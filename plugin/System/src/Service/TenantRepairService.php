<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\Constants\System;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Support\TenantContext;

/**
 * 租户基础数据恢复与历史 tenant_id 修复服务。
 */
final class TenantRepairService extends CoreService
{
    private const PLATFORM_LOG_TABLE = 'system_logs_action';

    private const TENANT_DELETE_IGNORED_AUDIT_TABLES = [
        'system_logs_action',
        'system_logs_change',
    ];

    /**
     * 确保升级库具备 system_user.super 字段；发布恢复路径不依赖开发迁移执行。
     * 不同数据库在线加索引语法差异较大，这里只补业务字段，索引由新库迁移或发布快照提供。
     *
     * @return array{created:bool,skipped:bool}
     */
    public function ensureSystemUserSuperColumn(bool $dryRun = false): array
    {
        if (!Schema::hasTable('system_user')) {
            return ['created' => false, 'skipped' => true];
        }

        if (Schema::hasColumn('system_user', 'super')) {
            return ['created' => false, 'skipped' => false];
        }

        if (!$dryRun) {
            Schema::table('system_user', static function (Blueprint $table): void {
                $table->addColumn('bigInteger', 'super', [])->nullable()->default(0)->comment('SaaS子超管(1是,0否)');
            });
        }

        return ['created' => true, 'skipped' => false];
    }

    /**
     * 确保发布升级库具备租户级上传配置表；正式发布恢复路径不依赖开发迁移执行。
     *
     * @return array{created:bool,skipped:bool}
     */
    public function ensureUploadConfigTable(bool $dryRun = false): array
    {
        if (Schema::hasTable('system_upload_config')) {
            return ['created' => false, 'skipped' => false];
        }

        if (!$dryRun) {
            Schema::create('system_upload_config', static function (Blueprint $table): void {
                $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
                $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
                $table->addColumn('text', 'value', [])->nullable()->comment('上传通道配置');
                $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
                $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
                $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
                $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
                $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
                $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
                $table->unique(['tenant_id'], 'uni_suc_tenant_id');
                $table->index(['deleted_at'], 'idx_suc_deleted_at');
                $table->comment('租户上传通道配置表');
            });
        }

        return ['created' => true, 'skipped' => false];
    }

    /**
     * 恢复固定默认租户 1；默认租户是系统保留租户，只允许后续维护资料字段。
     *
     * @return array{created:bool,updated:bool,skipped:bool}
     */
    public function ensureDefaultTenant(bool $dryRun = false): array
    {
        if (!Schema::hasTable('system_tenant')) {
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }

        $now = date('Y-m-d H:i:s');
        $expected = [
            'id' => TenantContext::DEFAULT_TENANT_ID,
            'code' => 'default',
            'name' => '默认租户',
            'package_code' => 'basic',
            'expired_at' => null,
            'status' => Status::ENABLED,
            'updated_at' => $now,
        ];

        $tenant = Db::table('system_tenant')
            ->where('id', TenantContext::DEFAULT_TENANT_ID)
            ->first();
        if (!$tenant) {
            $insert = $expected + [
                'contact_name' => '',
                'contact_phone' => '',
                'contact_email' => '',
                'remark' => '系统固定默认租户',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
            ];
            if (Schema::hasColumn('system_tenant', 'deleted_at')) {
                $insert['deleted_at'] = null;
            }
            if (!$dryRun) {
                Db::table('system_tenant')->insert($insert);
            }

            return ['created' => true, 'updated' => false, 'skipped' => false];
        }

        if (Schema::hasColumn('system_tenant', 'deleted_at')) {
            $expected['deleted_at'] = null;
        }

        $payload = [];
        foreach ($expected as $field => $value) {
            $current = $tenant->{$field} ?? null;
            if ($field === 'updated_at') {
                continue;
            }
            if ((string)($current ?? '') !== (string)($value ?? '')) {
                $payload[$field] = $value;
            }
        }
        if ($payload !== []) {
            $payload['updated_at'] = $now;
            if (!$dryRun) {
                Db::table('system_tenant')
                    ->where('id', TenantContext::DEFAULT_TENANT_ID)
                    ->update($payload);
            }
        }

        return ['created' => false, 'updated' => $payload !== [], 'skipped' => false];
    }

