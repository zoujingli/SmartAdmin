<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Service;

use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatClient\Mapper\WechatClientArticleMapper;
use Plugin\WechatClient\Model\WechatClientArticle;

/**
 * 微信图文文章服务。
 *
 * 负责本地图文维护、上传微信草稿、提交发布以及同步发布状态。
 */
final class WechatClientArticleService extends CoreService
{
    public function __construct(
        protected WechatClientArticleMapper $mapper,
        private readonly WechatClientAccountService $accounts,
    ) {}

    /**
     * 将本地文章上传为微信草稿，返回并保存官方 draft media_id。
     */
    public function uploadDraft(int $id): WechatClientArticle
    {
        $article = $this->requireArticle($id);
        $account = $this->accounts->requireAccount((int)$article->account_id);
        if (trim((string)$article->thumb_media_id) === '') {
            throw new ErrorResponseException('请先配置已上传到微信的封面 MediaID');
        }
        if (trim((string)$article->title) === '' || trim((string)$article->content) === '') {
            throw new ErrorResponseException('文章标题和正文不能为空');
        }

        $payload = [
            'articles' => [[
                'title' => (string)$article->title,
                'author' => (string)$article->author,
                'digest' => (string)$article->digest,
                'content' => (string)$article->content,
                'content_source_url' => (string)$article->content_source_url,
                'thumb_media_id' => (string)$article->thumb_media_id,
                'need_open_comment' => 0,
                'only_fans_can_comment' => 0,
            ]],
        ];
        $result = $this->accounts->officialRequest($account, 'cgi-bin/draft/add', $payload);
        $this->mapper->update($article, [
            'draft_media_id' => (string)($result['media_id'] ?? ''),
            'publish_status' => 'draft_uploaded',
            'raw_payload' => $result,
        ]);

        return $this->requireArticle($id);
    }

    /**
     * 提交微信图文发布任务；发布结果需通过 queryPublishStatus 后续确认。
     */
    public function publish(int $id): WechatClientArticle
    {
        $article = $this->requireArticle($id);
        $account = $this->accounts->requireAccount((int)$article->account_id);
        $mediaId = trim((string)$article->draft_media_id);
        if ($mediaId === '') {
            throw new ErrorResponseException('请先上传微信草稿');
        }

        $result = $this->accounts->officialRequest($account, 'cgi-bin/freepublish/submit', ['media_id' => $mediaId]);
        $this->mapper->update($article, [
            'publish_id' => (string)($result['publish_id'] ?? $article->publish_id),
            'publish_status' => 'publishing',
            'raw_payload' => $result,
        ]);

        return $this->requireArticle($id);
    }

    /**
     * 查询微信发布任务状态并同步本地 publish_status。
     */
    public function queryPublishStatus(int $id): WechatClientArticle
    {
        $article = $this->requireArticle($id);
        $account = $this->accounts->requireAccount((int)$article->account_id);
        $publishId = trim((string)$article->publish_id);
        if ($publishId === '') {
            throw new ErrorResponseException('文章还没有发布任务 ID');
        }

        $result = $this->accounts->officialRequest($account, 'cgi-bin/freepublish/get', ['publish_id' => $publishId]);
        $status = (string)($result['publish_status'] ?? $result['status'] ?? 'publishing');
        $this->mapper->update($article, [
            'publish_status' => $status,
            'raw_payload' => $result,
        ]);

        return $this->requireArticle($id);
    }

    /**
     * 保存文章前校验基础字段；官方 ID 字段允许为空，后续由上传/发布动作补齐。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['title', 'author', 'thumb_media_id', 'thumb_url', 'content', 'digest', 'content_source_url', 'draft_media_id', 'publish_id', 'publish_status'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'account_id.integer' => '接口账号 ID 必须为数字',
            'account_id.min:1' => '接口账号不能为空',
            'title.filled' => '文章标题不能为空',
            'title.max:180' => '文章标题最多 180 位',
            'author.max:80' => '作者最多 80 位',
            'thumb_media_id.max:180' => '封面 MediaID 最多 180 位',
            'thumb_url.max:500' => '封面地址最多 500 位',
            // 富文本正文允许保存完整 HTML；上传微信草稿时再校验标题、正文和封面 MediaID 是否满足官方要求。
            'content',
            'digest.max:500' => '摘要最多 500 位',
            'content_source_url.max:500' => '原文链接最多 500 位',
            'draft_media_id.max:180' => '草稿 MediaID 最多 180 位',
            'publish_id.max:180' => '发布任务 ID 最多 180 位',
            'publish_status.max:30' => '发布状态最多 30 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['account_id.required'] = '接口账号不能为空';
            $rules['title.required'] = '文章标题不能为空';
            $rules['publish_status.default'] = 'draft';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['tenant_id', 'account_id', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    private function requireArticle(int $id): WechatClientArticle
    {
        $article = $this->mapper->read($id);
        if (!$article instanceof WechatClientArticle) {
            throw new ErrorResponseException('文章不存在');
        }

        return $article;
    }
}
