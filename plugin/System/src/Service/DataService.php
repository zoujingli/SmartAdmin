<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Service;

use Composer\InstalledVersions;
use Library\CoreService;
use Library\Events\Processor\AuthProcessor;
use Library\Exception\UnauthorizedResponseException;
use Library\Support\CacheDriverResolver;
use Library\Support\ModuleRegistry;
use Library\Support\ModelChangeLog;
use System\Mapper\DataMapper;
use System\Mapper\UserMapper;
use System\Support\SystemAppMeta;

/**
 * 系统数据服务
 * 处理系统配置数据和统计信息.
 */
final class DataService extends CoreService
{
    /**
     * @param DataMapper $mapper 系统数据访问层
     * @param UserMapper $userMapper 用户数据访问层
     * @param OnlineUserService $onlineUsers 在线用户服务
     * @param AuthCacheService $authCache 鉴权缓存服务
     * @param UserService $users 用户服务
     */
    public function __construct(
        protected DataMapper $mapper,
        protected UserMapper $userMapper,
        protected OnlineUserService $onlineUsers,
        protected AuthCacheService $authCache,
        protected UserService $users,
    ) {}

    /**
     * 获取系统数据统计.
     */
    public function getStatistics(): array
    {
        $onlineSummary = $this->getScopedOnlineSummary(0);

        return [
            'user_count' => $this->mapper->getUserCount(),
            'role_count' => $this->mapper->getRoleCount(),
            'menu_count' => $this->mapper->getMenuCount(),
            'dept_count' => $this->mapper->getDeptCount(),
            'post_count' => $this->mapper->getPostCount(),
            'node_count' => $this->mapper->getNodeCount(),
            'log_count' => $this->mapper->getLogCount(),
            'online_count' => (int)($onlineSummary['user_count'] ?? 0),
            'online_session_count' => (int)($onlineSummary['session_count'] ?? 0),
            'today_logs' => $this->mapper->getTodayLogs(),
            'recent_users' => $this->mapper->getRecentUsers(),
        ];
    }

    /**
     * 获取 System 模块公共能力概览.
     */
    public function getCapabilities(): array
    {
        $modules = array_map(
            static function (array $module): array {
                unset($module['code']);

                return $module;
            },
            $this->filterAccessibleModules(ModuleRegistry::modules())
        );
        $commonFeatures = ModuleRegistry::commonCapabilities();
        $onlineSummary = $this->getScopedOnlineSummary();

        return [
            'summary' => [
                'module_count' => count($modules),
                'common_capability_count' => count($commonFeatures),
                'cache_driver' => CacheDriverResolver::effectiveStoreKey(),
                'cache_dynamic' => true,
                'permission_strategy' => '@Auth + system_node + system_role_node',
                'menu_source' => 'MenuSeedRegistry + system_menu sync',
                'online_user_count' => (int)($onlineSummary['user_count'] ?? 0),
                'online_session_count' => (int)($onlineSummary['session_count'] ?? 0),
            ],
            'common_features' => $commonFeatures,
            'modules' => $modules,
            'online_users' => $onlineSummary['users'] ?? [],
        ];
    }

    /**
     * 获取提供给界面布局使用的公开 UI 元信息。
     */
    public function getUiMeta(): array
    {
        $meta = $this->getAppMetaConfig();
        $defaults = SystemAppMeta::defaults();

        return [
            'app_name' => (string)($meta['app_name'] ?? $defaults['app_name']),
            'app_version' => (string)($meta['app_version'] ?? $defaults['app_version']),
            'app_description' => (string)($meta['app_description'] ?? $defaults['app_description']),
            'login_title' => (string)($meta['login_title'] ?? $defaults['login_title']),
            'login_description' => (string)($meta['login_description'] ?? $defaults['login_description']),
            'logo_url' => (string)($meta['logo_url'] ?? $defaults['logo_url']),
            'logo_file_id' => (int)($meta['logo_file_id'] ?? $defaults['logo_file_id']),
            'copyright' => [
                'enable' => $this->normalizeBool($meta['copyright_enable'] ?? $defaults['copyright_enable'], true),
                'companyName' => (string)($meta['company_name'] ?? $defaults['company_name']),
                'companySiteLink' => (string)($meta['company_site_link'] ?? $defaults['company_site_link']),
                'date' => (string)($meta['copyright_date'] ?? $defaults['copyright_date']),
                'icp' => (string)($meta['icp'] ?? $defaults['icp']),
                'icpLink' => (string)($meta['icp_link'] ?? $defaults['icp_link']),
            ],
        ];
    }

