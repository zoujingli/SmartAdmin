<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Support;

/**
 * 官网开放接口权限范围。
 *
 * scopes 绑定到第三方应用而不是请求参数；开放接口验签后按当前路由声明的 scope 检查，
 * 防止一个只用于读取导航的应用被拿去提交线索或读取内容详情。
 */
final class WebsiteOpenApiScope
{
    public const SITE_READ = 'site:read';

    public const NAV_READ = 'nav:read';

    public const CHANNEL_READ = 'channel:read';

    public const PAGE_READ = 'page:read';

    public const CONTENT_READ = 'content:read';

    public const BLOCK_READ = 'block:read';

    public const LEAD_CREATE = 'lead:create';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::SITE_READ,
            self::NAV_READ,
            self::CHANNEL_READ,
            self::PAGE_READ,
            self::CONTENT_READ,
            self::BLOCK_READ,
            self::LEAD_CREATE,
        ];
    }

    /**
     * 新建第三方应用默认只开放读取类接口；线索提交属于写入动作，必须后台显式勾选。
     *
     * @return array<int, string>
     */
    public static function defaultReadScopes(): array
    {
        return [
            self::SITE_READ,
            self::NAV_READ,
            self::CHANNEL_READ,
            self::PAGE_READ,
            self::CONTENT_READ,
            self::BLOCK_READ,
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public static function options(): array
    {
        return array_map(static fn (string $scope): array => [
            'label' => self::label($scope),
            'value' => $scope,
        ], self::all());
    }

    public static function label(string $scope): string
    {
        return [
            self::SITE_READ => '站点资料读取',
            self::NAV_READ => '导航读取',
            self::CHANNEL_READ => '栏目读取',
            self::PAGE_READ => '页面读取',
            self::CONTENT_READ => '内容读取',
            self::BLOCK_READ => '区块读取',
            self::LEAD_CREATE => '线索提交',
        ][$scope] ?? $scope;
    }

    /**
     * @return array<int, string>
     */
    public static function normalize(mixed $value, ?array $default = null): array
    {
        $items = array_map('strtolower', WebsiteData::stringList($value));
        if ($items === [] && $default !== null) {
            $items = $default;
        }

        return array_values(array_intersect(self::all(), array_values(array_unique($items))));
    }

    public static function allows(mixed $scopes, string $scope): bool
    {
        return in_array($scope, self::normalize($scopes), true);
    }
}
