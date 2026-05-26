<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Service;

use GuzzleHttp\Client as GuzzleClient;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatClient\Mapper\WechatClientMediaMapper;
use Plugin\WechatClient\Model\WechatClientMedia;
use System\Service\FileService;

/**
 * 微信素材服务。
 *
 * 负责本地素材记录维护、官方永久素材同步，以及将本地文件上传到微信永久素材库。
 */
final class WechatClientMediaService extends CoreService
{
    private const TYPES = ['image', 'voice', 'video', 'news'];

    public function __construct(
        protected WechatClientMediaMapper $mapper,
        private readonly WechatClientAccountService $accounts,
        private readonly FileService $files,
    ) {}

    /**
     * 从微信官方永久素材列表同步到本地；仅同步摘要字段，官方原始项保留到 raw_payload。
     */
    public function sync(int $accountId, string $type = 'image', int $maxPages = 5): array
    {
        $account = $this->accounts->requireAccount($accountId);
        $type = $this->normalizeType($type);
        $total = 0;
        $pages = max(1, min(20, $maxPages));

        for ($page = 0; $page < $pages; ++$page) {
            $result = $this->accounts->officialRequest($account, 'cgi-bin/material/batchget_material', [
                'type' => $type,
                'offset' => $page * 20,
                'count' => 20,
            ]);
            $items = (array)($result['item'] ?? []);
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $mediaId = trim((string)($item['media_id'] ?? ''));
                if ($mediaId === '') {
                    continue;
                }
                $content = is_array($item['content'] ?? null) ? $item['content'] : [];
                $newsItem = is_array(($content['news_item'] ?? [])[0] ?? null) ? $content['news_item'][0] : [];
                $this->mapper->upsertByMediaId((int)$account->id, $mediaId, [
                    'tenant_id' => (int)$account->tenant_id,
                    'account_id' => (int)$account->id,
                    'media_id' => $mediaId,
                    'media_type' => $type,
                    'name' => (string)($item['name'] ?? $newsItem['title'] ?? $mediaId),
                    'url' => (string)($item['url'] ?? $newsItem['url'] ?? $newsItem['thumb_url'] ?? ''),
                    'raw_payload' => $item,
                    'status' => Status::ENABLED,
                ]);
                ++$total;
            }
            if (count($items) < 20) {
                break;
            }
        }

