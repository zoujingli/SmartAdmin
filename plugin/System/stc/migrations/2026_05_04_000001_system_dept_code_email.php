<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'uni_sd_dbda_tenant_id_code';

    public function up(): void
    {
        if (!Schema::hasTable('system_dept')) {
            return;
        }

        $hadCode = Schema::hasColumn('system_dept', 'code');
        $hadEmail = Schema::hasColumn('system_dept', 'email');

        Schema::table('system_dept', function (Blueprint $table) use ($hadCode, $hadEmail) {
            // 兼容已初始化环境：fresh 建表已包含字段，这里仅为已有开发库补齐前端已提交的编码和邮箱字段。
            if (!$hadCode) {
                $table->addColumn('string', 'code', ['length' => 50])->nullable()->default('')->after('pid')->comment('部门编码');
            }
            if (!$hadEmail) {
                $table->addColumn('string', 'email', ['length' => 50])->nullable()->default('')->after('phone')->comment('部门邮箱');
            }
        });

        if (!Schema::hasIndex('system_dept', self::UNIQUE_INDEX, 'unique')) {
            $this->normalizeCodeBeforeUniqueIndex();

            Schema::table('system_dept', function (Blueprint $table) {
                // 部门编码用于前端导入、展示和跨层级识别；唯一性按租户隔离，避免不同租户编码互相冲突。
                $table->unique(['tenant_id', 'code'], self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_dept')) {
            return;
        }

        if (Schema::hasIndex('system_dept', self::UNIQUE_INDEX, 'unique')) {
            Schema::table('system_dept', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        $hasEmail = Schema::hasColumn('system_dept', 'email');
        $hasCode = Schema::hasColumn('system_dept', 'code');

        Schema::table('system_dept', function (Blueprint $table) use ($hasEmail, $hasCode) {
            if ($hasEmail) {
                $table->dropColumn('email');
            }
            if ($hasCode) {
                $table->dropColumn('code');
            }
        });
    }

    /**
     * 创建唯一索引前先修复历史数据：空编码和同租户重复编码会导致索引创建失败。
     */
    private function normalizeCodeBeforeUniqueIndex(): void
    {
        $seen = [];

        foreach (Db::table('system_dept')->orderBy('id')->get(['id', 'tenant_id', 'code']) as $row) {
            $id = (int)$row->id;
            $tenantId = (int)($row->tenant_id ?? 0);
            $originalCode = (string)($row->code ?? '');
            $code = trim($originalCode);
            $lookup = $this->normalizeLookupCode($code);

            if ($code === '' || isset($seen[$tenantId][$lookup])) {
                // 历史库可能已有空编码或重复编码，使用主键生成稳定兜底值，保证升级可重复执行。
                $code = $this->makeFallbackCode($id, $seen[$tenantId] ?? []);
                $lookup = $this->normalizeLookupCode($code);
            }

            $seen[$tenantId][$lookup] = true;

            if ($code !== $originalCode) {
                Db::table('system_dept')
                    ->where('id', $id)
                    ->update(['code' => $code]);
            }
        }
    }

    /**
     * MySQL 默认字符集通常大小写不敏感，迁移侧用小写键提前规避唯一索引冲突。
     */
    private function normalizeLookupCode(string $code): string
    {
        return strtolower($code);
    }

    /**
     * 为冲突数据生成不超过字段长度的兜底编码，并避开本租户内已占用值。
     *
     * @param array<string, bool> $used
     */
    private function makeFallbackCode(int $id, array $used): string
    {
        $base = 'dept_' . $id;
        $code = $base;
        $counter = 1;

        while (isset($used[$this->normalizeLookupCode($code)])) {
            $suffix = '_' . $counter++;
            $code = substr($base, 0, 50 - strlen($suffix)) . $suffix;
        }

        return $code;
    }
};