    /**
     * 历史脏数据改入默认租户前，先检查包含 tenant_id 的唯一索引是否会冲突。
     *
     * @return array{conflicts:int,items:list<array{table:string,index:string,columns:list<string>,type:string,count:int,sample:list<array<string,mixed>>}>}
     */
    public function precheckZeroTenantUniqueConflicts(): array
    {
        $items = [];
        foreach ($this->businessTenantTables() as $table) {
            foreach ($this->tenantUniqueIndexes($table) as $index) {
                $items = array_merge($items, $this->detectUniqueIndexConflicts($table, $index));
            }
        }

        return [
            'conflicts' => count($items),
            'items' => $items,
        ];
    }

    /**
     * 扫描所有 tenant_id 字段，将历史 0/null 修复到默认租户 1。
     *
     * @return array{dry_run:bool,tables:int,rows:int,items:list<array{table:string,rows:int,recent_id:int,recent_created_at:?string,recent_updated_at:?string,kept_id?:int,deleted_ids?:list<int>,log_summary?:list<array{router:string,response_code:string,username:string,rows:int,recent_id:int,recent_created_at:?string}>}>}
     */
    public function repairZeroTenantRows(bool $dryRun = false): array
    {
        $items = [];
        $rows = 0;
        foreach ($this->businessTenantTables() as $table) {
            if ($table === 'system_upload_config') {
                $diagnostics = $this->tenantDirtyRowDiagnostics($table);
                // 上传配置存在 tenant_id 唯一约束，必须先做专用合并，不能参与通用 tenant_id 批量修复。
                $uploadConfig = $this->repairUploadConfigZeroTenantRows($dryRun);
                $count = (int)$uploadConfig['rows'];
                if ($count > 0) {
                    $rows += $count;
                    $item = ['table' => $table, 'rows' => $count] + $diagnostics;
                    if ((int)$uploadConfig['kept_id'] > 0) {
                        $item['kept_id'] = (int)$uploadConfig['kept_id'];
                    }
                    if ($uploadConfig['deleted_ids'] !== []) {
                        $item['deleted_ids'] = $uploadConfig['deleted_ids'];
                    }
                    $items[] = $item;
                }
                continue;
            }

            $count = (int)Db::table($table)
                ->where(static function ($query): void {
                    $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
                })
                ->count();
            if ($count <= 0) {
                continue;
            }

            $diagnostics = $this->tenantDirtyRowDiagnostics($table);
            if (!$dryRun) {
                Db::table($table)
                    ->where(static function ($query): void {
                        $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
                    })
                    ->update(['tenant_id' => TenantContext::DEFAULT_TENANT_ID]);
            }

            $rows += $count;
            $items[] = ['table' => $table, 'rows' => $count] + $diagnostics;
        }

        return [
            'dry_run' => $dryRun,
            'tables' => count($items),
            'rows' => $rows,
            'items' => $items,
        ];
    }

    /**
     * system_logs_action 允许 tenant_id=0 保存平台日志；这里仅诊断，不参与默认租户修复。
     *
     * @return array{dry_run:bool,tables:int,rows:int,items:list<array{table:string,rows:int,recent_id:int,recent_created_at:?string,recent_updated_at:?string,log_summary?:list<array{router:string,response_code:string,username:string,rows:int,recent_id:int,recent_created_at:?string}>}>}
     */
    public function platformLogRows(bool $dryRun = false): array
    {
        if (!Schema::hasTable(self::PLATFORM_LOG_TABLE) || !Schema::hasColumn(self::PLATFORM_LOG_TABLE, 'tenant_id')) {
            return ['dry_run' => $dryRun, 'tables' => 0, 'rows' => 0, 'items' => []];
        }

        $count = (int)Db::table(self::PLATFORM_LOG_TABLE)
            ->where(static function ($query): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
            })
            ->count();
        if ($count <= 0) {
            return ['dry_run' => $dryRun, 'tables' => 0, 'rows' => 0, 'items' => []];
        }

