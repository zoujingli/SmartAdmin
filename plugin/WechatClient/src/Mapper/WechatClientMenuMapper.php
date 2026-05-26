<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientMenu;

/**
 * 微信菜单 Mapper。
 *
 * 提供本地菜单方案列表筛选能力。
 */
final class WechatClientMenuMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientMenu::class
    ) {}

    /**
     * 菜单方案列表不走操作范围过滤，仅按租户、账号和筛选条件控制。
     */
    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 禁用操作范围过滤，避免后台用户权限影响菜单方案管理。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理后台菜单方案列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name')
            ->equal('tenant_id,account_id,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
