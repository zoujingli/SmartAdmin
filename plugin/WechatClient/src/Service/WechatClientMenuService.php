<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Service;

use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatClient\Mapper\WechatClientArticleMapper;
use Plugin\WechatClient\Mapper\WechatClientMediaMapper;
use Plugin\WechatClient\Mapper\WechatClientMenuMapper;
use Plugin\WechatClient\Mapper\WechatClientReplyRuleMapper;
use Plugin\WechatClient\Model\WechatClientArticle;
use Plugin\WechatClient\Model\WechatClientMedia;
use Plugin\WechatClient\Model\WechatClientMenu;
use Plugin\WechatClient\Model\WechatClientReplyRule;

/**
 * 微信菜单服务。
 *
 * 负责本地菜单可视化方案保存、资源按钮转换和发布到微信官方自定义菜单接口。
 */
final class WechatClientMenuService extends CoreService
{
    private const int MAX_TOP_BUTTONS = 3;

    private const int MAX_SUB_BUTTONS = 5;

    private const int TOP_NAME_WIDTH = 8;

    private const int SUB_NAME_WIDTH = 16;

    public function __construct(
        protected WechatClientMenuMapper $mapper,
        private readonly WechatClientAccountService $accounts,
        private readonly WechatClientMediaMapper $mediaMapper,
        private readonly WechatClientArticleMapper $articleMapper,
        private readonly WechatClientReplyRuleMapper $replyMapper,
    ) {}

    /**
     * 将本地菜单方案转换为微信官方 button 结构后发布；资源缺失会在发布前失败并给出明确提示。
     */
    public function publish(int $id): WechatClientMenu
    {
        $menu = $this->mapper->read($id);
        if (!$menu instanceof WechatClientMenu) {
            throw new ErrorResponseException('菜单方案不存在');
        }

        $account = $this->accounts->requireAccount((int)$menu->account_id);
        $buttons = $this->buildOfficialButtons((array)$menu->buttons, (int)$menu->account_id);
        $result = $this->accounts->officialRequest($account, 'cgi-bin/menu/create', [
            'button' => $buttons,
        ]);
        $this->mapper->update($menu, [
            'published_at' => date('Y-m-d H:i:s'),
            'publish_result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'success',
        ]);

        return $this->mapper->read($id);
    }

    /**
     * 保存菜单方案前校验基础字段，并保留可视化按钮结构或高级 JSON 结构。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        $hasButtons = array_key_exists('buttons', $data);
        $buttons = $data['buttons'] ?? [];
        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'account_id.integer' => '接口账号 ID 必须为数字',
            'account_id.min:1' => '接口账号不能为空',
            'name.filled' => '菜单方案名称不能为空',
            'name.max:120' => '菜单方案名称最多 120 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['account_id.required'] = '接口账号不能为空';
            $rules['name.required'] = '菜单方案名称不能为空';
            $rules['status.default'] = 1;
        }

        $data = _vali($rules, $data);
        if ($hasButtons || $exists === []) {
            // 菜单按钮是完整设计器结构：创建时必须提交，更新时仅在请求显式携带 buttons 时覆盖，避免局部更新状态/名称误清空菜单。
            $data['buttons'] = $this->normalizeButtons($this->decodeButtons($buttons, '菜单按钮结构'));
            $this->assertMenuStructure($data['buttons']);
        }
        foreach (['tenant_id', 'account_id', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    /**
     * 解析可视化菜单按钮数组；JSON 格式错误必须在保存阶段暴露，不能静默降级为空菜单。
     *
     * @return array<int,mixed>
     */
    private function decodeButtons(mixed $buttons, string $label): array
    {
        if (is_string($buttons)) {
            $decoded = json_decode($buttons, true);
            if (!is_array($decoded) || !array_is_list($decoded)) {
                throw new ErrorResponseException(sprintf('%s格式错误', $label));
            }

            return $decoded;
        }

        if (!is_array($buttons)) {
            return [];
        }
        if ($buttons !== [] && !array_is_list($buttons)) {
            throw new ErrorResponseException(sprintf('%s格式错误', $label));
        }

        return $buttons;
    }

