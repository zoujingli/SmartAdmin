<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Service;

use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Helper\CoderHelper;
use Plugin\WechatService\Mapper\WechatServiceGatewayMapper;
use Plugin\WechatService\Model\WechatServiceGateway;
use Plugin\WechatService\Support\GatewayToken;
use Plugin\WechatService\Support\Secret;

final class WechatServiceGatewayService extends CoreService
{
    public function __construct(
        protected WechatServiceGatewayMapper $mapper
    ) {}

    /**
     * 创建网关凭据时仅本次返回明文 secret，后续只能轮换。
     *
     * @return array<string,mixed>
     */
    public function createCredential(array $data): array
    {
        $secret = CoderHelper::genRandCode(48, 3);
        // 前端允许调用 Key 留空，服务端生成不可猜测的稳定 key 并只返回一次明文 secret。
        if (trim((string)($data['client_key'] ?? '')) === '') {
            $data['client_key'] = 'wsg_' . CoderHelper::genRandCode(24, 3);
        }
        $data['client_secret'] = $secret;
        /** @var WechatServiceGateway $model */
        $model = $this->create($data);
        $result = $model->toArray();
        $result['client_secret'] = $secret;

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function rotateSecret(int $id): array
    {
        $model = $this->mapper->read($id, isScope: false);
        if (!$model instanceof WechatServiceGateway) {
            throw new ErrorResponseException('网关凭据不存在');
        }

        $secret = CoderHelper::genRandCode(48, 3);
        $this->mapper->update($model, ['client_secret' => Secret::encrypt($secret)]);
        $result = $this->mapper->read($id, isScope: false)->toArray();
        $result['client_secret'] = $secret;

        return $result;
    }

    /**
     * 校验 JSON-RPC token，返回调用身份与目标 appid。
     *
     * @return array{class:string,appid:string,key:string}
     */
    public function verifyToken(string $token): array
    {
        $payload = GatewayToken::decode($token);
        $clientKey = $payload['key'];
        $appid = $payload['appid'];
        $class = $payload['class'];
        GatewayToken::assertClientClass($class);

        $credential = $this->mapper->findByClientKey($clientKey);
        if (!$credential instanceof WechatServiceGateway || !Status::isEnabled((int)$credential->status)) {
            throw new ErrorResponseException('网关凭据不可用');
        }

        $secret = Secret::decrypt((string)$credential->client_secret);
        GatewayToken::assertSignature($payload, $secret);

        $allowed = (array)$credential->allowed_appids;
        GatewayToken::assertAllowedAppid($allowed, $appid);

        $this->mapper->incrementTotal($clientKey);

        return ['class' => $class, 'appid' => $appid, 'key' => $clientKey];
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $allowedAppids = $data['allowed_appids'] ?? null;
        $rules = [
            'client_key.filled' => '调用 Key 不能为空',
            'client_key.max:80' => '调用 Key 最多 80 位',
            'client_secret.max:255' => '调用 Secret 最多 255 位',
            'name.max:100' => '凭据名称最多 100 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['client_key.required'] = '调用 Key 不能为空';
            $rules['name.default'] = '接口网关凭据';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        if (array_key_exists('client_key', $data) && $this->mapper->existsByClientKey((string)$data['client_key'], (int)($exists['id'] ?? 0))) {
            throw new ErrorResponseException('网关调用 Key 已存在');
        }
        if ($allowedAppids !== null) {
            $data['allowed_appids'] = $allowedAppids;
        }
        if (array_key_exists('client_secret', $data) && !Secret::isMask($data['client_secret'])) {
            $data['client_secret'] = Secret::encrypt((string)$data['client_secret']);
        }
        if (array_key_exists('client_secret', $data) && Secret::isMask($data['client_secret'])) {
            // 掩码只表示“保持原密钥”，不能把 ****** 写回数据库导致网关凭据失效。
            unset($data['client_secret']);
        }
        if (array_key_exists('allowed_appids', $data)) {
            $data['allowed_appids'] = $this->normalizeAllowedAppids($data['allowed_appids']);
        }
        if (array_key_exists('status', $data)) {
            $data['status'] = (int)$data['status'];
        }

        return $data;
    }

    /**
     * 规范化网关 AppID 白名单：只接受标量字符串，去重并限制单项长度，避免异常结构进入 JSON 字段。
     *
     * @return array<int,string>
     */
    private function normalizeAllowedAppids(mixed $value): array
    {
        $items = is_array($value)
            ? $value
            : preg_split('/[\s,，]+/u', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($items ?: [] as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $appid = trim((string)$item);
            if ($appid === '') {
                continue;
            }
            if (mb_strlen($appid) > 64) {
                throw new ErrorResponseException('允许 AppID 最多 64 位');
            }
            $result[$appid] = $appid;
        }
        if (count($result) > 200) {
            throw new ErrorResponseException('允许 AppID 最多 200 个');
        }

        return array_values($result);
    }
}
