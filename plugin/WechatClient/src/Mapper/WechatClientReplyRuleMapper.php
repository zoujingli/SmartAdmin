<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientReplyRule;

/**
 * 微信自动回复规则 Mapper。
 *
 * 提供后台筛选与公众号回调场景下的规则匹配查询。
 */
final class WechatClientReplyRuleMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientReplyRule::class
    ) {}

    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 查询订阅回复规则；发送顺序必须稳定，避免多条延时回复错序。
     *
     * @return array<int, WechatClientReplyRule>
     */
    public function subscribeRules(int $accountId): array
    {
        return $this->rulesQuery($accountId, 'subscribe')->get()->all();
    }

    public function defaultRule(int $accountId): ?WechatClientReplyRule
    {
        /** @var null|WechatClientReplyRule $rule */
        $rule = $this->rulesQuery($accountId, 'default')->first();

        return $rule;
    }

    public function menuClickRule(int $accountId, string $key): ?WechatClientReplyRule
    {
        $id = str_starts_with($key, 'XC_REPLY_') ? (int)substr($key, 9) : 0;
        if ($id <= 0) {
            return null;
        }

        /** @var null|WechatClientReplyRule $rule */
        $rule = $this->model::query()
            ->where('account_id', $accountId)
            ->where('id', $id)
            ->where('rule_type', 'menu_click')
            ->where('status', 1)
            ->first();

        return $rule;
    }

    public function keywordRule(int $accountId, string $content): ?WechatClientReplyRule
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        /** @var null|WechatClientReplyRule $exact */
        $exact = $this->rulesQuery($accountId, 'keyword')
            ->where('match_mode', 'exact')
            ->where('keyword', $content)
            ->first();
        if ($exact instanceof WechatClientReplyRule) {
            return $exact;
        }

        foreach ($this->rulesQuery($accountId, 'keyword')->where('match_mode', 'contains')->get()->all() as $rule) {
            if ($rule instanceof WechatClientReplyRule && trim((string)$rule->keyword) !== '' && str_contains($content, (string)$rule->keyword)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * 禁用创建人数据范围，回复规则由公众号账号统一维护。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理回复规则后台筛选条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('keyword')
            ->equal('tenant_id,account_id,rule_type,match_mode,reply_type,status')
            ->dateBetween('created_at')
            ->getQuery();
    }

    private function rulesQuery(int $accountId, string $type): Builder
    {
        return $this->model::query()
            ->where('account_id', $accountId)
            ->where('rule_type', $type)
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id');
    }
}
