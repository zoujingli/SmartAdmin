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

use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;
use Library\CoreService;
use Library\Helper\RequestHelper;
use Library\Interfaces\OperateLogWriterInterface;
use System\Mapper\LogsActionMapper;
use System\Mapper\LogsChangeMapper;

/**
 * 操作日志服务
 * 处理日志记录、查询、删除等业务逻辑.
 */
final class LogsActionService extends CoreService implements OperateLogWriterInterface
{
    /**
     * @param LogsActionMapper $mapper 日志数据访问层
     * @param LogsChangeMapper $changeMapper 变更日志数据访问层
     */
    public function __construct(
        protected LogsActionMapper $mapper,
        protected LogsChangeMapper $changeMapper
    ) {}

    /**
     * 写入操作日志（供 Library\LoggerListener 调用）.
     */
    public function write(array $data): void
    {
        $changePayload = is_array($data['change_payload'] ?? null) ? $data['change_payload'] : null;
        unset($data['change_payload']);

        Db::beginTransaction();
        try {
            $action = $this->create(self::fillIpLocation($data));
            if ($action instanceof Model && $changePayload !== null) {
                $this->writeChanges((int)$action->getKey(), $action->toArray(), $changePayload);
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    /**
     * 清空日志.
     */
    public function clear(?int $days = null): int
    {
        Db::beginTransaction();
        try {
            $date = $days === null || $days <= 0 ? null : date('Y-m-d H:i:s', strtotime("-{$days} days"));
            // 清空操作日志时必须用同一筛选范围先同步软删关联变更，避免详情侧留下孤立 change。
            $this->changeMapper->softDeleteByActionQuery($this->mapper->makeClearQuery($date)->clone());
            $deleted = $this->mapper->clear($date);
            Db::commit();

            return $deleted;
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    /**
     * 删除日志.
     */
    public function delete(array $ids): bool
    {
        return $this->transactionalBool(function () use ($ids): bool {
            $deleted = $this->mapper->delete($ids);
            if ($deleted) {
                $this->changeMapper->softDeleteByActionIds($ids);
            }

            return $deleted;
        });
    }

    /**
     * 彻底删除操作日志及关联变更。
     */
    public function delreal(array $ids): bool
    {
        return $this->transactionalBool(function () use ($ids): bool {
            $deleted = $this->mapper->delreal($ids);
            if ($deleted) {
                $this->changeMapper->forceDeleteByActionIds($ids);
            }

            return $deleted;
        });
    }

    /**
     * 恢复操作日志及关联变更。
     */
    public function recovery(array $ids): bool
    {
        return $this->transactionalBool(function () use ($ids): bool {
            $restored = $this->mapper->recovery($ids);
            if ($restored) {
                $this->changeMapper->restoreByActionIds($ids);
            }

            return $restored;
        });
    }

    /**
     * 获取指定操作日志下的变更明细。
     */
    public function getChanges(int $actionId): array
    {
        if ($actionId <= 0 || !$this->mapper->read($actionId)) {
            return [];
        }

        return $this->changeMapper->getByActionId($actionId);
    }

    /**
     * 获取日志统计信息.
     */
    public function getStatistics(array $params = []): array
    {
        $query = $this->mapper->makeLogQuery($params);

        // 基础统计
        $total = (int)$query->clone()->count();

        // 按响应码统计
        $responseCodeStats = self::normalizeCountMap($query->clone()
            ->selectRaw('response_code, COUNT(*) as aggregate')
            ->groupBy('response_code')
            ->pluck('aggregate', 'response_code')
            ->toArray());
        $warningCount = self::sumClientErrorCountByResponseCode($responseCodeStats);

        // 按用户统计
        $userStats = self::normalizeCountMap($query->clone()
            ->selectRaw('username, COUNT(*) as aggregate')
            ->groupBy('username')
            ->orderBy('aggregate', 'desc')
            ->limit(10)
            ->pluck('aggregate', 'username')
            ->toArray());

        // 按业务名称统计
        $businessStats = self::normalizeCountMap($query->clone()
            ->selectRaw('name, COUNT(*) as aggregate')
            ->groupBy('name')
            ->orderBy('aggregate', 'desc')
            ->limit(10)
            ->pluck('aggregate', 'name')
            ->toArray());

        // 时间统计
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $thisWeek = date('Y-m-d', strtotime('monday this week'));
        $thisMonth = date('Y-m-01');

        $timeStats = [
            'today' => (int)$query->clone()->whereDate('created_at', $today)->count(),
            'yesterday' => (int)$query->clone()->whereDate('created_at', $yesterday)->count(),
            'this_week' => (int)$query->clone()->where('created_at', '>=', $thisWeek)->count(),
            'this_month' => (int)$query->clone()->where('created_at', '>=', $thisMonth)->count(),
        ];

        $successCount = (int)($responseCodeStats['200'] ?? 0);

        return [
            'total' => $total,
            'today' => (int)$timeStats['today'],
            'success_count' => $successCount,
            'warning_count' => $warningCount,
            'error_count' => max(0, $total - $successCount - $warningCount),
            'by_response_code' => $responseCodeStats,
            'by_user' => $userStats,
            'by_business' => $businessStats,
            'by_time' => $timeStats,
        ];
    }

    /**
     * 日志写入入口只接受日志模型白名单字段；日志内容字段保留原始字符串，避免二次 JSON 处理破坏审计内容。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['username', 'method', 'router', 'name', 'remark', 'ip', 'ip_location', 'os', 'browser', 'response_code'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $data = _vali([
            'tenant_id',
            'username',
            'method',
            'router',
            'name',
            'remark',
            'ip',
            'ip_location',
            'os',
            'browser',
            'request_data',
            'response_code',
            'response_data',
            'created_by',
            'updated_by',
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'username.max:20' => '操作用户最多 20 位',
            'method.max:20' => '请求方法最多 20 位',
            'router.max:500' => '请求路由最多 500 位',
            'name.max:30' => '操作名称最多 30 位',
            'remark.max:200' => '日志备注最多 200 位',
            'ip.max:200' => 'IP 地址最多 200 位',
            'ip_location.max:200' => 'IP 归属地最多 200 位',
            'os.max:200' => '操作系统最多 200 位',
            'browser.max:200' => '浏览器最多 200 位',
            'response_code.max:5' => '响应码最多 5 位',
            'created_by.integer' => '创建者必须为数字',
            'created_by.min:0' => '创建者不能小于 0',
            'updated_by.integer' => '更新者必须为数字',
            'updated_by.min:0' => '更新者不能小于 0',
        ], $data);

        foreach (['tenant_id', 'created_by', 'updated_by'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    /**
     * 获取日志分析报告.
     */
    public function getAnalysisReport(array $params = []): array
    {
        $query = $this->mapper->makeLogQuery($params);

        // 按小时统计
        $hourlyStats = self::fillNumberRange(self::normalizeCountMap($query->clone()
            ->selectRaw('HOUR(created_at) as stat_key, COUNT(*) as aggregate')
            ->groupBy('stat_key')
            ->orderBy('stat_key')
            ->pluck('aggregate', 'stat_key')
            ->toArray()), 0, 23);

        // 按星期统计
        $weeklyStats = self::fillNumberRange(self::normalizeCountMap($query->clone()
            ->selectRaw('WEEKDAY(created_at) as stat_key, COUNT(*) as aggregate')
            ->groupBy('stat_key')
            ->orderBy('stat_key')
            ->pluck('aggregate', 'stat_key')
            ->toArray()), 0, 6);

        // 错误日志分析
        $errorLogs = $query->clone()
            ->where('response_code', '!=', '200')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();

        return [
            'hourly_stats' => $hourlyStats,
            'weekly_stats' => $weeklyStats,
            'error_logs' => $errorLogs,
        ];
    }

    /**
     * 日志近实时窗口：计数与最近活动（与列表筛选条件一致）.
     */
    public function getRealtimeMetrics(array $params = []): array
    {
        $query = $this->mapper->makeLogQuery($params);

        $now = time();
        $t1 = date('Y-m-d H:i:s', $now - 60);
        $t5 = date('Y-m-d H:i:s', $now - 300);
        $t15 = date('Y-m-d H:i:s', $now - 900);

        $lastActivityAt = $query->clone()->max('created_at');
        $lastActivityAt = $lastActivityAt !== null ? (string)$lastActivityAt : null;

        $count1m = (int)$query->clone()->where('created_at', '>=', $t1)->count();
        $count5m = (int)$query->clone()->where('created_at', '>=', $t5)->count();
        $count15m = (int)$query->clone()->where('created_at', '>=', $t15)->count();

        $q5 = $query->clone()->where('created_at', '>=', $t5);
        $errors5m = (int)$q5->clone()->where('response_code', '!=', '200')->count();

        $byCode5m = self::normalizeCountMap($q5->clone()
            ->selectRaw('response_code, COUNT(*) as aggregate')
            ->groupBy('response_code')
            ->pluck('aggregate', 'response_code')
            ->toArray());

        $perMin5m = round($count5m / 5, 2);

        return [
            'server_time' => date('Y-m-d H:i:s'),
            'last_activity_at' => $lastActivityAt,
            'count_last_1m' => $count1m,
            'count_last_5m' => $count5m,
            'count_last_15m' => $count15m,
            'errors_last_5m' => $errors5m,
            'events_per_minute_5m' => $perMin5m,
            'by_response_code_last_5m' => $byCode5m,
        ];
    }

    /**
     * 按响应码键累加 4xx 条数（须遍历键；array_reduce 回调拿不到键）.
     *
     * @param array<int|string, mixed> $countsByResponseCode
     */
    private static function sumClientErrorCountByResponseCode(array $countsByResponseCode): int
    {
        $sum = 0;
        foreach ($countsByResponseCode as $code => $count) {
            if (str_starts_with((string)$code, '4')) {
                $sum += (int)$count;
            }
        }

        return $sum;
    }

    /**
     * 统计接口统一输出 string=>int 的稳定结构，空分组统一归入“未记录”，避免前端遇到 null/数字字符串时报错。
     *
     * @param array<int|string, mixed> $counts
     * @return array<string, int>
     */
    private static function normalizeCountMap(array $counts): array
    {
        $result = [];
        foreach ($counts as $key => $count) {
            $name = trim((string)$key);
            $result[$name === '' ? '未记录' : $name] = (int)$count;
        }

        return $result;
    }

    /**
     * 小时和星期图表必须补齐完整下标，否则空数据日期下前端表格/图表会出现缺项。
     *
     * @param array<int|string, mixed> $counts
     * @return array<string, int>
     */
    private static function fillNumberRange(array $counts, int $start, int $end): array
    {
        $result = [];
        for ($index = $start; $index <= $end; ++$index) {
            $result[(string)$index] = (int)($counts[(string)$index] ?? $counts[$index] ?? 0);
        }

        return $result;
    }

    /**
     * 将采集到的变更分段拆成多条 system_logs_change；每个分段对应一个业务对象的一次变化。
     *
     * @param array<string, mixed> $action 已写入的操作日志行
     * @param array<string, mixed> $changeData ModelChangeFormatter 生成的临时载荷
     */
    private function writeChanges(int $actionId, array $action, array $changeData): void
    {
        if ($actionId <= 0 || !is_array($changeData['segments'] ?? null)) {
            return;
        }

        foreach ($changeData['segments'] as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $row = self::buildChangeRow($actionId, $action, $segment);
            if ($row !== null) {
                $this->changeMapper->create($row);
            }
        }
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $segment
     * @return null|array<string, mixed>
     */
    private static function buildChangeRow(int $actionId, array $action, array $segment): ?array
    {
        if ($actionId <= 0) {
            return null;
        }

        $fields = $segment['fields'] ?? [];
        if (!is_array($fields) || $fields === []) {
            return null;
        }

        $changeValues = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row = [
            'tenant_id' => (int)($action['tenant_id'] ?? 0),
            'action_id' => $actionId,
            'username' => self::limitString($action['username'] ?? '', 20),
            'model' => self::limitString($segment['model'] ?? '', 100),
            'table_name' => self::limitString($segment['table_name'] ?? $segment['table'] ?? '', 100),
            'model_name' => self::limitString($segment['model_name'] ?? '', 100),
            'record_id' => self::limitString($segment['record_id'] ?? '', 100),
            'record_label' => self::limitString($segment['record_label'] ?? '', 200),
            'event' => self::limitString($segment['event'] ?? '', 50),
            'change_values' => is_string($changeValues) ? $changeValues : '[]',
            'change_remark' => self::buildChangeRemark($segment),
            'created_by' => (int)($action['created_by'] ?? 0),
            'updated_by' => (int)($action['updated_by'] ?? 0),
        ];

        // 变更明细来自模型审计规则，这里只做写库边界归一化，避免异常结构进入日志表。
        return $row;
    }

    /**
     * 单条变更日志保留完整对象前缀，方便脱离 action 也能直接阅读。
     *
     * @param array<string, mixed> $segment
     */
    private static function buildChangeRemark(array $segment): string
    {
        $prefix = self::scalarString($segment['model_name'] ?? $segment['model'] ?? '记录');
        $recordLabel = trim(self::scalarString($segment['record_label'] ?? ''));
        $recordId = trim(self::scalarString($segment['record_id'] ?? ''));
        if ($recordLabel !== '') {
            $prefix .= "({$recordLabel})";
        } elseif ($recordId !== '') {
            $prefix .= '#' . $recordId;
        }

        $text = trim(self::scalarString($segment['text'] ?? ''));

        return $text === '' ? $prefix : "{$prefix}：{$text}";
    }

    /**
     * 日志字段只接受标量展示值；数组等复杂值进入 change_values，不进入索引字段。
     */
    private static function scalarString(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }

        return '';
    }

    private static function limitString(mixed $value, int $length): string
    {
        $value = trim(self::scalarString($value));

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    private function transactionalBool(\Closure $callback): bool
    {
        Db::beginTransaction();
        try {
            $result = $callback();
            Db::commit();

            return $result;
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    /**
     * 直接写入操作日志时可能只传入 IP，这里统一补齐 ip2region 归属地且不覆盖调用方显式值。
     */
    private static function fillIpLocation(array $data): array
    {
        $ip = trim((string)($data['ip'] ?? ''));
        if ($ip === '') {
            return $data;
        }

        $location = trim((string)($data['ip_location'] ?? ''));
        if ($location !== '') {
            return $data;
        }

        $data['ip_location'] = RequestHelper::getIpLocationSimple($ip);

        return $data;
    }

}
