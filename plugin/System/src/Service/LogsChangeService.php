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

use Library\CoreService;
use System\Mapper\LogsActionMapper;
use System\Mapper\LogsChangeMapper;

/**
 * 变更日志服务
 *
 * 变更日志是操作日志的业务明细视图，支持独立检索，同时详情会带出可访问的操作日志基础信息。
 */
final class LogsChangeService extends CoreService
{
    /**
     * @param LogsChangeMapper $mapper 变更日志数据访问层
     * @param LogsActionMapper $actionMapper 操作日志数据访问层
     */
    public function __construct(
        protected LogsChangeMapper $mapper,
        protected LogsActionMapper $actionMapper
    ) {}

    /**
     * 获取变更日志统计信息；统计口径与列表筛选保持一致。
     *
     * @return array<string, mixed>
     */
    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    /**
     * 获取变更日志详情，并在同一数据范围内补齐关联操作日志。
     *
     * @return array<string, mixed>
     */
    public function getDetail(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        $change = $this->mapper->read($id);
        if (!$change) {
            return [];
        }

        $row = $change->toArray();
        $actionId = (int)($row['action_id'] ?? 0);
        $action = $actionId > 0 ? $this->actionMapper->read($actionId) : null;
        $row['action'] = $action?->toArray();

        return $row;
    }
}
