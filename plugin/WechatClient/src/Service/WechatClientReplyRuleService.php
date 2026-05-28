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

use Hyperf\Coroutine\Coroutine;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatClient\Mapper\WechatClientArticleMapper;
use Plugin\WechatClient\Mapper\WechatClientMediaMapper;
use Plugin\WechatClient\Mapper\WechatClientReplyRuleMapper;
use Plugin\WechatClient\Model\WechatClientAccount;
use Plugin\WechatClient\Model\WechatClientArticle;
use Plugin\WechatClient\Model\WechatClientMedia;
use Plugin\WechatClient\Model\WechatClientReplyRule;

/**
 * 微信自动回复服务。
 *
 * 负责后台规则维护、公众号被动回复匹配，以及订阅事件的内存延时客服消息发送。
 */
final class WechatClientReplyRuleService extends CoreService
{
    private const RULE_TYPES = ['subscribe', 'default', 'keyword', 'menu_click'];

    private const MATCH_MODES = ['exact', 'contains'];

    private const REPLY_TYPES = ['text', 'image', 'voice', 'video', 'news'];

    public function __construct(
        protected WechatClientReplyRuleMapper $mapper,
        private readonly WechatClientAccountService $accounts,
        private readonly WechatClientMediaMapper $mediaMapper,
        private readonly WechatClientArticleMapper $articleMapper,
    ) {}

    /**
     * 处理微信官方推送并返回被动回复 XML；订阅多回复走异步客服消息，立即返回 null。
     *
     * @param array<string,mixed> $payload
     */
    public function handleOfficialPush(WechatClientAccount $account, array $payload): ?string
    {
        $msgType = strtolower(trim((string)($payload['MsgType'] ?? '')));
        if ($msgType === 'event') {
            return $this->handleEvent($account, $payload);
        }
        if ($msgType === 'text') {
            return $this->handleText($account, $payload);
        }

        return null;
    }

    /**
     * 保存回复规则前校验业务字段和结构化回复内容。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['rule_type', 'keyword', 'match_mode', 'reply_type'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        $hasReplyContent = array_key_exists('reply_content', $data);
        $replyContent = $data['reply_content'] ?? [];

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'account_id.integer' => '接口账号 ID 必须为数字',
            'account_id.min:1' => '接口账号不能为空',
            'rule_type.in:subscribe,default,keyword,menu_click' => '规则类型错误',
            'keyword.max:120' => '关键词最多 120 位',
            'match_mode.in:exact,contains' => '匹配模式错误',
            'reply_type.in:text,image,voice,video,news' => '回复类型错误',
            'delay_seconds.integer' => '延迟秒数必须为数字',
            'delay_seconds.min:0' => '延迟秒数不能小于 0',
            'delay_seconds.max:86400' => '延迟秒数不能超过 86400',
            'sort.integer' => '排序必须为数字',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['account_id.required'] = '接口账号不能为空';
            $rules['rule_type.default'] = 'keyword';
            $rules['match_mode.default'] = 'contains';
            $rules['reply_type.default'] = 'text';
            $rules['delay_seconds.default'] = 0;
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        $replyType = (string)($data['reply_type'] ?? $exists['reply_type'] ?? 'text');
        if ($hasReplyContent || $exists === []) {
            // 回复内容决定微信端真实输出，创建必须校验，更新仅在显式提交 reply_content 时覆盖，避免局部更新误清空回复。
            $data['reply_content'] = $this->normalizeReplyContent($this->decodeReplyContent($replyContent));
            $this->assertReplyContent($replyType, $data['reply_content']);
        } elseif (isset($exists['reply_content'])) {
            $this->assertReplyContent($replyType, $this->normalizeReplyContent((array)$exists['reply_content']));
        }
        foreach (['tenant_id', 'account_id', 'delay_seconds', 'sort', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }
        if (($data['rule_type'] ?? $exists['rule_type'] ?? '') === 'keyword' && trim((string)($data['keyword'] ?? $exists['keyword'] ?? '')) === '') {
            throw new ErrorResponseException('关键词规则必须填写关键词');
        }

        return $data;
    }

    /**
     * 解析回复内容 JSON，格式错误不能静默保存为空回复，否则微信端命中规则后不会有任何输出。
     *
     * @return array<string,mixed>
     */
    private function decodeReplyContent(mixed $content): array
    {
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                throw new ErrorResponseException('回复内容格式错误');
            }