    /**
     * 获取工作台待办事项.
     * @return array<int, array<string, mixed>>
     */
    public function getWorkbenchTodos(): array
    {
        $now = date('Y-m-d H:i:s');
        $items = [];

        if ($this->canAccessCode('system.logs.action.index')) {
            $errorLogs = $this->mapper->getTodayServerErrorLogs();

            $items[] = [
                'title' => '检查异常日志',
                'content' => $errorLogs > 0
                    ? "今日发现 <a>{$errorLogs}</a> 条 5xx 异常日志，建议优先排查失败接口与服务波动。"
                    : '今日暂未发现 5xx 异常日志。',
                'completed' => $errorLogs === 0,
                'date' => $now,
                'url' => '/system/logs/action',
                'action_text' => $errorLogs > 0 ? '查看日志' : '',
                'level' => $errorLogs > 0 ? 'danger' : 'success',
            ];

            $failedLogins = $this->mapper->getTodayFailedLoginLogs();

            $items[] = [
                'title' => '复核登录失败',
                'content' => $failedLogins > 0
                    ? "今日共有 <a>{$failedLogins}</a> 次登录失败记录，建议检查密码错误、账号状态与恶意尝试。"
                    : '今日暂无异常登录失败记录。',
                'completed' => $failedLogins === 0,
                'date' => $now,
                'url' => '/system/logs/action',
                'action_text' => $failedLogins > 0 ? '检查登录日志' : '',
                'level' => $failedLogins > 0 ? 'warning' : 'success',
            ];
        }

        if ($this->canAccessCode('system.tenant.index')) {
            $disabledTenants = $this->mapper->getDisabledTenantCount();
            $expiringTenants = $this->mapper->getExpiringTenantCount();
            $tenantIssueCount = $disabledTenants + $expiringTenants;

            $tenantMessage = $tenantIssueCount > 0
                ? "当前有 <a>{$disabledTenants}</a> 个停用租户、<a>{$expiringTenants}</a> 个 7 天内到期租户待处理。"
                : '当前租户状态正常，暂无停用或即将到期的租户。';

            $items[] = [
                'title' => '确认租户状态',
                'content' => $tenantMessage,
                'completed' => $tenantIssueCount === 0,
                'date' => $now,
                'url' => '/system/tenant',
                'action_text' => $tenantIssueCount > 0 ? '处理租户' : '',
                'level' => $tenantIssueCount > 0 ? 'warning' : 'success',
            ];
        }

        if ($this->canAccessCode('system.file.index')) {
            $duplicateGroups = $this->mapper->getDuplicateFileGroupCount();

            $items[] = [
                'title' => '清理重复文件',
                'content' => $duplicateGroups > 0
                    ? "当前检测到 <a>{$duplicateGroups}</a> 组重复文件，建议进入文件管理执行批量去重。"
                    : '当前未检测到重复文件分组。',
                'completed' => $duplicateGroups === 0,
                'date' => $now,
                'url' => '/system/file',
                'action_text' => $duplicateGroups > 0 ? '执行去重' : '',
                'level' => $duplicateGroups > 0 ? 'info' : 'success',
            ];
        }

        if ($this->canAccessCode('system.data.index')) {
            $onlineSummary = $this->getScopedOnlineSummary(0);
            $userCount = (int)($onlineSummary['user_count'] ?? 0);
            $sessionCount = (int)($onlineSummary['session_count'] ?? 0);
            $sessionGap = $userCount > 0 && $sessionCount > ($userCount * 2) ? ($sessionCount - $userCount) : 0;

            $items[] = [
                'title' => '关注在线会话',
                'content' => $sessionGap > 0
                    ? "当前在线用户 <a>{$userCount}</a> 人，但会话达到 <a>{$sessionCount}</a> 个，建议复核异常会话。"
                    : "当前在线用户 <a>{$userCount}</a> 人、在线会话 <a>{$sessionCount}</a> 个，整体运行平稳。",
                'completed' => $sessionGap === 0,
                'date' => $now,
                'url' => '/system/data',
                'action_text' => $sessionGap > 0 ? '查看会话' : '',
                'level' => $sessionGap > 0 ? 'warning' : 'success',
            ];
        }

        return $items;
    }