        return ['synced' => $total, 'type' => $type];
    }

    /**
     * 将本地素材关联的文件上传为微信永久素材；开放平台网关模式不传输本地文件流，直接拒绝。
     */
    public function uploadPermanent(int $id): WechatClientMedia
    {
        $media = $this->mapper->read($id);
        if (!$media instanceof WechatClientMedia) {
            throw new ErrorResponseException('素材不存在');
        }

        $account = $this->accounts->requireAccount((int)$media->account_id);
        if ((int)$account->service_mode === 1) {
            throw new ErrorResponseException('开放平台网关模式暂不支持本地文件流上传，请先在微信后台上传后同步素材');
        }

        $type = $this->normalizeType((string)$media->media_type);
        if ($type === 'news') {
            throw new ErrorResponseException('图文素材请在文章管理中上传草稿和发布');
        }

        [$path, $cleanup] = $this->resolveUploadPath($media);
        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            $cleanup();
            throw new ErrorResponseException('素材文件不可读');
        }

        $multipart = [[
            'name' => 'media',
            'contents' => $handle,
            'filename' => basename($path),
        ]];
        if ($type === 'video') {
            // 微信永久视频素材上传要求额外提交 description，首版使用素材名称生成标题和简介，避免接口因缺少描述拒绝。
            $multipart[] = [
                'name' => 'description',
                'contents' => json_encode([
                    'title' => (string)$media->name,
                    'introduction' => (string)$media->name,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            ];
        }

        try {
            $result = $this->accounts->officialRequest($account, 'cgi-bin/material/add_material', [], 'POST', [
                'query' => ['type' => $type],
                'multipart' => $multipart,
            ]);
        } finally {
            is_resource($handle) && fclose($handle);
            $cleanup();
        }

        $this->mapper->update($media, [
            'media_id' => (string)($result['media_id'] ?? $media->media_id),
            'url' => (string)($result['url'] ?? $media->url),
            'raw_payload' => $result,
        ]);

        return $this->mapper->read($id);
    }

    /**
     * 保存素材前校验本地字段；media_id 可为空，表示稍后上传或同步官方素材。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['media_id', 'media_type', 'name', 'url', 'file_url'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'account_id.integer' => '接口账号 ID 必须为数字',
            'account_id.min:1' => '接口账号不能为空',
            'media_id.max:180' => 'MediaID 最多 180 位',
            'media_type.in:image,voice,video,news' => '素材类型错误',
            'name.filled' => '素材名称不能为空',
            'name.max:180' => '素材名称最多 180 位',
            'url.max:500' => '素材地址最多 500 位',
            'file_id.integer' => '本地文件 ID 必须为数字',
            'file_id.min:0' => '本地文件 ID 不能小于 0',
            'file_url.max:500' => '文件地址最多 500 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['account_id.required'] = '接口账号不能为空';
            $rules['media_type.default'] = 'image';
            $rules['name.required'] = '素材名称不能为空';
            $rules['file_id.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['tenant_id', 'account_id', 'file_id', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw new ErrorResponseException('素材类型错误');
        }

        return $type;
    }

    /**
     * 解析上传文件路径；远程 URL 会下载到临时文件，返回清理闭包避免泄露临时资源。
     * 本地文件必须通过系统文件 ID 解析，禁止表单直接传服务器路径，避免素材上传权限被用来读取任意本地文件。
     *
     * @return array{0:string,1:callable():void}
     */
    private function resolveUploadPath(WechatClientMedia $media): array
    {
        $fileUrl = trim((string)$media->file_url);
        if ((int)$media->file_id > 0) {
            $target = $this->files->getDownloadTarget((int)$media->file_id);
            if (($target['type'] ?? '') === 'local' && is_file((string)($target['path'] ?? ''))) {
                return [(string)$target['path'], static function (): void {}];
            }
            $fileUrl = trim((string)($target['url'] ?? $fileUrl));
        }
        if ($fileUrl === '') {
            throw new ErrorResponseException('请先配置本地文件 ID 或文件地址');
        }

        if (!preg_match('#^https?://#i', $fileUrl)) {
            throw new ErrorResponseException('文件地址必须是可访问的 http(s) URL，服务器本地文件请使用本地文件 ID');
        }
        $this->assertPublicRemoteUrl($fileUrl);

        $tmp = tempnam(sys_get_temp_dir(), 'wcli_media_');
        if (!is_string($tmp)) {
            throw new ErrorResponseException('创建临时文件失败');
        }

        try {
            $response = (new GuzzleClient(['timeout' => 30.0]))->get($fileUrl, [
                'allow_redirects' => false,
                'sink' => $tmp,
            ]);
            // 禁止跟随 3xx 跳转，避免外部 URL 二次跳转到 localhost/内网造成 SSRF；只接受直接 2xx 素材响应。
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || !is_file($tmp) || filesize($tmp) <= 0) {
                throw new ErrorResponseException('下载素材文件失败');
            }
        } catch (\Throwable $throwable) {
            is_file($tmp) && unlink($tmp);
            throw new ErrorResponseException('下载素材文件失败：' . $throwable->getMessage());
        }

        return [$tmp, static function () use ($tmp): void {
            is_file($tmp) && unlink($tmp);
        }];
    }

    /**
     * 校验远程素材地址不能指向本机或内网。
     *
     * 素材上传会由服务器主动下载 file_url；如果允许 localhost、私有网段或链路本地地址，
     * 后台上传权限会变成 SSRF 能力。需要访问服务器本地文件时应使用系统文件 ID。
     */
    private function assertPublicRemoteUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || trim($host) === '') {
            throw new ErrorResponseException('文件地址必须是可访问的 http(s) URL，服务器本地文件请使用本地文件 ID');
        }

        $host = trim($host, '[]');
        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            throw new ErrorResponseException('文件地址不能指向本机或内网地址');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHostIps($host);
        if ($ips === []) {
            // DNS 无法解析时不能放行给 Guzzle 二次解析，否则会从“已验证公网”退化为 fail-open SSRF。
            throw new ErrorResponseException('文件地址无法解析或不可访问');
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp((string)$ip)) {
                throw new ErrorResponseException('文件地址不能指向本机或内网地址');
            }
        }
    }

    /**
     * 解析域名的 A/AAAA 记录；同时覆盖 IPv4 与 IPv6，避免 IPv6 内网地址绕过。
     *
     * @return array<int,string>
     */
    private function resolveHostIps(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach ((array)@dns_get_record($host, DNS_A + DNS_AAAA) as $record) {
                if (is_array($record) && isset($record['ip'])) {
                    $ips[] = (string)$record['ip'];
                }
                if (is_array($record) && isset($record['ipv6'])) {
                    $ips[] = (string)$record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * 判断 IP 是否为公网地址；IPv4/IPv6 私有、保留、环回和链路本地地址均拒绝。
     */
    private function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
