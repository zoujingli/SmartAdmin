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
 * 官网模块结构化字段标准化工具。
 *
 * 官网接口需要大量 JSON 对象、列表和路由字段；集中处理可保证后台写入、公开读取和初始化数据
 * 对空值、非法 JSON、路由斜杠的语义一致，避免前端按不同资源写兼容分支。
 */
final class WebsiteData
{
    /**
     * 将 JSON 对象字段标准化为空对象或关联数组。
     *
     * @return array<string, mixed>
     */
    public static function object(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? [] : $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode(trim($value), true);
        return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }

    /**
     * 将标签、别名等列表字段标准化为去重字符串列表。
     *
     * @return array<int, string>
     */
    public static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode(trim($value), true);
            $value = is_array($decoded) ? $decoded : preg_split('/[,，\n]+/u', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $text = trim((string)$item);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return array_values(array_unique($items));
    }

    public static function encodeObject(mixed $value): string
    {
        $data = self::object($value);

        return $data === [] ? '{}' : (json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    public static function encodeList(mixed $value): string
    {
        $data = self::stringList($value);

        return $data === [] ? '[]' : (json_encode($data, JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    public static function route(string $route): string
    {
        $route = trim($route);
        if ($route === '' || $route === '/') {
            return '/';
        }

        $route = '/' . trim($route, '/') . '/';
        return (string)preg_replace('#/+#', '/', $route);
    }

    public static function nullableDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $text = is_scalar($value) ? trim((string)$value) : '';
        if ($text === '') {
            return null;
        }

        return strlen($text) === 10 ? $text . ' 00:00:00' : substr($text, 0, 19);
    }
}
