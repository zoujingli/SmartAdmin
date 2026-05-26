<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatService\Model\WechatServiceGateway;

final class WechatServiceGatewayMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatServiceGateway::class
    ) {}

    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    public function findByClientKey(string $clientKey): ?WechatServiceGateway
    {
        /* @var null|WechatServiceGateway $credential */
        return $this->model::query()->where('client_key', $clientKey)->first();
    }

    /**
     * 检查调用 Key 是否已存在，包含软删除记录，避免最终触发数据库唯一索引异常。
     */
    public function existsByClientKey(string $clientKey, int $ignoreId = 0): bool
    {
        $query = $this->model::withTrashed()->where('client_key', $clientKey);
        if ($ignoreId > 0) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function incrementTotal(string $clientKey): void
    {
        $this->model::query()->where('client_key', $clientKey)->increment('total');
    }

    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('client_key,name')
            ->equal('status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
