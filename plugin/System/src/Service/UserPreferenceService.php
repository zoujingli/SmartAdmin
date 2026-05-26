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

use Library\Exception\ErrorResponseException;

/**
 * System 用户界面偏好过滤服务。
 *
 * 只负责把前端传入的 UI 偏好裁剪为白名单结构，不读取请求态或保存用户模型，避免单例服务持有可变上下文。
 */
final class UserPreferenceService
{
    private const UI_PREFERENCE_SCHEMA = [
        'app' => [
            'locale' => 'locale',
            'dynamicTitle' => 'bool',
            'layout' => 'string',
            'colorGrayMode' => 'bool',
            'colorWeakMode' => 'bool',
            'contentCompact' => 'string',
            'watermark' => 'bool',
            'watermarkContent' => 'string',
            'enableCheckUpdates' => 'bool',
            'enableStickyPreferencesNavigationBar' => 'bool',
            'preferencesButtonPosition' => 'string',
        ],
        'breadcrumb' => [
            'enable' => 'bool',
            'hideOnlyOne' => 'bool',
            'showHome' => 'bool',
            'showIcon' => 'bool',
            'styleType' => 'string',
        ],
        'copyright' => [
            'companyName' => 'string',
            'companySiteLink' => 'string',
            'date' => 'string',
            'enable' => 'bool',
            'icp' => 'string',
            'icpLink' => 'string',
        ],
        'footer' => [
            'enable' => 'bool',
            'fixed' => 'bool',
        ],
        'header' => [
            'enable' => 'bool',
            'menuAlign' => 'string',
            'mode' => 'string',
        ],
        'navigation' => [
            'accordion' => 'bool',
            'split' => 'bool',
            'styleType' => 'string',
        ],
        'shortcutKeys' => [
            'enable' => 'bool',
            'globalLockScreen' => 'bool',
            'globalLogout' => 'bool',
            'globalSearch' => 'bool',
        ],
        'sidebar' => [
            'autoActivateChild' => 'bool',
            'collapsed' => 'bool',
            'collapsedButton' => 'bool',
            'collapsedShowTitle' => 'bool',
            'enable' => 'bool',
            'expandOnHover' => 'bool',
            'fixedButton' => 'bool',
            'width' => 'int',
        ],
        'tabbar' => [
            'draggable' => 'bool',
            'enable' => 'bool',
            'maxCount' => 'int',
            'middleClickToClose' => 'bool',
            'persist' => 'bool',
            'showIcon' => 'bool',
            'showMaximize' => 'bool',
            'showMore' => 'bool',
            'styleType' => 'string',
            'visitHistory' => 'bool',
            'wheelable' => 'bool',
        ],
        'theme' => [
            'builtinType' => 'string',
            'colorPrimary' => 'string',
            'fontSize' => 'int',
            'mode' => 'string',
            'radius' => 'string',
            'semiDarkHeader' => 'bool',
            'semiDarkSidebar' => 'bool',
        ],
        'transition' => [
            'enable' => 'bool',
            'loading' => 'bool',
            'name' => 'string',
            'progress' => 'bool',
        ],
        'widget' => [
            'fullscreen' => 'bool',
            'globalSearch' => 'bool',
            'languageToggle' => 'bool',
            'lockScreen' => 'bool',
            'notification' => 'bool',
            'refresh' => 'bool',
            'sidebarToggle' => 'bool',
            'themeToggle' => 'bool',
        ],
    ];

    /**
     * 合并用户 extra 中的 UI 偏好；空偏好表示清空旧配置而不是保留历史值。
     *
     * @param array<int|string, mixed> $extra
     * @param array<int|string, mixed> $preferences
     * @return array<int|string, mixed>
     */
    public function mergeUserExtraUiPreferences(array $extra, array $preferences): array
    {
        if ($preferences === []) {
            unset($extra['ui_preferences']);

            return $extra;
        }

        $extra['ui_preferences'] = $preferences;

        return $extra;
    }

    /**
     * 根据固定白名单过滤前端偏好结构，未知 key 或类型不匹配的值直接丢弃。
     *
     * @return array<int|string, mixed>
     */
    public function normalizeUiPreferencesInput(mixed $input): array
    {
        if (!is_array($input)) {
            throw new ErrorResponseException('界面配置格式无效');
        }

        return $this->filterUiPreferencesBySchema($input, self::UI_PREFERENCE_SCHEMA);
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param array<string, array|string> $schema
     * @return array<int|string, mixed>
     */
    private function filterUiPreferencesBySchema(array $payload, array $schema): array
    {
        $result = [];

        foreach ($schema as $key => $definition) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_array($definition)) {
                if (!is_array($value)) {
                    continue;
                }

                $nested = $this->filterUiPreferencesBySchema($value, $definition);
                if ($nested !== []) {
                    $result[$key] = $nested;
                }

                continue;
            }

            $normalized = $this->filterUiPreferenceLeaf($value, $definition);
            if ($normalized !== null) {
                $result[$key] = $normalized;
            }
        }

        return $result;
    }

    /**
     * 按叶子节点类型过滤 UI 偏好值，避免前端提交任意对象污染用户 extra JSON。
     */
    private function filterUiPreferenceLeaf(mixed $value, string $type): bool|int|string|null
    {
        return match ($type) {
            'bool' => is_bool($value) ? $value : null,
            'int' => (is_int($value) || (is_float($value) && is_finite($value))) ? (int)$value : null,
            'locale' => is_string($value) && in_array($value, ['zh-CN', 'zh-TW', 'en-US'], true) ? $value : null,
            'string' => is_string($value) ? $value : null,
            default => null,
        };
    }
}
