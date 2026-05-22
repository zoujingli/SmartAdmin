<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Service;

use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatClient\Mapper\WechatClientPaymentMerchantMapper;
use Plugin\WechatClient\Model\WechatClientPaymentMerchant;
use Plugin\WechatClient\Support\Secret;
use We\Client as WechatSdkClient;
use We\Config\WechatPaymentConfig;
use We\Support\Signature;

/**
 * 微信支付商户服务。
 *
 * 负责商户配置的校验、敏感字段加解密，以及封装微信支付 SDK 的统一请求入口。
 */
final class WechatClientPaymentMerchantService extends CoreService
{
    /**
     * 需要加密保存的商户敏感字段，返回列表时也会被模型隐藏。
     */
    private const SECRET_FIELDS = ['api_v3_key', 'merchant_serial', 'merchant_private_key', 'platform_public_key', 'platform_serial'];

    public function __construct(
        protected WechatClientPaymentMerchantMapper $mapper
    ) {}

    /**
     * 微信支付统一网关调用入口：按 URI + 参数请求。
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function paymentRequest(
        int|WechatClientPaymentMerchant $merchant,
        string $uriOrPath,
        array $params = [],
        string $httpMethod = 'POST',
        array $options = [],
    ): array
    {
        $merchant = $this->requireMerchant($merchant);
        $config = new WechatPaymentConfig(
            (string)$merchant->appid,
            (string)$merchant->mch_id,
            Secret::decrypt((string)$merchant->api_v3_key),
            Secret::decrypt((string)$merchant->merchant_serial),
            Secret::decrypt((string)$merchant->merchant_private_key),
            '',
            // 平台公钥与平台序列号分别传入，避免回调验签时误把序列号当公钥使用。
            Secret::decrypt((string)$merchant->platform_public_key),
            Secret::decrypt((string)$merchant->platform_serial),
        );

        $payment = $this->sdkClient()->wechatPayment($config);
        $method = strtoupper(trim($httpMethod) === '' ? 'POST' : $httpMethod);

        return match ($method) {
            'GET' => $payment->get($uriOrPath, $params, $options),
            'POST' => $payment->post($uriOrPath, $params, $options),
            default => $payment->call($uriOrPath, $params, $method, $options),
        };
    }

    /**
     * 获取并校验可用的微信支付商户。
     */
    public function requireMerchant(int|WechatClientPaymentMerchant $merchant): WechatClientPaymentMerchant
    {
        if ($merchant instanceof WechatClientPaymentMerchant) {
            if (!Status::isEnabled((int)$merchant->status)) {
                throw new ErrorResponseException('微信支付商户不可用');
            }

            return $merchant;
        }
        $model = $this->mapper->read($merchant);
        if (!$model instanceof WechatClientPaymentMerchant || !Status::isEnabled((int)$model->status)) {
            throw new ErrorResponseException('微信支付商户不可用');
        }

        return $model;
    }

    /**
     * 回调场景按商户 ID 获取商户。
     *
     * 微信通知没有后台登录态，必须绕过租户全局作用域，再由商户归属恢复租户上下文。
     */
    public function requireMerchantForCallback(int $id): WechatClientPaymentMerchant
    {
        $model = $this->mapper->findForCallback($id);
        if (!$model instanceof WechatClientPaymentMerchant || !Status::isEnabled((int)$model->status)) {
            throw new ErrorResponseException('微信支付商户不可用');
        }

        return $model;
    }

    /**
     * 解析业务调用使用的默认商户。
     *
     * 传入 merchant_id 时精确使用该商户；未传时取当前租户第一条启用商户。
     */
    public function defaultMerchant(int $merchantId = 0): WechatClientPaymentMerchant
    {
        if ($merchantId > 0) {
            return $this->requireMerchant($merchantId);
        }
        if (tenant_id() <= 0) {
            // 未指定商户且没有明确租户上下文时必须拒绝，避免平台空间或 CLI 场景误取其它租户第一条商户发起真实扣款。
            throw new ErrorResponseException('微信支付商户不可用');
        }

        $query = WechatClientPaymentMerchant::query()
            ->where('status', Status::ENABLED)
            ->where('tenant_id', tenant_id())
            ->orderBy('id');
        $model = $query->first();
        if (!$model instanceof WechatClientPaymentMerchant) {
            throw new ErrorResponseException('微信支付商户不可用');
        }

        return $model;
    }

    /**
     * 生成 JSAPI 前端调起参数；微信下单接口返回 prepay_id，签名需由商户私钥本地生成。
     *
     * @return array<string,string>
     */
    public function makeJsapiPaymentParams(WechatClientPaymentMerchant $merchant, string $prepaymentId): array
    {
        $prepaymentId = trim($prepaymentId);
        if ($prepaymentId === '') {
            return [];
        }

        $timeStamp = (string)time();
        $nonceStr = bin2hex(random_bytes(16));
        $package = 'prepay_id=' . $prepaymentId;
        $message = implode("\n", [(string)$merchant->appid, $timeStamp, $nonceStr, $package]) . "\n";

        return [
            'appId' => (string)$merchant->appid,
            'timeStamp' => $timeStamp,
            'nonceStr' => $nonceStr,
            'package' => $package,
            'signType' => 'RSA',
            // 微信 JSAPI 官方字段名固定为 paySign；本地签名方法使用 Payment 命名，避免 SDK 公开 API 出现缩写。
            'paySign' => Signature::paymentV3Sign(Secret::decrypt((string)$merchant->merchant_private_key), $message),
        ];
    }

    /**
     * 保存商户前统一校验字段，并对敏感配置加密。
     *
     * 更新时如果前端传回掩码值，表示保持原密文，不重新加密。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        $secrets = [];
        foreach (self::SECRET_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $secrets[$field] = $data[$field];
            }
        }
        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'account_id.integer' => '接口账号 ID 必须为数字',
            'appid.filled' => '支付 AppID 不能为空',
            'appid.max:64' => '支付 AppID 最多 64 位',
            'mch_id.filled' => '商户号不能为空',
            'mch_id.max:64' => '商户号最多 64 位',
            'name.filled' => '商户名称不能为空',
            'name.max:120' => '商户名称最多 120 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['appid.required'] = '支付 AppID 不能为空';
            $rules['mch_id.required'] = '商户号不能为空';
            $rules['name.required'] = '商户名称不能为空';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        if (array_key_exists('mch_id', $data) && $this->mapper->existsByMchId((string)$data['mch_id'], (int)($exists['id'] ?? 0))) {
            throw new ErrorResponseException('微信支付商户号已存在');
        }
        foreach (['tenant_id', 'account_id', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }
        foreach ($secrets as $field => $value) {
            if (!Secret::isMask($value)) {
                $data[$field] = Secret::encrypt((string)$value);
            }
        }

        return $data;
    }

    /**
     * 创建微信 SDK 客户端实例，便于后续替换或测试隔离。
     */
    private function sdkClient(): WechatSdkClient
    {
        return new WechatSdkClient();
    }
}
