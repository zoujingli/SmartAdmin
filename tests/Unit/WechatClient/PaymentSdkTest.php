<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\WechatClient;

use GuzzleHttp\Psr7\Response;
use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Mapper\WechatClientPaymentMerchantMapper;
use Plugin\WechatClient\Model\WechatClientPaymentMerchant;
use Plugin\WechatClient\Service\WechatClientPaymentMerchantService;
use Plugin\WechatClient\Support\PaymentNotification;
use Plugin\WechatClient\Support\Secret;
use Psr\Http\Message\RequestInterface;
use We\Common\Exception\SignatureException;
use We\Common\Transport\HttpTransportInterface;

/** @internal */
#[CoversClass(PaymentNotification::class)]
#[CoversClass(WechatClientPaymentMerchantService::class)]
final class PaymentSdkTest extends TestCase
{
    private static string $privateKey = '';

    private static string $publicKey = '';

    public static function setUpBeforeClass(): void
    {
        // 临时 RSA 夹具仅在进程内生成，不读取真实商户密钥。
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => __DIR__ . '/fixtures/openssl.cnf'];
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, self::$privateKey, null, $options);
        self::$publicKey = openssl_pkey_get_details($key)['key'];
    }

    public function testSignedPaymentRequestAndResponse(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::once())->method('send')->willReturnCallback(function (RequestInterface $request): Response {
            self::assertSame('/v3/refund/domestic/refunds', $request->getUri()->getPath());
            self::assertSame(['out_refund_no' => 'REFUND-1'], json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR));
            preg_match_all('/([a-z_]+)="([^"]+)"/', $request->getHeaderLine('Authorization'), $matches, PREG_SET_ORDER);
            $auth = array_column($matches, 2, 1);
            self::assertSame('merchant-serial', $auth['serial_no']);
            self::assertSame('1900000001', $auth['mchid']);
            $signed = 'POST' . "\n" . $request->getRequestTarget() . "\n" . $auth['timestamp'] . "\n" . $auth['nonce_str'] . "\n" . $request->getBody() . "\n";
            self::assertSame(1, openssl_verify($signed, base64_decode($auth['signature']), self::$publicKey, OPENSSL_ALGO_SHA256));
            $body = '{"refund_id":"refund-1"}';
            return new Response(200, $this->signedHeaders($body) + ['Content-Type' => 'application/json'], $body);
        });
        $service = new WechatClientPaymentMerchantService(new WechatClientPaymentMerchantMapper(), $transport);
        self::assertSame(['refund_id' => 'refund-1'], $service->paymentRequest($this->merchant(), 'v3/refund/domestic/refunds', ['out_refund_no' => 'REFUND-1']));
    }

    public function testUnsignedPaymentResponseFailsClosed(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::once())->method('send')->willReturn(new Response(200, ['Content-Type' => 'application/json'], '{}'));
        $service = new WechatClientPaymentMerchantService(new WechatClientPaymentMerchantMapper(), $transport);
        $this->expectException(SignatureException::class);
        $service->paymentRequest($this->merchant(), 'v3/pay/transactions/out-trade-no/PAY-1', ['mchid' => '1900000001'], 'GET');
    }

    public function testSignedNoContentResponseReturnsEmptyArray(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::once())->method('send')->willReturn(new Response(204, $this->signedHeaders('')));
        $service = new WechatClientPaymentMerchantService(new WechatClientPaymentMerchantMapper(), $transport);
        self::assertSame([], $service->paymentRequest($this->merchant(), 'v3/pay/transactions/out-trade-no/PAY-1/close', ['mchid' => '1900000001']));
    }

    public function testNotificationUsesRawBytesAndNeedsNoMerchantPrivateKey(): void
    {
        $body = $this->notification();
        $merchant = $this->merchant();
        $merchant->merchant_private_key = '';
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::never())->method('send');
        $service = new WechatClientPaymentMerchantService(new WechatClientPaymentMerchantMapper(), $transport);
        self::assertSame(['appid' => 'wx-fixture', 'mchid' => '1900000001', 'trade_state' => 'SUCCESS'], $service->paymentRequest($merchant, 'decrypt_notification', [], 'POST', ['headers' => $this->signedHeaders($body), 'raw_body' => $body, 'body' => ['ignored' => true]]));
    }

    #[DataProvider('invalidNotifications')]
    public function testInvalidNotificationIsRejected(string $mode): void
    {
        $body = $this->notification();
        $headers = $this->signedHeaders($body, $mode === 'expired' ? (string)(time() - 601) : null);
        $key = str_repeat('k', 32);
        if ($mode === 'tampered') {
            $body .= ' ';
        } elseif ($mode === 'serial') {
            $headers['Wechatpay-Serial'] = 'unknown';
        } elseif ($mode === 'probe') {
            $headers['Wechatpay-Signature'] = 'WECHATPAY/SIGNTEST/invalid';
        } elseif ($mode === 'wrong-key') {
            $key = str_repeat('x', 32);
        } elseif ($mode === 'algorithm') {
            $body = str_replace('AEAD_AES_256_GCM', 'AES-CBC', $body);
            $headers = $this->signedHeaders($body);
        }
        $this->expectException(ErrorResponseException::class);
        PaymentNotification::decrypt($headers, $body, $key, 'platform-serial', self::$publicKey);
    }

    public static function invalidNotifications(): array
    {
        return [['tampered'], ['serial'], ['expired'], ['probe'], ['wrong-key'], ['algorithm']];
    }

    public function testJsapiSignatureKeepsOfficialFields(): void
    {
        $service = new WechatClientPaymentMerchantService(new WechatClientPaymentMerchantMapper());
        $result = $service->makeJsapiPaymentParams($this->merchant(), 'prepay-1');
        self::assertSame('RSA', $result['signType']);
        self::assertSame('prepay_id=prepay-1', $result['package']);
        $message = implode("\n", [$result['appId'], $result['timeStamp'], $result['nonceStr'], $result['package']]) . "\n";
        self::assertSame(1, openssl_verify($message, base64_decode($result['paySign']), self::$publicKey, OPENSSL_ALGO_SHA256));
    }

    private function notification(): string
    {
        $plain = '{"appid":"wx-fixture","mchid":"1900000001","trade_state":"SUCCESS"}';
        $cipher = openssl_encrypt($plain, 'AES-256-GCM', str_repeat('k', 32), OPENSSL_RAW_DATA, '123456789012', $tag, 'transaction');
        return json_encode(['resource' => ['algorithm' => 'AEAD_AES_256_GCM', 'ciphertext' => base64_encode($cipher . $tag), 'nonce' => '123456789012', 'associated_data' => 'transaction']], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    private function signedHeaders(string $body, ?string $timestamp = null): array
    {
        $timestamp ??= (string)time();
        openssl_sign($timestamp . "\nnonce\n" . $body . "\n", $signature, self::$privateKey, OPENSSL_ALGO_SHA256);
        return ['Wechatpay-Serial' => 'platform-serial', 'Wechatpay-Timestamp' => $timestamp, 'Wechatpay-Nonce' => 'nonce', 'Wechatpay-Signature' => base64_encode($signature)];
    }

    private function merchant(): WechatClientPaymentMerchant
    {
        return new WechatClientPaymentMerchant(['id' => 1, 'tenant_id' => 7, 'appid' => 'wx-fixture', 'mch_id' => '1900000001', 'status' => 1, 'api_v3_key' => Secret::encrypt(str_repeat('k', 32)), 'merchant_serial' => Secret::encrypt('merchant-serial'), 'merchant_private_key' => Secret::encrypt(self::$privateKey), 'platform_serial' => Secret::encrypt('platform-serial'), 'platform_public_key' => Secret::encrypt(self::$publicKey)]);
    }
}