            return $decoded;
        }

        if (!is_array($content) || ($content !== [] && array_is_list($content))) {
            throw new ErrorResponseException('回复内容格式错误');
        }

        return $content;
    }

    /**
     * 保存阶段按回复类型校验必要内容，避免规则可保存但被动回复/客服消息构建时静默降级为空。
     *
     * @param array<string,mixed> $content
     */
    private function assertReplyContent(string $replyType, array $content): void
    {
        $replyType = $this->normalizeReplyType($replyType);
        if ($replyType === 'text') {
            if (trim((string)($content['content'] ?? '')) === '') {
                throw new ErrorResponseException('文本回复内容不能为空');
            }
            return;
        }

        if (in_array($replyType, ['image', 'voice', 'video'], true)) {
            $mediaId = trim((string)($content['media_id'] ?? ''));
            $localId = (int)($content['media_local_id'] ?? 0);
            if ($mediaId === '' && $localId <= 0) {
                throw new ErrorResponseException('素材回复必须选择本地素材或填写 MediaID');
            }
            return;
        }

        if ($replyType === 'news' && (int)($content['article_id'] ?? 0) <= 0) {
            throw new ErrorResponseException('图文回复必须选择本地文章');
        }
    }

    /**
     * 订阅事件统一使用客服消息异步发送，支持多条规则和每条独立延迟。
     *
     * @param array<string,mixed> $payload
     */
    private function handleEvent(WechatClientAccount $account, array $payload): ?string
    {
        $event = strtolower(trim((string)($payload['Event'] ?? '')));
        $openid = trim((string)($payload['FromUserName'] ?? ''));
        if ($openid === '') {
            return null;
        }

        if ($event === 'subscribe') {
            foreach ($this->mapper->subscribeRules((int)$account->id) as $rule) {
                $this->scheduleCustomerMessage($account, $openid, $rule);
            }
            return null;
        }

        if ($event === 'click') {
            $rule = $this->mapper->menuClickRule((int)$account->id, (string)($payload['EventKey'] ?? ''));
            return $rule instanceof WechatClientReplyRule ? $this->buildPassiveXml($payload, $rule) : null;
        }

        return null;
    }

    /**
     * 文本消息先匹配关键词，未命中时使用默认回复。
     *
     * @param array<string,mixed> $payload
     */
    private function handleText(WechatClientAccount $account, array $payload): ?string
    {
        $content = (string)($payload['Content'] ?? '');
        $rule = $this->mapper->keywordRule((int)$account->id, $content) ?? $this->mapper->defaultRule((int)$account->id);

        return $rule instanceof WechatClientReplyRule ? $this->buildPassiveXml($payload, $rule) : null;
    }

    private function scheduleCustomerMessage(WechatClientAccount $account, string $openid, WechatClientReplyRule $rule): void
    {
        $delay = max(0, (int)$rule->delay_seconds);
        // 延时回复按产品约定采用 Swoole 内存协程，重启不可恢复；异常仅记录到 Hyperf 协程日志，不能阻塞微信回调响应。
        Coroutine::create(function () use ($account, $openid, $rule, $delay): void {
            if ($delay > 0 && class_exists(\Swoole\Coroutine::class)) {
                \Swoole\Coroutine::sleep($delay);
            } elseif ($delay > 0) {
                sleep($delay);
            }

            try {
                $message = $this->buildCustomerMessage($openid, $rule);
                if ($message !== []) {
                    $this->accounts->officialRequest($account, 'cgi-bin/message/custom/send', $message);
                }
            } catch (\Throwable) {
                // 客服消息发送失败通常由 48 小时窗口、素材失效或接口限流导致；回调已响应，只能静默降级避免重试风暴。
            }
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCustomerMessage(string $openid, WechatClientReplyRule $rule): array
    {
        $type = $this->normalizeReplyType((string)$rule->reply_type);
        $content = $this->normalizeReplyContent((array)$rule->reply_content);
        $message = ['touser' => $openid, 'msgtype' => $type];

        return match ($type) {
            'text' => trim((string)($content['content'] ?? '')) === '' ? [] : $message + ['text' => ['content' => (string)$content['content']]],
            'image', 'voice' => ($mediaId = $this->resolveMediaId($content)) === '' ? [] : $message + [$type => ['media_id' => $mediaId]],
            'video' => ($mediaId = $this->resolveMediaId($content)) === '' ? [] : $message + ['video' => [
                'media_id' => $mediaId,
                'title' => (string)($content['title'] ?? ''),
                'description' => (string)($content['description'] ?? ''),
            ]],
            'news' => ($article = $this->resolveArticle($content)) === null ? [] : [
                'touser' => $openid,
                'msgtype' => 'news',
                'news' => ['articles' => [[
                    'title' => (string)$article->title,
                    'description' => (string)$article->digest,
                    'url' => (string)($article->content_source_url ?: ($content['url'] ?? '')),
                    'picurl' => (string)($article->thumb_url ?: ($content['picurl'] ?? '')),
                ]]],
            ],
            default => [],
        };
    }

    /**
     * 构建微信被动回复 XML；被动回复只能返回一条消息，多条订阅回复已改为异步客服消息。
     *
     * @param array<string,mixed> $payload
     */
    private function buildPassiveXml(array $payload, WechatClientReplyRule $rule): ?string
    {
        $to = (string)($payload['FromUserName'] ?? '');
        $from = (string)($payload['ToUserName'] ?? '');
        if ($to === '' || $from === '') {
            return null;
        }

        $type = $this->normalizeReplyType((string)$rule->reply_type);
        $content = $this->normalizeReplyContent((array)$rule->reply_content);
        $body = [
            'ToUserName' => $to,
            'FromUserName' => $from,
            'CreateTime' => (string)time(),
            'MsgType' => $type,
        ];

        return match ($type) {
            'text' => trim((string)($content['content'] ?? '')) === '' ? null : $this->xml($body + ['Content' => (string)$content['content']]),
            'image' => ($mediaId = $this->resolveMediaId($content)) === '' ? null : $this->nestedXml($body, 'Image', ['MediaId' => $mediaId]),
            'voice' => ($mediaId = $this->resolveMediaId($content)) === '' ? null : $this->nestedXml($body, 'Voice', ['MediaId' => $mediaId]),
            'video' => ($mediaId = $this->resolveMediaId($content)) === '' ? null : $this->nestedXml($body, 'Video', [
                'MediaId' => $mediaId,
                'Title' => (string)($content['title'] ?? ''),
                'Description' => (string)($content['description'] ?? ''),
            ]),
            'news' => ($article = $this->resolveArticle($content)) === null ? null : $this->newsXml($body, $article, $content),
            default => null,
        };
    }

    /** @param array<string,mixed> $content */
    private function resolveMediaId(array $content): string
    {
        $mediaId = trim((string)($content['media_id'] ?? ''));
        if ($mediaId !== '') {
            return $mediaId;
        }
        $localId = (int)($content['media_local_id'] ?? 0);
        if ($localId <= 0) {
            return '';
        }
        $media = $this->mediaMapper->read($localId);

        return $media instanceof WechatClientMedia ? trim((string)$media->media_id) : '';
    }

    /** @param array<string,mixed> $content */
    private function resolveArticle(array $content): ?WechatClientArticle
    {
        $articleId = (int)($content['article_id'] ?? 0);
        if ($articleId <= 0) {
            return null;
        }
        $article = $this->articleMapper->read($articleId);

        return $article instanceof WechatClientArticle ? $article : null;
    }

    private function normalizeReplyType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::REPLY_TYPES, true)) {
            return 'text';
        }

        return $type;
    }

    /** @param array<string,mixed> $content @return array<string,mixed> */
    private function normalizeReplyContent(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                $content[$key] = trim($value);
            }
        }

        return $content;
    }

    /** @param array<string,string> $data */
    private function xml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $value) {
            $xml .= sprintf('<%s><![CDATA[%s]]></%s>', $key, $this->cdata((string)$value), $key);
        }

        return $xml . '</xml>';
    }

    /** @param array<string,string> $base @param array<string,string> $nested */
    private function nestedXml(array $base, string $node, array $nested): string
    {
        $xml = '<xml>';
        foreach ($base as $key => $value) {
            $xml .= sprintf('<%s><![CDATA[%s]]></%s>', $key, $this->cdata((string)$value), $key);
        }
        $xml .= '<' . $node . '>';
        foreach ($nested as $key => $value) {
            $xml .= sprintf('<%s><![CDATA[%s]]></%s>', $key, $this->cdata((string)$value), $key);
        }

        return $xml . '</' . $node . '></xml>';
    }

    /** @param array<string,string> $base @param array<string,mixed> $content */
    private function newsXml(array $base, WechatClientArticle $article, array $content): string
    {
        $xml = '<xml>';
        foreach ($base as $key => $value) {
            $xml .= sprintf('<%s><![CDATA[%s]]></%s>', $key, $this->cdata((string)$value), $key);
        }
        $url = (string)($article->content_source_url ?: ($content['url'] ?? ''));
        $picurl = (string)($article->thumb_url ?: ($content['picurl'] ?? ''));
        $xml .= '<ArticleCount>1</ArticleCount><Articles><item>';
        $xml .= '<Title><![CDATA[' . $this->cdata((string)$article->title) . ']]></Title>';
        $xml .= '<Description><![CDATA[' . $this->cdata((string)$article->digest) . ']]></Description>';
        $xml .= '<PicUrl><![CDATA[' . $this->cdata($picurl) . ']]></PicUrl>';
        $xml .= '<Url><![CDATA[' . $this->cdata($url) . ']]></Url>';

        return $xml . '</item></Articles></xml>';
    }

    private function cdata(string $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $value);
    }
}
