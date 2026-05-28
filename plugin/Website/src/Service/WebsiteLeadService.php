<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Service;

use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\Website\Mapper\WebsiteLeadMapper;
use Plugin\Website\Model\WebsiteLead;
use Plugin\Website\Model\WebsiteSite;
use Plugin\Website\Support\WebsiteLeadStatus;
use Psr\SimpleCache\CacheInterface;

/**
 * 官网访客线索服务。
 */
final class WebsiteLeadService extends CoreService
{
    private const PUBLIC_LIMIT = 5;

    private const PUBLIC_LIMIT_TTL = 600;

    public function __construct(
        protected WebsiteLeadMapper $mapper,
        private CacheInterface $cache
    ) {}

    public function createPublic(WebsiteSite $site, array $data, string $ip, string $userAgent): WebsiteLead
    {
        $this->assertPublicRateLimit((int)$site->id, $ip);
        $payload = $this->filterPublicData($data);
        $payload['tenant_id'] = (int)$site->tenant_id;
        $payload['site_id'] = (int)$site->id;
        $payload['ip'] = mb_substr($ip, 0, 60);
        $payload['user_agent'] = mb_substr($userAgent, 0, 500);
        $payload['status'] = WebsiteLeadStatus::PENDING;

        return $this->mapper->create($payload);
    }

    public function handle(int $id, array $data): WebsiteLead
    {
        $lead = $this->mapper->read($id);
        if (!$lead instanceof WebsiteLead) {
            throw new ErrorResponseException('线索不存在');
        }

        $payload = $this->filterHandleData($data);
        $payload['handled_at'] = date('Y-m-d H:i:s');
        try {
            $payload['handled_by'] = (int)(user()?->id ?? 0);
        } catch (\Throwable) {
            $payload['handled_by'] = 0;
        }
        $this->mapper->update($lead, $payload);

        return $this->mapper->read($id) ?: $lead;
    }

    public function changeLeadStatus(int $id, string $status): WebsiteLead
    {
        $lead = $this->mapper->read($id);
        if (!$lead instanceof WebsiteLead) {
            throw new ErrorResponseException('线索不存在');
        }
        if (!WebsiteLeadStatus::isValid($status)) {
            throw new ErrorResponseException('线索状态错误');
        }
        $payload = ['status' => $status];
        if (in_array($status, [WebsiteLeadStatus::HANDLED, WebsiteLeadStatus::INVALID], true)) {
            $payload['handled_at'] = date('Y-m-d H:i:s');
            try {
                $payload['handled_by'] = (int)(user()?->id ?? 0);
            } catch (\Throwable) {
                $payload['handled_by'] = 0;
            }
        }
        $this->mapper->update($lead, $payload);

        return $this->mapper->read($id) ?: $lead;
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        return $this->filterHandleData($data);
    }

    private function filterPublicData(array $data): array
    {
        foreach (['name', 'mobile', 'email', 'company', 'subject', 'content', 'source_url'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        $rules = [
            'name.max:60' => '联系人最多 60 位',
            'mobile.max:30' => '手机号最多 30 位',
            'email.max:120' => '邮箱最多 120 位',
            'email.nullable' => '邮箱格式错误',
            'email.email' => '邮箱格式错误',
            'company.max:120' => '公司名称最多 120 位',
            'subject.max:180' => '咨询主题最多 180 位',
            'content.required' => '咨询内容不能为空',
            'content.max:2000' => '咨询内容最多 2000 位',
            'source_url.max:500' => '来源页面最多 500 位',
        ];
        $payload = _vali($rules, $data);
        if (trim((string)($payload['mobile'] ?? '')) === '' && trim((string)($payload['email'] ?? '')) === '') {
            throw new ErrorResponseException('请至少填写手机号或邮箱');
        }
        if (!empty($payload['mobile']) && !preg_match('/^[0-9+\-\s()]{5,30}$/', (string)$payload['mobile'])) {
            throw new ErrorResponseException('手机号格式错误');
        }
        $payload['subject'] = $payload['subject'] ?? '官网咨询';

        return $payload;
    }

    private function filterHandleData(array $data): array
    {
        foreach (['status', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        $payload = _vali([
            'status.in:pending,processing,handled,invalid' => '线索状态错误',
            'remark.max:1000' => '处理备注最多 1000 位',
        ], $data);
        if (array_key_exists('status', $payload) && !WebsiteLeadStatus::isValid((string)$payload['status'])) {
            throw new ErrorResponseException('线索状态错误');
        }
        if (!array_key_exists('status', $payload)) {
            $payload['status'] = WebsiteLeadStatus::HANDLED;
        }

        return $payload;
    }

    private function assertPublicRateLimit(int $siteId, string $ip): void
    {
        // 公开留言接口没有登录态，按站点和 IP 做短窗口限频；生产多实例应使用共享缓存驱动。
        $key = sprintf('website:lead:%d:%s', $siteId, sha1($ip ?: '0.0.0.0'));
        $count = (int)($this->cache->get($key) ?? 0);
        if ($count >= self::PUBLIC_LIMIT) {
            throw new ErrorResponseException('提交过于频繁，请稍后再试');
        }
        $this->cache->set($key, $count + 1, self::PUBLIC_LIMIT_TTL);
    }
}
