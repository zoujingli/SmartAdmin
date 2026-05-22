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

use Library\CoreService;
use Library\Support\ModelChangeLog;
use System\Mapper\DataMapper;
use System\Support\SystemAppMeta;

/**
 * 系统参数服务。
 *
 * 系统参数只维护公开品牌、登录展示与版权备案信息，统一写入 config_app_meta，避免参数页越权修改其它 config_* 项。
 */
final class SettingService extends CoreService
{
    /**
     * @param DataMapper $mapper 系统配置数据访问层
     */
    public function __construct(
        protected DataMapper $mapper
    ) {}

    /**
     * 获取系统参数表单数据。
     *
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return $this->normalizeAppMeta($this->readAppMeta(), true);
    }

    /**
     * 保存系统参数。
     *
     * 仅接受白名单字段，Logo 只保存文件 ID 与访问 URL；其它上传文件元数据仍由文件模块维护。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateInfo(array $params): array
    {
        $current = $this->readAppMeta();
        $payload = $this->normalizeAppMeta(array_merge($this->getInfo(), $params), false);

        $this->mapper->updateConfig([
            'app_meta' => array_merge($current, $payload),
        ]);
        $this->recordAppMetaChange($current, array_merge($current, $payload));

        return $this->getInfo();
    }

    /**
     * 读取当前 app_meta 配置，兼容历史独立 config_* 字段。
     *
     * @return array<string, mixed>
     */
    private function readAppMeta(): array
    {
        $config = $this->mapper->getConfig();
        // app_meta 可能来自通用配置页或历史脚本，先兼容 JSON 字符串及二次嵌套结构再与默认值合并。
        $meta = SystemAppMeta::normalize($config['app_meta'] ?? []);

        return SystemAppMeta::mergeDefaults($meta, $config);
    }

    /**
     * 归一化系统参数字段。
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function normalizeAppMeta(array $meta, bool $withDefaults): array
    {
        $source = $withDefaults ? array_merge(SystemAppMeta::defaults(), $meta) : $meta;
        // 系统参数是配置型业务数据，先由 _vali 过滤白名单并校验数值字段，再按界面展示约束做归一化。
        $source = _vali([
            // ValidateHelper 只会把带规则的字段放入 validated 结果；配置字段使用 default 规则同时完成白名单过滤与缺省兜底。
            'app_name.default' => $source['app_name'] ?? '',
            'app_version.default' => $source['app_version'] ?? '',
            'app_description.default' => $source['app_description'] ?? '',
            'login_title.default' => $source['login_title'] ?? '',
            'login_description.default' => $source['login_description'] ?? '',
            'logo_url.default' => $source['logo_url'] ?? '',
            'logo_file_id.default' => $source['logo_file_id'] ?? 0,
            'copyright_enable.default' => $source['copyright_enable'] ?? true,
            'company_name.default' => $source['company_name'] ?? '',
            'company_site_link.default' => $source['company_site_link'] ?? '',
            'copyright_date.default' => $source['copyright_date'] ?? '',
            'icp.default' => $source['icp'] ?? '',
            'icp_link.default' => $source['icp_link'] ?? '',
            'logo_file_id.integer' => 'Logo 文件 ID 必须为数字',
            'logo_file_id.min:0' => 'Logo 文件 ID 不能小于 0',
        ], $source);

        return [
            'app_name' => $this->stringValue($source['app_name'] ?? '', 80),
            'app_version' => $this->stringValue($source['app_version'] ?? '', 30),
            'app_description' => $this->stringValue($source['app_description'] ?? '', 255),
            'login_title' => $this->stringValue($source['login_title'] ?? '', 120),
            'login_description' => $this->stringValue($source['login_description'] ?? '', 255),
            'logo_url' => $this->stringValue($source['logo_url'] ?? '', 500),
            'logo_file_id' => max(0, (int)($source['logo_file_id'] ?? 0)),
            'copyright_enable' => $this->boolValue($source['copyright_enable'] ?? true),
            'company_name' => $this->stringValue($source['company_name'] ?? '', 120),
            'company_site_link' => $this->stringValue($source['company_site_link'] ?? '', 500),
            'copyright_date' => $this->stringValue($source['copyright_date'] ?? '', 20),
            'icp' => $this->stringValue($source['icp'] ?? '', 120),
            'icp_link' => $this->stringValue($source['icp_link'] ?? '', 500),
        ];
    }

    /**
     * 规范化字符串并限制长度，避免配置字段撑开登录页与标题区域。
     */
    private function stringValue(mixed $value, int $limit): string
    {
        $string = trim((string)$value);

        return function_exists('mb_substr') ? mb_substr($string, 0, $limit) : substr($string, 0, $limit);
    }

    /**
     * 兼容表单布尔、数字和字符串提交。
     */
    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return false;
    }

    /**
     * 系统参数集中存储在 config_app_meta.value，数组内容需手动记录为配置变更。
     *
     * @param array<string, mixed> $oldValue
     * @param array<string, mixed> $newValue
     */
    private function recordAppMetaChange(array $oldValue, array $newValue): void
    {
        $record = $this->mapper->findConfigRecord(SystemAppMeta::CONFIG_NAME);
        if (!$record) {
            return;
        }

        ModelChangeLog::recordFields($record, 'updated', [[
            'field' => 'value',
            'label' => '配置内容',
            'old' => $oldValue,
            'new' => $newValue,
        ]]);
    }
}
