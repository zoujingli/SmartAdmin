<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Support;

/**
 * 系统品牌与展示参数默认值。
 *
 * 该类同时服务安装初始化、系统参数表单和 UI 元信息兜底，避免默认品牌文案在多个入口出现不一致。
 */
final class SystemAppMeta
{
    public const CONFIG_NAME = 'config_app_meta';

    public const DEFAULT_APP_NAME = 'SmartAdmin';

    public const DEFAULT_APP_VERSION = '1.0.0';

    public const DEFAULT_LOGIN_TITLE = '新一代企业级数字化运营平台';

    public const DEFAULT_LOGIN_DESCRIPTION = '融合权限治理、组织协同、数据安全与全链路审计能力';

    /**
     * 获取系统参数默认值。
     *
     * @return array<string, mixed>
     */
    public static function defaults(?string $year = null): array
    {
        return [
            'app_name' => self::DEFAULT_APP_NAME,
            'app_version' => self::DEFAULT_APP_VERSION,
            'app_description' => '',
            'login_title' => self::DEFAULT_LOGIN_TITLE,
            'login_description' => self::DEFAULT_LOGIN_DESCRIPTION,
            'logo_url' => '',
            'logo_file_id' => 0,
            'copyright_enable' => true,
            'company_name' => self::DEFAULT_APP_NAME,
            'company_site_link' => '',
            'copyright_date' => $year ?: date('Y'),
            'icp' => '',
            'icp_link' => '',
        ];
    }

    /**
     * 将默认值、历史独立配置和当前 app_meta 合并为完整参数。
     *
     * 合并顺序为默认值 < 历史 config_* < 当前 app_meta；只跳过 null 与空字符串，避免旧库已有的有效值被默认值覆盖。
     *
     * @param mixed $meta
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function mergeDefaults(mixed $meta, array $config = []): array
    {
        $meta = self::normalize($meta);
        $merged = self::defaults();
        foreach (self::fallbackFromConfig($config) as $key => $value) {
            if (self::hasValue($value)) {
                $merged[$key] = $value;
            }
        }

        foreach ($meta as $key => $value) {
            if (self::hasValue($value)) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * 将 app_meta 的历史/异常形态统一转成字段对象。
     *
     * 部分环境可能通过通用配置页或外部脚本把 app_meta 保存为 JSON 字符串，甚至保存成
     * {"app_meta": {...}} 的二次嵌套结构；系统参数页只认最终字段对象，因此这里集中兼容，
     * 避免设置页、登录页和系统数据预览读取到默认值。
     *
     * @return array<string, mixed>
     */
    public static function normalize(mixed $meta): array
    {
        if (is_string($meta)) {
            $meta = trim($meta);
            if ($meta === '') {
                return [];
            }

            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($meta) || array_is_list($meta)) {
            return [];
        }

        $nested = $meta['app_meta'] ?? null;
        if ($nested !== null) {
            $nestedMeta = self::normalize($nested);
            if ($nestedMeta !== []) {
                unset($meta['app_meta']);
                // 二次嵌套时优先使用最外层显式字段，便于管理员局部覆盖修复后的配置。
                $meta = array_merge($nestedMeta, $meta);
            }
        }

        return $meta;
    }

    /**
     * 兼容历史 config_* 独立字段，迁移到统一 app_meta 前仍可读取旧值。
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function fallbackFromConfig(array $config): array
    {
        return [
            'app_name' => $config['app_name'] ?? $config['site_name'] ?? null,
            'app_version' => $config['app_version'] ?? $config['version'] ?? null,
            'app_description' => $config['app_description'] ?? null,
            'login_title' => $config['login_title'] ?? null,
            'login_description' => $config['login_description'] ?? null,
            'logo_url' => $config['logo_url'] ?? null,
            'logo_file_id' => $config['logo_file_id'] ?? null,
            'company_name' => $config['company_name'] ?? null,
            'company_site_link' => $config['company_site_link'] ?? null,
            'copyright_date' => $config['copyright_date'] ?? null,
            'copyright_enable' => $config['copyright_enable'] ?? null,
            'icp' => $config['icp'] ?? null,
            'icp_link' => $config['icp_link'] ?? null,
        ];
    }

    /**
     * 配置回填只把 null 和空字符串视为缺失；false、0 和空数组都可能是管理员的显式配置。
     */
    private static function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
