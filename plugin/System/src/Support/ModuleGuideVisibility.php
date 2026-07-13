<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support;

use Library\Support\PluginManifestRegistry;

/**
 * 模块引导入口运行时展示配置。
 *
 * 插件清单 enabled=false 是硬禁用，系统参数只能控制已声明启用的入口，避免后台误开启尚未就绪的公开入口。
 */
final class ModuleGuideVisibility
{
    /**
     * 返回设置页可维护的入口展示信息。
     *
     * @return array<int, array{code:string,name:string,description:string,icon:string}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (PluginManifestRegistry::guideEntries() as $entry) {
            if (!(bool)($entry['enabled'] ?? true)) {
                continue;
            }

            $options[] = [
                'code' => (string)($entry['code'] ?? ''),
                'name' => (string)($entry['name'] ?? ''),
                'description' => (string)($entry['description'] ?? ''),
                'icon' => (string)($entry['icon'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * 将运行时配置归一化为完整的入口可见性映射。
     *
     * 未配置入口沿用插件清单默认启用状态；未知 code 和清单硬禁用入口不会进入持久化结果。
     *
     * @return array<string, bool>
     */
    public static function normalize(mixed $visibility): array
    {
        $source = is_array($visibility) && ($visibility === [] || !array_is_list($visibility))
            ? $visibility
            : [];
        $normalized = [];
        foreach (PluginManifestRegistry::guideEntries() as $entry) {
            if (!(bool)($entry['enabled'] ?? true)) {
                continue;
            }

            $code = (string)($entry['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $normalized[$code] = array_key_exists($code, $source)
                ? self::boolValue($source[$code], true)
                : true;
        }

        return $normalized;
    }

    /**
     * 返回公开引导页最终可展示的入口，隐藏配置不会随公开接口输出。
     *
     * @return array<int, array{plugin:string,code:string,name:string,description:string,icon:string,home_path:string,login_path:string,sort:int,enabled:bool}>
     */
    public static function visibleEntries(mixed $visibility): array
    {
        $normalized = self::normalize($visibility);

        return array_values(array_filter(
            PluginManifestRegistry::guideEntries(),
            static function (array $entry) use ($normalized): bool {
                $code = (string)($entry['code'] ?? '');

                return (bool)($entry['enabled'] ?? true) && ($normalized[$code] ?? false);
            }
        ));
    }

    /**
     * 兼容 Switch 常见的布尔、0/1 与字符串值；异常值保持清单默认启用，避免错误输入意外隐藏入口。
     */
    private static function boolValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $default;
    }
}