        return [
            'dry_run' => $dryRun,
            'tables' => 1,
            'rows' => $count,
            'items' => [[
                'table' => self::PLATFORM_LOG_TABLE,
                'rows' => $count,
            ] + $this->tenantDirtyRowDiagnostics(self::PLATFORM_LOG_TABLE)],
        ];
    }

    /**
     * 将旧全局 system_data.config_upload 仅迁移到固定默认租户 1，其它租户后续使用内置默认配置自行维护。
     *
     * @return array{created:bool,updated:bool,skipped:bool}
     */
    public function migrateLegacyUploadConfigToDefaultTenant(bool $dryRun = false): array
    {
        if (!Schema::hasTable('system_upload_config')) {
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }
        if (!Schema::hasTable('system_data')) {
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }

        $legacy = Db::table('system_data')
            ->where('name', 'config_upload')
            ->whereNull('deleted_at')
            ->first();
        if (!$legacy) {
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }

        $now = date('Y-m-d H:i:s');
        $exists = Db::table('system_upload_config')
            ->where('tenant_id', TenantContext::DEFAULT_TENANT_ID)
            ->first();
        $payload = [
            'tenant_id' => TenantContext::DEFAULT_TENANT_ID,
            'value' => (string)($legacy->value ?? ''),
            'remark' => '上传通道配置',
            'updated_by' => 0,
            'updated_at' => $now,
            'deleted_at' => null,
        ];

        if (!$exists) {
            if (!$dryRun) {
                Db::table('system_upload_config')->insert($payload + [
                    'created_by' => 0,
                    'created_at' => $now,
                ]);
            }

            return ['created' => true, 'updated' => false, 'skipped' => false];
        }

        if (($exists->deleted_at ?? null) === null) {
            // 旧全局配置只作为首次升级迁移来源；默认租户已有独立配置后，修复命令不得再次覆盖租户自行保存的配置。
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }

        if (!$dryRun) {
            Db::table('system_upload_config')
                ->where('tenant_id', TenantContext::DEFAULT_TENANT_ID)
                ->update($payload);
        }

        return ['created' => false, 'updated' => true, 'skipped' => false];
    }

    /**
     * 为缺失子超管的租户补齐一个启用用户，防止升级后租户管理员无法进入初始化/管理闭环。
     *
     * @return array{dry_run:bool,tenants:int,users:int,items:list<array{tenant_id:int,user_id:int,username:string}>,missing_tenant_super_without_candidate:list<int>}
     */
    public function ensureTenantSuperUsers(bool $dryRun = false): array
    {
        if (
            !Schema::hasTable('system_tenant')
            || !Schema::hasTable('system_user')
            || !Schema::hasColumn('system_user', 'super')
        ) {
            return ['dry_run' => $dryRun, 'tenants' => 0, 'users' => 0, 'items' => [], 'missing_tenant_super_without_candidate' => []];
        }

        $items = [];
        $missing = [];
        $tenants = Db::table('system_tenant')
            ->where('id', '>', TenantContext::UNSET_TENANT_ID)
            ->where('status', Status::ENABLED)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->toArray();

        foreach ($tenants as $tenantId) {
            $tenantId = (int)$tenantId;
            if ($tenantId <= TenantContext::UNSET_TENANT_ID || $this->hasEnabledTenantSuperUser($tenantId)) {
                continue;
            }

            // 升级历史库时优先把原“租户管理员”角色用户提升为子超管；没有角色数据时才退回首个启用用户。
            $candidate = $this->firstTenantSuperCandidate($tenantId);
            if (!$candidate) {
                $missing[] = $tenantId;
                continue;
            }

            if (!$dryRun) {
                Db::table('system_user')
                    ->where('id', (int)$candidate->id)
                    ->update(['super' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            }

            $items[] = [
                'tenant_id' => $tenantId,
                'user_id' => (int)$candidate->id,
                'username' => (string)($candidate->username ?? ''),
            ];
        }

        return [
            'dry_run' => $dryRun,
            'tenants' => count($items),
            'users' => count($items),
            'items' => $items,
            'missing_tenant_super_without_candidate' => $missing,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tenantTables(): array
    {
        $tables = [];
        $schema = Db::getSchemaBuilder();
        try {
            foreach ($schema->getTables() as $row) {
                $row = (array)$row;
                $table = strtolower((string)($row['name'] ?? $row['Name'] ?? $row['table'] ?? $row['TABLE_NAME'] ?? ''));
                if ($table !== '' && Schema::hasColumn($table, 'tenant_id')) {
                    $tables[] = $table;
                }
            }
        } catch (\Throwable) {
            // SQLite 等驱动不支持 SchemaBuilder::getTables()，发布安装仍必须能扫描并修复 tenant_id 脏数据。
            foreach (Db::connection()->getDoctrineSchemaManager()->listTableNames() as $table) {
                $table = strtolower((string)$table);
                if ($table !== '' && Schema::hasColumn($table, 'tenant_id')) {
                    $tables[] = $table;
                }
            }
        }

        sort($tables);

        return array_values(array_unique($tables));
    }

    /**
     * 业务租户表必须修复 tenant_id=0/null；操作日志平台 0 例外不参与业务修复。
     *
     * @return array<int, string>
     */
    public function businessTenantTables(): array
    {
        return array_values(array_filter(
            $this->tenantTables(),
            static fn (string $table): bool => $table !== self::PLATFORM_LOG_TABLE
        ));
    }

    /**
     * 租户硬删只检查真实业务数据；操作审计表不作为租户业务占用阻断，但脏 tenant_id 仍由修复命令按各自规则处理。
     *
     * @return array<int, string>
     */
    public function tenantDeleteBusinessTables(): array
    {
        return array_values(array_filter(
            $this->tenantTables(),
            static fn (string $table): bool => !in_array($table, self::TENANT_DELETE_IGNORED_AUDIT_TABLES, true)
        ));
    }

    /**
     * 安装、升级和人工修复共用入口；先恢复基础结构和默认租户，再修复历史脏数据与子超管兜底。
     *
     * @return array{system_user_super_column:array{created:bool,skipped:bool},upload_config_table:array{created:bool,skipped:bool},tenant_unique_precheck:array{conflicts:int,items:list<array{table:string,index:string,columns:list<string>,type:string,count:int,sample:list<array<string,mixed>>}>},default_tenant:array{created:bool,updated:bool,skipped:bool},legacy_upload_config:array{created:bool,updated:bool,skipped:bool},tenant_rows:array{dry_run:bool,tables:int,rows:int,items:list<array{table:string,rows:int,recent_id:int,recent_created_at:?string,recent_updated_at:?string,kept_id?:int,deleted_ids?:list<int>,log_summary?:list<array{router:string,response_code:string,username:string,rows:int,recent_id:int,recent_created_at:?string}>}>},platform_log_rows:array{dry_run:bool,tables:int,rows:int,items:list<array{table:string,rows:int,recent_id:int,recent_created_at:?string,recent_updated_at:?string,log_summary?:list<array{router:string,response_code:string,username:string,rows:int,recent_id:int,recent_created_at:?string}>}>},tenant_super_users:array{dry_run:bool,tenants:int,users:int,items:list<array{tenant_id:int,user_id:int,username:string}>,missing_tenant_super_without_candidate:list<int>}}
     */
    public function repair(bool $dryRun = false): array
    {
        $superColumn = $this->ensureSystemUserSuperColumn($dryRun);
        $uploadConfigTable = $this->ensureUploadConfigTable($dryRun);
        $uniquePrecheck = $this->precheckZeroTenantUniqueConflicts();
        if (!$dryRun && $uniquePrecheck['conflicts'] > 0) {
            // tenant_id=0/null 改入默认租户前必须先拦截唯一键冲突，避免修复命令执行到一半留下混合状态。
            throw new ErrorResponseException('历史租户数据修复存在唯一键冲突，请先处理冲突后重试：' . $this->formatUniqueConflictSummary($uniquePrecheck['items']));
        }

        return [
            'system_user_super_column' => $superColumn,
            'upload_config_table' => $uploadConfigTable,
            'tenant_unique_precheck' => $uniquePrecheck,
            'default_tenant' => $this->ensureDefaultTenant($dryRun),
            'tenant_rows' => $this->repairZeroTenantRows($dryRun),
            'platform_log_rows' => $this->platformLogRows($dryRun),
            'legacy_upload_config' => $this->migrateLegacyUploadConfigToDefaultTenant($dryRun),
            'tenant_super_users' => $this->ensureTenantSuperUsers($dryRun),
        ];
    }

    /**
     * 上传配置按 tenant_id 唯一；历史 0/null 行需要先合并到默认租户，避免通用批量 update 撞唯一键。
     *
     * @return array{rows:int,kept_id:int,deleted_ids:list<int>}
     */
    private function repairUploadConfigZeroTenantRows(bool $dryRun): array
    {
        if (!Schema::hasTable('system_upload_config')) {
            return ['rows' => 0, 'kept_id' => 0, 'deleted_ids' => []];
        }

        $dirtyQuery = Db::table('system_upload_config')
            ->where(static function ($query): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
            });
        $count = (int)$dirtyQuery->count();
        if ($count <= 0) {
            return ['rows' => 0, 'kept_id' => 0, 'deleted_ids' => []];
        }

        $defaultExists = Db::table('system_upload_config')
            ->where('tenant_id', TenantContext::DEFAULT_TENANT_ID)
            ->exists();
        $dirtyRows = Db::table('system_upload_config')
            ->where(static function ($query): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id'])
            ->all();
        if ($dirtyRows === []) {
            return ['rows' => $count, 'kept_id' => 0, 'deleted_ids' => []];
        }

        $keepId = 0;
        if (!$defaultExists) {
            $keepId = (int)($dirtyRows[0]->id ?? 0);
            if (!$dryRun && $keepId > 0) {
                // 默认租户没有配置时保留最近更新的一条历史配置，其余脏行删除，避免唯一键冲突。
                Db::table('system_upload_config')->where('id', $keepId)->update([
                    'tenant_id' => TenantContext::DEFAULT_TENANT_ID,
                    'deleted_at' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $deleteIds = array_values(array_filter(array_map(
            static fn (object $row): int => (int)($row->id ?? 0),
            $dirtyRows
        ), static fn (int $id): bool => $id > 0 && $id !== $keepId));
        if (!$dryRun && $deleteIds !== []) {
            Db::table('system_upload_config')->whereIn('id', $deleteIds)->delete();
        }

        return ['rows' => $count, 'kept_id' => $keepId, 'deleted_ids' => $deleteIds];
    }

    /**
     * 为 tenant_id=0/null 修复报告提供只读摘要；摘要在真正 update 前生成，用于定位旧实例或异常入口。
     *
     * @return array{recent_id:int,recent_created_at:?string,recent_updated_at:?string,log_summary?:list<array{router:string,response_code:string,username:string,rows:int,recent_id:int,recent_created_at:?string}>}
     */
    private function tenantDirtyRowDiagnostics(string $table): array
    {
        $recent = Db::table($table)
            ->where(static function ($query): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
            })
            ->orderByDesc(Schema::hasColumn($table, 'id') ? 'id' : 'tenant_id')
            ->first([
                Schema::hasColumn($table, 'id') ? 'id' : Db::raw('0 as id'),
                Schema::hasColumn($table, 'created_at') ? 'created_at' : Db::raw('NULL as created_at'),
                Schema::hasColumn($table, 'updated_at') ? 'updated_at' : Db::raw('NULL as updated_at'),
            ]);

        $diagnostics = [
            'recent_id' => (int)($recent->id ?? 0),
            'recent_created_at' => $this->nullableDateString($recent->created_at ?? null),
            'recent_updated_at' => $this->nullableDateString($recent->updated_at ?? null),
        ];

        if ($table === 'system_logs_action') {
            $diagnostics['log_summary'] = array_map(
                static fn (object $row): array => [
                    'router' => (string)($row->router ?? ''),
                    'response_code' => (string)($row->response_code ?? ''),
                    'username' => (string)($row->username ?? ''),
                    'rows' => (int)($row->rows ?? 0),
                    'recent_id' => (int)($row->recent_id ?? 0),
                    'recent_created_at' => isset($row->recent_created_at) ? (string)$row->recent_created_at : null,
                ],
                Db::table($table)
                    ->select('router', 'response_code', 'username')
                    ->selectRaw('COUNT(*) AS rows')
                    ->selectRaw('MAX(id) AS recent_id')
                    ->selectRaw('MAX(created_at) AS recent_created_at')
                    ->where(static function ($query): void {
                        $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
                    })
                    ->groupBy('router', 'response_code', 'username')
                    ->orderByDesc('recent_id')
                    ->limit(10)
                    ->get()
                    ->all()
            );
        }

        return $diagnostics;
    }

    private function nullableDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }

    private function hasEnabledTenantSuperUser(int $tenantId): bool
    {
        return Db::table('system_user')
            ->where(DataField::TENANT, $tenantId)
            ->where('id', '!=', System::getSuperId())
            ->where('super', 1)
            ->where('status', Status::ENABLED)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function firstTenantSuperCandidate(int $tenantId): ?object
    {
        if (Schema::hasTable('system_role') && Schema::hasTable('system_user_role')) {
            $roleUser = Db::table('system_user')
                ->select('system_user.id', 'system_user.username')
                ->join('system_user_role', 'system_user.id', '=', 'system_user_role.user_id')
                ->join('system_role', 'system_user_role.role_id', '=', 'system_role.id')
                ->where('system_user.' . DataField::TENANT, $tenantId)
                ->where('system_user.id', '!=', System::getSuperId())
                ->where('system_user.status', Status::ENABLED)
                ->whereNull('system_user.deleted_at')
                ->where('system_user_role.' . DataField::TENANT, $tenantId)
                ->where('system_role.' . DataField::TENANT, $tenantId)
                ->where('system_role.name', '租户管理员')
                ->whereNull('system_role.deleted_at')
                ->orderBy('system_user.id')
                ->first();
            if ($roleUser) {
                return $roleUser;
            }
        }

        return Db::table('system_user')
            ->select('id', 'username')
            ->where(DataField::TENANT, $tenantId)
            ->where('id', '!=', System::getSuperId())
            ->where('status', Status::ENABLED)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<array{name:string,columns:list<string>}>
     */
    private function tenantUniqueIndexes(string $table): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $indexes = [];
        foreach (Schema::getIndexes($table) as $index) {
            $index = (array)$index;
            if (!($index['unique'] ?? false) || ($index['primary'] ?? false)) {
                continue;
            }

            $columns = $this->normalizeIndexColumns($index['columns'] ?? []);
            if (!in_array('tenant_id', $columns, true)) {
                continue;
            }

            $name = trim((string)($index['name'] ?? ''));
            $indexes[] = [
                'name' => $name === '' ? implode('_', $columns) : $name,
                'columns' => $columns,
            ];
        }

        return $indexes;
    }

    /**
     * @return list<array{table:string,index:string,columns:list<string>,type:string,count:int,sample:list<array<string,mixed>>}>
     */
    private function detectUniqueIndexConflicts(string $table, array $index): array
    {
        $columns = array_values(array_filter($index['columns'] ?? [], static fn (string $column): bool => $column !== ''));
        $businessColumns = array_values(array_filter($columns, static fn (string $column): bool => $column !== 'tenant_id'));
        if ($businessColumns === []) {
            if ($table === 'system_upload_config') {
                return [];
            }

            // 只有 tenant_id 的唯一索引无法按业务字段去重；多条脏行或默认租户已存在时只能人工处理。
            $dirtyRows = (int)Db::table($table)
                ->where(static function ($query): void {
                    $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID);
                })
                ->count();
            $defaultExists = Db::table($table)
                ->where('tenant_id', TenantContext::DEFAULT_TENANT_ID)
                ->exists();
            if ($dirtyRows > 1 || ($dirtyRows > 0 && $defaultExists)) {
                return [[
                    'table' => $table,
                    'index' => (string)$index['name'],
                    'columns' => $columns,
                    'type' => 'tenant_only',
                    'count' => $dirtyRows,
                    'sample' => [['dirty_rows' => $dirtyRows, 'default_exists' => $defaultExists]],
                ]];
            }

            return [];
        }

        $selects = [];
        foreach ($businessColumns as $column) {
            $selects[] = sprintf('`%s`', str_replace('`', '``', $column));
        }
        $keyColumnsSql = implode(', ', $selects);
        $dirtyWhereSql = '(tenant_id IS NULL OR tenant_id <= ?)';
        $notNullSql = implode(' AND ', array_map(
            static fn (string $column): string => sprintf('`%s` IS NOT NULL', str_replace('`', '``', $column)),
            $businessColumns
        ));
        $defaultTenantId = TenantContext::DEFAULT_TENANT_ID;
        $unsetTenantId = TenantContext::UNSET_TENANT_ID;
        $items = [];

        $internalRows = Db::select(sprintf(
            'SELECT %s, COUNT(*) AS aggregate FROM `%s` WHERE %s AND %s GROUP BY %s HAVING COUNT(*) > 1 LIMIT 20',
            $keyColumnsSql,
            str_replace('`', '``', $table),
            $dirtyWhereSql,
            $notNullSql,
            $keyColumnsSql
        ), [$unsetTenantId]);
        if ($internalRows !== []) {
            $items[] = $this->buildUniqueConflictItem($table, $index, 'dirty_internal', $internalRows);
        }

        // 同一业务键如果默认租户 1 已经存在，脏行迁移过去也会撞唯一键，需要在写入前报告样本。
        $join = [];
        foreach ($businessColumns as $column) {
            $quoted = sprintf('`%s`', str_replace('`', '``', $column));
            $join[] = sprintf('d.%1$s = t.%1$s', $quoted);
        }
        $externalRows = Db::select(sprintf(
            'SELECT %s, COUNT(*) AS aggregate FROM `%s` d INNER JOIN `%s` t ON t.tenant_id = ? AND %s WHERE (d.tenant_id IS NULL OR d.tenant_id <= ?) AND %s GROUP BY %s LIMIT 20',
            implode(', ', array_map(static fn (string $column): string => 'd.`' . str_replace('`', '``', $column) . '`', $businessColumns)),
            str_replace('`', '``', $table),
            str_replace('`', '``', $table),
            implode(' AND ', $join),
            implode(' AND ', array_map(static fn (string $column): string => 'd.`' . str_replace('`', '``', $column) . '` IS NOT NULL', $businessColumns)),
            implode(', ', array_map(static fn (string $column): string => 'd.`' . str_replace('`', '``', $column) . '`', $businessColumns))
        ), [$defaultTenantId, $unsetTenantId]);
        if ($externalRows !== []) {
            $items[] = $this->buildUniqueConflictItem($table, $index, 'default_tenant_existing', $externalRows);
        }

        return $items;
    }

    /**
     * @param array<int, object> $rows
     * @return array{table:string,index:string,columns:list<string>,type:string,count:int,sample:list<array<string,mixed>>}
     */
    private function buildUniqueConflictItem(string $table, array $index, string $type, array $rows): array
    {
        return [
            'table' => $table,
            'index' => (string)$index['name'],
            'columns' => $index['columns'],
            'type' => $type,
            'count' => count($rows),
            'sample' => array_map(static fn (object $row): array => (array)$row, array_slice($rows, 0, 5)),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeIndexColumns(mixed $columns): array
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        if (!is_array($columns)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $column): string => trim((string)$column, " \t\n\r\0\x0B`"),
            $columns
        ), static fn (string $column): bool => $column !== ''));
    }

    /**
     * @param list<array{table:string,index:string,columns:list<string>,type:string,count:int,sample:list<array<string,mixed>>}> $items
     */
    private function formatUniqueConflictSummary(array $items): string
    {
        $parts = [];
        foreach (array_slice($items, 0, 5) as $item) {
            $parts[] = sprintf('%s.%s[%s]=%s', $item['table'], $item['index'], implode(',', $item['columns']), $item['type']);
        }

        return implode('；', $parts);
    }
}