    /**
     * @param array<int,mixed> $buttons
     * @return array<int,array<string,mixed>>
     */
    private function buildOfficialButtons(array $buttons, int $accountId = 0): array
    {
        $buttons = $this->normalizeButtons($buttons);
        $this->assertMenuStructure($buttons);
        if (count($buttons) > self::MAX_TOP_BUTTONS) {
            throw new ErrorResponseException('一级菜单最多 3 个');
        }

        $result = [];
        foreach ($buttons as $index => $button) {
            $result[] = $this->buildOfficialButton($button, $index + 1, 1, $accountId);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $button
     * @return array<string,mixed>
     */
    private function buildOfficialButton(array $button, int $index, int $level, int $accountId = 0): array
    {
        $name = trim((string)($button['name'] ?? ''));
        if ($name === '') {
            throw new ErrorResponseException(sprintf('第 %d 个菜单名称不能为空', $index));
        }
        $this->assertButtonName($name, $level, sprintf('第 %d 个菜单', $index));

        $children = $this->childButtons($button);
        if ($children !== []) {
            if ($level !== 1) {
                throw new ErrorResponseException('微信菜单最多支持二级');
            }
            if (count($children) > self::MAX_SUB_BUTTONS) {
                throw new ErrorResponseException(sprintf('菜单「%s」最多 5 个二级菜单', $name));
            }
            return [
                'name' => $name,
                'sub_button' => array_map(fn (array $child, int $childIndex): array => $this->buildOfficialButton($child, $childIndex + 1, 2, $accountId), $children, array_keys($children)),
            ];
        }

        $type = strtolower(trim((string)($button['type'] ?? 'view')));
        if ($type === 'json') {
            $raw = $this->parseRawOfficialButton($button['raw_json'] ?? [], sprintf('菜单「%s」高级 JSON', $name));
            $raw['name'] = trim((string)($raw['name'] ?? $name)) ?: $name;
            $this->assertRawOfficialButton($raw, $level, sprintf('菜单「%s」高级 JSON', $name));
            return $raw;
        }

        return match ($type) {
            'view' => ['type' => 'view', 'name' => $name, 'url' => $this->requireUrl($button, 'url', sprintf('菜单「%s」网页链接不能为空', $name), sprintf('菜单「%s」网页链接必须是完整 URL', $name))],
            'local_media', 'media' => ['type' => 'media_id', 'name' => $name, 'media_id' => $this->resolveMediaId($button, $name, $accountId)],
            'local_article', 'article' => $this->buildArticleButton($button, $name, $accountId),
            'reply', 'menu_click' => ['type' => 'click', 'name' => $name, 'key' => $this->resolveReplyKey($button, $name, $accountId)],
            'click' => ['type' => 'click', 'name' => $name, 'key' => $this->requireString($button, 'key', sprintf('菜单「%s」事件 KEY 不能为空', $name))],
            'media_id', 'view_limited' => ['type' => $type, 'name' => $name, 'media_id' => $this->requireString($button, 'media_id', sprintf('菜单「%s」MediaID 不能为空', $name))],
            'miniprogram' => [
                'type' => 'miniprogram',
                'name' => $name,
                'url' => $this->requireUrl($button, 'url', sprintf('菜单「%s」备用链接不能为空', $name), sprintf('菜单「%s」备用链接必须是完整 URL', $name)),
                'appid' => $this->requireString($button, 'appid', sprintf('菜单「%s」小程序 AppID 不能为空', $name)),
                'pagepath' => $this->requireString($button, 'pagepath', sprintf('菜单「%s」小程序页面不能为空', $name)),
            ],
            default => $this->buildOfficialFallbackButton($button, $name, $type),
        };
    }

    /** @param array<string,mixed> $button @return array<string,mixed> */
    private function buildArticleButton(array $button, string $name, int $accountId): array
    {
        $articleId = (int)($button['article_id'] ?? $button['article_local_id'] ?? 0);
        $article = $articleId > 0 ? $this->articleMapper->read($articleId) : null;
        if (!$article instanceof WechatClientArticle) {
            throw new ErrorResponseException(sprintf('菜单「%s」关联文章不存在', $name));
        }
        if ($accountId > 0 && (int)$article->account_id !== $accountId) {
            throw new ErrorResponseException(sprintf('菜单「%s」关联文章不属于当前账号', $name));
        }
        $mediaId = trim((string)$article->draft_media_id);
        if ($mediaId !== '') {
            return ['type' => 'view_limited', 'name' => $name, 'media_id' => $mediaId];
        }

        // 本地文章按钮必须转换为微信素材类菜单；外部链接请显式选择“网页链接”，避免未上传文章被误当成本地文章发布。
        throw new ErrorResponseException(sprintf('菜单「%s」关联文章尚未上传草稿，不能发布为本地文章菜单', $name));
    }

    /** @param array<string,mixed> $button */
    private function resolveMediaId(array $button, string $name, int $accountId): string
    {
        $mediaId = trim((string)($button['media_id'] ?? ''));
        if ($mediaId !== '') {
            return $mediaId;
        }
        $mediaId = (int)($button['media_local_id'] ?? $button['media_id_local'] ?? 0);
        $media = $mediaId > 0 ? $this->mediaMapper->read($mediaId) : null;
        if (!$media instanceof WechatClientMedia || trim((string)$media->media_id) === '') {
            throw new ErrorResponseException(sprintf('菜单「%s」关联素材不存在或未上传微信', $name));
        }
        if ($accountId > 0 && (int)$media->account_id !== $accountId) {
            throw new ErrorResponseException(sprintf('菜单「%s」关联素材不属于当前账号', $name));
        }

        return (string)$media->media_id;
    }

    /** @param array<string,mixed> $button */
    private function resolveReplyKey(array $button, string $name, int $accountId): string
    {
        $ruleId = (int)($button['reply_rule_id'] ?? $button['rule_id'] ?? 0);
        $rule = $ruleId > 0 ? $this->replyMapper->read($ruleId) : null;
        if (!$rule instanceof WechatClientReplyRule || (string)$rule->rule_type !== 'menu_click') {
            throw new ErrorResponseException(sprintf('菜单「%s」请选择菜单点击回复规则', $name));
        }
        if ($accountId > 0 && (int)$rule->account_id !== $accountId) {
            throw new ErrorResponseException(sprintf('菜单「%s」关联回复规则不属于当前账号', $name));
        }

        return 'XC_REPLY_' . $ruleId;
    }

    /** @param array<string,mixed> $button @return array<string,mixed> */
    private function buildOfficialFallbackButton(array $button, string $name, string $type): array
    {
        $keyTypes = ['scancode_push', 'scancode_waitmsg', 'pic_sysphoto', 'pic_photo_or_album', 'pic_weixin', 'location_select'];
        if (in_array($type, $keyTypes, true)) {
            return ['type' => $type, 'name' => $name, 'key' => $this->requireString($button, 'key', sprintf('菜单「%s」事件 KEY 不能为空', $name))];
        }

        throw new ErrorResponseException(sprintf('菜单「%s」类型不支持', $name));
    }

    /** @param array<string,mixed> $button */
    private function requireString(array $button, string $field, string $message): string
    {
        $value = trim((string)($button[$field] ?? ''));
        if ($value === '') {
            throw new ErrorResponseException($message);
        }

        return $value;
    }

    /** @param array<string,mixed> $button */
    private function requireUrl(array $button, string $field, string $emptyMessage, string $invalidMessage): string
    {
        $value = $this->requireString($button, $field, $emptyMessage);
        if (!preg_match('#^https?://#i', $value)) {
            throw new ErrorResponseException($invalidMessage);
        }

        return $value;
    }

    /**
     * @param array<int,mixed> $buttons
     * @return array<int,array<string,mixed>>
     */
    private function normalizeButtons(array $buttons): array
    {
        $normalized = [];
        foreach ($buttons as $button) {
            if (!is_array($button)) {
                continue;
            }
            $button['name'] = trim((string)($button['name'] ?? ''));
            $button['type'] = strtolower(trim((string)($button['type'] ?? 'view')));
            $children = $this->childButtons($button);
            if ($children !== []) {
                $button['children'] = $this->normalizeButtons($children);
                unset($button['sub_button']);
            }
            $normalized[] = $button;
        }

        return $normalized;
    }

    /**
     * 校验本地菜单结构是否符合微信官方自定义菜单限制。
     *
     * 微信官方限制为：一级菜单最多 3 个；每个一级菜单最多 5 个二级菜单；菜单最多二级。
     *
     * @param array<int,array<string,mixed>> $buttons
     */
    private function assertMenuStructure(array $buttons): void
    {
        if ($buttons === []) {
            throw new ErrorResponseException('微信官方自定义菜单至少需要 1 个一级菜单');
        }
        if (count($buttons) > self::MAX_TOP_BUTTONS) {
            throw new ErrorResponseException('微信官方自定义菜单一级菜单最多 3 个');
        }

        foreach ($buttons as $index => $button) {
            $this->assertLocalButtonStructure($button, 1, sprintf('第 %d 个一级菜单', $index + 1));
        }
    }

    /** @param array<string,mixed> $button */
    private function assertLocalButtonStructure(array $button, int $level, string $label): void
    {
        $name = trim((string)($button['name'] ?? ''));
        if ($name === '') {
            throw new ErrorResponseException(sprintf('%s名称不能为空', $label));
        }
        $this->assertButtonName($name, $level, $label);

        $children = $this->childButtons($button);
        if ($children === []) {
            if (($button['type'] ?? '') === 'json') {
                $raw = $this->parseRawOfficialButton($button['raw_json'] ?? [], $label . '的高级 JSON');
                $this->assertRawOfficialButton($raw, $level, $label . '的高级 JSON');
            } else {
                $this->assertLocalLeafButton($button, $label);
            }

            return;
        }
        if ($level !== 1) {
            throw new ErrorResponseException('微信官方自定义菜单最多支持二级菜单');
        }
        if (count($children) > self::MAX_SUB_BUTTONS) {
            throw new ErrorResponseException(sprintf('%s 最多包含 5 个二级菜单', $label));
        }

        foreach ($children as $index => $child) {
            $this->assertLocalButtonStructure($child, 2, sprintf('%s下第 %d 个二级菜单', $label, $index + 1));
        }
    }

    /** @param array<string,mixed> $button */
    private function assertLocalLeafButton(array $button, string $label): void
    {
        $type = strtolower(trim((string)($button['type'] ?? 'view')));
        if ($type === 'view') {
            $this->requireUrl($button, 'url', sprintf('%s网页链接不能为空', $label), sprintf('%s网页链接必须是完整 URL', $label));
            return;
        }
        if (in_array($type, ['local_media', 'media'], true)) {
            if (trim((string)($button['media_id'] ?? '')) === '' && (int)($button['media_local_id'] ?? $button['media_id_local'] ?? 0) <= 0) {
                throw new ErrorResponseException(sprintf('%s关联素材不能为空', $label));
            }
            return;
        }
        if (in_array($type, ['local_article', 'article'], true)) {
            if ((int)($button['article_id'] ?? $button['article_local_id'] ?? 0) <= 0) {
                throw new ErrorResponseException(sprintf('%s关联文章不能为空', $label));
            }
            return;
        }
        if (in_array($type, ['reply', 'menu_click'], true)) {
            if ((int)($button['reply_rule_id'] ?? $button['rule_id'] ?? 0) <= 0) {
                throw new ErrorResponseException(sprintf('%s菜单点击回复规则不能为空', $label));
            }
            return;
        }
        if ($type === 'click') {
            $this->requireString($button, 'key', sprintf('%s事件 KEY 不能为空', $label));
            return;
        }
        if (in_array($type, ['media_id', 'view_limited'], true)) {
            $this->requireString($button, 'media_id', sprintf('%s MediaID 不能为空', $label));
            return;
        }
        if ($type === 'miniprogram') {
            $this->requireUrl($button, 'url', sprintf('%s备用链接不能为空', $label), sprintf('%s备用链接必须是完整 URL', $label));
            $this->requireString($button, 'appid', sprintf('%s小程序 AppID 不能为空', $label));
            $this->requireString($button, 'pagepath', sprintf('%s小程序页面不能为空', $label));
            return;
        }
        $keyTypes = ['scancode_push', 'scancode_waitmsg', 'pic_sysphoto', 'pic_photo_or_album', 'pic_weixin', 'location_select'];
        if (in_array($type, $keyTypes, true)) {
            $this->requireString($button, 'key', sprintf('%s事件 KEY 不能为空', $label));
            return;
        }

        throw new ErrorResponseException(sprintf('%s类型不支持', $label));
    }

    /**
     * 解析并校验高级 JSON 必须是官方按钮对象。
     *
     * @return array<string,mixed>
     */
    private function parseRawOfficialButton(mixed $raw, string $label): array
    {
        if (is_string($raw)) {
            $text = trim($raw);
            if ($text === '') {
                throw new ErrorResponseException(sprintf('%s 不能为空', $label));
            }
            $decoded = json_decode($text, true);
            if (!is_array($decoded) || array_is_list($decoded)) {
                throw new ErrorResponseException(sprintf('%s 格式错误', $label));
            }

            return $decoded;
        }

        if (!is_array($raw) || array_is_list($raw) || $raw === []) {
            throw new ErrorResponseException(sprintf('%s 不能为空', $label));
        }

        return $raw;
    }

    /**
     * 校验高级 JSON 中绕过可视化设计器的官方按钮结构，防止发布时才暴露数量和层级错误。
     *
     * @param array<string,mixed> $button
     */
    private function assertRawOfficialButton(array $button, int $level, string $label): void
    {
        $rawName = trim((string)($button['name'] ?? ''));
        if ($rawName !== '') {
            $this->assertButtonName($rawName, $level, $label);
        }

        $children = $this->rawChildButtons($button, $label);
        if ($children === []) {
            $this->assertRawOfficialLeafButton($button, $label);
            return;
        }
        if ($level !== 1) {
            throw new ErrorResponseException(sprintf('%s 不能包含三级菜单', $label));
        }
        if (array_key_exists('type', $button) && trim((string)$button['type']) !== '') {
            throw new ErrorResponseException(sprintf('%s 包含 sub_button 时不能同时配置 type', $label));
        }
        if (count($children) > self::MAX_SUB_BUTTONS) {
            throw new ErrorResponseException(sprintf('%s 最多包含 5 个二级菜单', $label));
        }

        foreach ($children as $index => $child) {
            $this->assertRawOfficialButton($child, 2, sprintf('%s下第 %d 个二级菜单', $label, $index + 1));
        }
    }

    /**
     * 校验高级 JSON 叶子按钮的基础官方字段，避免保存成功但发布到微信时才暴露明显结构错误。
     *
     * @param array<string,mixed> $button
     */
    private function assertRawOfficialLeafButton(array $button, string $label): void
    {
        $type = strtolower(trim((string)($button['type'] ?? '')));
        if ($type === '') {
            throw new ErrorResponseException(sprintf('%s 需配置 type 或 sub_button', $label));
        }

        $required = match ($type) {
            'view' => ['url' => '网页链接不能为空'],
            'miniprogram' => ['url' => '备用链接不能为空', 'appid' => '小程序 AppID 不能为空', 'pagepath' => '小程序页面不能为空'],
            'click', 'scancode_push', 'scancode_waitmsg', 'pic_sysphoto', 'pic_photo_or_album', 'pic_weixin', 'location_select' => ['key' => '事件 KEY 不能为空'],
            'media_id', 'view_limited' => ['media_id' => 'MediaID 不能为空'],
            'article_id', 'article_view_limited' => ['article_id' => '图文 article_id 不能为空'],
            default => null,
        };
        if ($required === null) {
            throw new ErrorResponseException(sprintf('%s 类型不支持', $label));
        }

        foreach ($required as $field => $message) {
            if (trim((string)($button[$field] ?? '')) === '') {
                throw new ErrorResponseException(sprintf('%s %s', $label, $message));
            }
        }
        if (in_array($type, ['view', 'miniprogram'], true) && !preg_match('#^https?://#i', (string)($button['url'] ?? ''))) {
            $message = $type === 'view' ? '网页链接必须是完整 URL' : '备用链接必须是完整 URL';
            throw new ErrorResponseException(sprintf('%s %s', $label, $message));
        }
    }

    private function assertButtonName(string $name, int $level, string $label): void
    {
        $maxWidth = $level === 1 ? self::TOP_NAME_WIDTH : self::SUB_NAME_WIDTH;
        if (mb_strwidth($name, 'UTF-8') <= $maxWidth) {
            return;
        }

        $limit = $level === 1 ? '4 个汉字（或 8 个英文字符）' : '8 个汉字（或 16 个英文字符）';
        throw new ErrorResponseException(sprintf('%s名称过长，微信官方限制最多 %s', $label, $limit));
    }

    /**
     * @param array<string,mixed> $button
     * @return array<int,array<string,mixed>>
     */
    private function rawChildButtons(array $button, string $label): array
    {
        $children = $button['sub_button'] ?? $button['children'] ?? [];
        if ($children === [] || $children === null || $children === '') {
            return [];
        }
        if (!is_array($children)) {
            throw new ErrorResponseException(sprintf('%s 的 sub_button 必须是数组', $label));
        }

        $result = [];
        foreach ($children as $index => $child) {
            if (!is_array($child)) {
                throw new ErrorResponseException(sprintf('%s 下第 %d 个子菜单必须是对象', $label, (int)$index + 1));
            }
            $result[] = $child;
        }

        return $result;
    }

    /** @param array<string,mixed> $button @return array<int,array<string,mixed>> */
    private function childButtons(array $button): array
    {
        $children = $button['children'] ?? $button['sub_button'] ?? [];
        if (!is_array($children)) {
            return [];
        }

        return array_values(array_filter($children, static fn (mixed $item): bool => is_array($item)));
    }
}