    /**
     * 获取系统信息.
     */
    public function getSystemInfo(): array
    {
        $meta = $this->getAppMetaConfig();
        $swooleVersion = function_exists('swoole_version') ? swoole_version() : '未安装';
        $hyperfVersion = class_exists(InstalledVersions::class)
            ? (InstalledVersions::getPrettyVersion('hyperf/framework') ?: InstalledVersions::getPrettyVersion('hyperf/hyperf'))
            : null;

        return [
            'name' => (string)($meta['app_name'] ?? 'SmartAdmin'),
            'version' => (string)($meta['app_version'] ?? '1.0.0'),
            'php_version' => PHP_VERSION,
            'hyperf_version' => $hyperfVersion ?: '未知',
            'swoole_version' => $swooleVersion,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
    }

    /**
     * 获取系统配置.
     */
    public function getConfig(): array
    {
        $config = $this->mapper->getConfig();
        if (array_key_exists('app_meta', $config)) {
            // 配置预览保持通用结构，但 app_meta 需要统一成对象，兼容 JSON 字符串和二次嵌套的历史数据。
            $config['app_meta'] = SystemAppMeta::normalize($config['app_meta']);
        }

        return $config;
    }

    /**
     * 更新系统配置.
     */
    public function updateConfig(array $params): array
    {
        // 通用配置页仍按动态 key 保存，但入口必须先确认请求体是对象数组，防止非结构化内容落入 config_*。
        $params = _vali([
            'configs.value' => $params,
            'configs.array' => '系统配置必须是对象',
        ])['configs'];

        $current = $this->mapper->getConfig();
        $this->mapper->updateConfig($params);
        foreach ($params as $key => $value) {
            $this->recordConfigChange((string)$key, $current[(string)$key] ?? null, $value);
        }

        return $this->getConfig();
    }

    /**
     * 清理当前用户或全站业务缓存。
     */
    public function clearCache(): array
    {
        $currentUser = user();
        if (!$currentUser) {
            throw new UnauthorizedResponseException('未登录');
        }

        $userId = (int)$currentUser->getId();
        if ($currentUser->isSuper()) {
            $this->authCache->bumpGlobalVersion();
            $this->users->clearAllUserListSnapshots();
            $this->onlineUsers->clearAll();
            AuthProcessor::clearCache();

            return [
                'scope' => 'global',
                'message' => '全站业务缓存已清理',
                'items' => [
                    'permission_global_version',
                    'user_list_snapshot_global_version',
                    'online_user_index',
                    'auth_annotation_runtime_cache',
                ],
            ];
        }

        $this->authCache->forgetUser($userId);
        $this->users->clearUserListSnapshotsForUser($userId);

        return [
            'scope' => 'self',
            'message' => '个人缓存已清理',
            'items' => [
                'permission_user_version',
                'user_list_snapshot_user_version',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAppMetaConfig(): array
    {
        $config = $this->mapper->getConfig();
        // UI 元信息会影响登录页、标题与版权展示，读取时必须与系统参数页使用同一套兼容规则。
        return SystemAppMeta::mergeDefaults($config['app_meta'] ?? [], $config);
    }

    /**
     * 将配置值归一化为布尔值。
     */
    private function normalizeBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $default;
    }

    /**
     * 动态配置的 value 是数组/JSON，模型规则不会自动展开，保存后在 Service 层记录可读变更。
     */
    private function recordConfigChange(string $key, mixed $oldValue, mixed $newValue): void
    {
        $record = $this->mapper->findConfigRecord($key);
        if (!$record) {
            return;
        }

        ModelChangeLog::recordFields($record, 'updated', [[
            'field' => 'value',
            'label' => '配置内容',
            'old' => $oldValue,
            'new' => $newValue,
        ]]);
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     * @return array<int, array<string, mixed>>
     */
    private function filterAccessibleModules(array $modules): array
    {
        $currentUser = user();
        if (!$currentUser) {
            return [];
        }

        if ($currentUser->isSuper()) {
            return $modules;
        }

        $permissions = array_fill_keys($currentUser->getPermissions(), true);

        return array_values(array_filter($modules, static function (array $module) use ($permissions): bool {
            $code = (string)($module['code'] ?? '');
            return $code === '' || isset($permissions['*']) || isset($permissions[$code]);
        }));
    }

    /**
     * 判断当前用户是否具备指定权限码。
     */
    private function canAccessCode(string $code): bool
    {
        $currentUser = user();
        if (!$currentUser) {
            return false;
        }

        if ($currentUser->isSuper()) {
            return true;
        }

        $permissions = array_fill_keys($currentUser->getPermissions(), true);
        return isset($permissions['*']) || isset($permissions[$code]);
    }

    /**
     * 系统看板中的在线用户也需要遵守用户数据范围，避免统计接口泄露全局在线人员。
     *
     * @return array{
     *   user_count:int,
     *   session_count:int,
     *   users:array<int, array<string, mixed>>
     * }
     */
    private function getScopedOnlineSummary(int $limit = 10): array
    {
        $currentUser = user();
        if (!$currentUser) {
            return [
                'user_count' => 0,
                'session_count' => 0,
                'users' => [],
            ];
        }

        if ($currentUser->isSuper()) {
            return $this->onlineUsers->getSummary($limit);
        }

        $summary = $this->onlineUsers->getSummary(0);
        $entries = array_values(array_filter(
            (array)($summary['users'] ?? []),
            static fn (mixed $entry): bool => is_array($entry)
        ));

        $userIds = array_values(array_unique(array_filter(
            array_map(static fn (array $entry): int => (int)($entry['user_id'] ?? 0), $entries),
            static fn (int $userId): bool => $userId > 0
        )));

        if ($userIds === []) {
            return [
                'user_count' => 0,
                'session_count' => 0,
                'users' => [],
            ];
        }

        $allowedUserIds = array_fill_keys($this->userMapper->filterScopedUserIds($userIds, false), true);
        $scopedEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => isset($allowedUserIds[(int)($entry['user_id'] ?? 0)])
        ));

        $uniqueUserIds = [];
        foreach ($scopedEntries as $entry) {
            $userId = (int)($entry['user_id'] ?? 0);
            if ($userId > 0) {
                $uniqueUserIds[$userId] = true;
            }
        }

        return [
            'user_count' => count($uniqueUserIds),
            'session_count' => count($scopedEntries),
            'users' => $limit > 0 ? array_slice($scopedEntries, 0, $limit) : $scopedEntries,
        ];
    }
}
