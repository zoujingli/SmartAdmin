<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Mapper;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Library\CoreMapper;
use System\Model\SystemData;
use System\Model\SystemDept;
use System\Model\SystemFile;
use System\Model\SystemMenu;
use System\Model\SystemNode;
use System\Model\SystemLogsAction;
use System\Model\SystemPost;
use System\Model\SystemRole;
use System\Model\SystemUser;
use System\Model\SystemTenant;

/**
 * 系统数据数据访问层
 */
final class DataMapper extends CoreMapper
{
    /**
     * @param string $model 系统数据模型类
     */
    public function __construct(
        protected string $model = SystemData::class
    ) {}

    /**
     * 获取用户数量.
     */
    public function getUserCount(): int
    {
        return (int)$this->scopedQuery(SystemUser::class)->count();
    }

    /**
     * 获取角色数量.
     */
    public function getRoleCount(): int
    {
        return (int)$this->scopedQuery(SystemRole::class)->count();
    }

    /**
     * 获取菜单数量.
     */
    public function getMenuCount(): int
    {
        return (int)$this->scopedQuery(SystemMenu::class)->count();
    }

    /**
     * 获取部门数量.
     */
    public function getDeptCount(): int
    {
        return (int)$this->scopedQuery(SystemDept::class, 'created_by', 'id')->count();
    }

    /**
     * 获取岗位数量.
     */
    public function getPostCount(): int
    {
        return (int)$this->scopedQuery(SystemPost::class)->count();
    }

    /**
     * 获取权限节点数量.
     */
    public function getNodeCount(): int
    {
        return (int)$this->scopedQuery(SystemNode::class)->count();
    }

    /**
     * 获取操作日志数量.
     */
    public function getLogCount(): int
    {
        return (int)$this->scopedQuery(SystemLogsAction::class)->count();
    }

    /**
     * 获取今日操作日志数量.
     */
    public function getTodayLogs(): int
    {
        return (int)$this->scopedQuery(SystemLogsAction::class)
            ->whereDate('created_at', date('Y-m-d'))
            ->count();
    }

    /**
     * 获取今日服务端错误操作日志数量（5xx）。
     */
    public function getTodayServerErrorLogs(): int
    {
        return (int)$this->scopedQuery(SystemLogsAction::class)
            ->whereDate('created_at', date('Y-m-d'))
            ->where('response_code', 'like', '5%')
            ->count();
    }

    /**
     * 获取今日登录失败操作日志数量。
     */
    public function getTodayFailedLoginLogs(): int
    {
        return (int)$this->scopedQuery(SystemLogsAction::class)
            ->whereDate('created_at', date('Y-m-d'))
            ->where('name', '用户登录')
            ->where('response_code', '!=', '200')
            ->count();
    }

    /**
     * 获取已禁用租户数量。
     */
    public function getDisabledTenantCount(): int
    {
        return (int)$this->scopedQuery(SystemTenant::class)
            ->where('status', 0)
            ->count();
    }

    /**
     * 获取即将到期租户数量。
     *
     * @param int $days 到期窗口天数，最小按 1 天处理
     */
    public function getExpiringTenantCount(int $days = 7): int
    {
        return (int)$this->scopedQuery(SystemTenant::class)
            ->whereNotNull('expired_at')
            ->where('expired_at', '>=', date('Y-m-d H:i:s'))
            ->where('expired_at', '<=', date('Y-m-d H:i:s', strtotime(sprintf('+%d days', max(1, $days)))))
            ->count();
    }

    /**
     * 获取重复文件分组数量（按 driver + hash）。
     */
    public function getDuplicateFileGroupCount(): int
    {
        return (int)$this->scopedQuery(SystemFile::class)
            ->whereNotNull('hash')
            ->groupBy('driver', 'hash')
            ->havingRaw('COUNT(*) > 1')
            ->get(['driver', 'hash'])
            ->count();
    }

    /**
     * 获取最近注册用户.
     */
    public function getRecentUsers(): array
    {
        return $this->scopedQuery(SystemUser::class)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'username', 'nickname', 'created_at'])
            ->toArray();
    }

    /**
     * 获取系统配置.
     */
    public function getConfig(): array
    {
        $configs = $this->model::where('name', 'like', 'config_%')
            ->where('name', '!=', 'config_upload')
            ->get()
            ->pluck('value', 'name')
            ->toArray();

        $result = [];
        foreach ($configs as $name => $value) {
            $key = str_replace('config_', '', (string)$name);
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * 读取指定配置模型，供 Service 在 JSON 配置写入后追加可读变更日志。
     */
    public function findConfigRecord(string $key): ?SystemData
    {
        $name = str_starts_with($key, 'config_') ? $key : 'config_' . $key;
        $record = $this->model::query()->where('name', $name)->first();

        return $record instanceof SystemData ? $record : null;
    }

    /**
     * 更新系统配置.
     */
    public function updateConfig(array $configs): bool
    {
        foreach ($configs as $key => $value) {
            $name = 'config_' . $key;
            $this->model::updateOrCreate(
                ['name' => $name],
                ['value' => $value, 'remark' => '系统配置']
            );
        }
        return true;
    }

    /**
     * 过滤查询条件.
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name,remark')
            ->dateBetween('created_at')
            ->getQuery();
    }

    /**
     * 创建带数据范围限制的模型查询。
     *
     * @param class-string<Model> $modelClass
     */
    private function scopedQuery(string $modelClass, string $userField = 'created_by', ?string $deptField = null): Builder
    {
        return $this->applyDataScope($modelClass::query(), $userField, $deptField);
    }
}
