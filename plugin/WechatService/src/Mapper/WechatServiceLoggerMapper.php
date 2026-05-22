<?php

declare(strict_types=1);

namespace Plugin\WechatService\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatService\Model\WechatServiceLogger;

final class WechatServiceLoggerMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatServiceLogger::class
    ) {}

    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('event,appid,message')
            ->equal('status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
