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
use Library\Support\WechatMessageCrypto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Mapper\WechatClientAccountMapper;
use Plugin\WechatClient\Model\WechatClientAccount;
use Plugin\WechatClient\Provider;
use Plugin\WechatClient\Service\WechatClientAccountService;
use Plugin\WechatClient\Support\SdkRequest;
use Plugin\WechatClient\Support\Secret;
use Psr\Http\Message\RequestInterface;
use We\Common\Exception\InvalidCallException;
use We\Common\Transport\HttpTransportInterface;
use We\Wechat\Common\NullCacheStore;
use We\Wechat\Common\StoreCacheInterface;

/** @internal */
#[CoversClass(SdkRequest::class)]
#[CoversClass(WechatClientAccountService::class)]
final class SdkCompatibilityTest extends TestCase
{
    public function testPluginRequiresWechatMessageCryptoCapability(): void
    {
        $path = dirname((new \ReflectionClass(Provider::class))->getFileName(), 2) . '/composer.json';
        self::assertFileExists($path);
        $composer = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('^1.0', $composer['require']['zoujingli/smart-admin-wechat-message-crypto'] ?? null);
    }

    #[DataProvider('accountTypes')]
    public function testChannelUsesTokenAndKeepsArrayResponse(string $type): void
    {
        $calls = [];
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::exactly(2))->method('send')->willReturnCallback(function (RequestInterface $request, int $timeout) use (&$calls): Response {
            $calls[] = $request;
            if (count($calls) === 1) {
                self::assertSame('/cgi-bin/token', $request->getUri()->getPath());
                return new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"mock-token","expires_in":7200}');
            }
            self::assertSame('GET', $request->getMethod());
            parse_str($request->getUri()->getQuery(), $query);
            self::assertSame(['next_openid' => 'o+1', 'access_token' => 'mock-token'], $query);
            self::assertSame('', (string)$request->getBody());
            self::assertSame(1500, $timeout);
            return new Response(200, ['Content-Type' => 'application/json'], '{"data":{"openid":["o1"]}}');
        });
        $service = new WechatClientAccountService(new WechatClientAccountMapper(), $transport, new NullCacheStore());
        self::assertSame(['data' => ['openid' => ['o1']]], $service->officialRequest($this->account($type), 'cgi-bin/user/get', ['next_openid' => 'o+1'], ' get ', ['timeout' => 1.5]));
    }

    public static function accountTypes(): array
    {
        return [['official_account'], ['mini_program']];
    }

    public function testMultipartIsSentAsFileInsteadOfJson(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'fixture-image');
        rewind($stream);
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::once())->method('send')->willReturnCallback(function (RequestInterface $request): Response {
            self::assertSame('POST', $request->getMethod());
            self::assertSame('type=image', $request->getUri()->getQuery());
            self::assertStringContainsString('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
            $body = (string)$request->getBody();
            self::assertStringContainsString('name="media"; filename="fixture.jpg"', $body);
            self::assertStringContainsString('Content-Type: image/jpeg', $body);
            self::assertStringContainsString('fixture-image', $body);
            return new Response(200, ['Content-Type' => 'application/json'], '{"media_id":"media-1"}');
        });
        $service = new WechatClientAccountService(new WechatClientAccountMapper(), $transport, new NullCacheStore());
        try {
            self::assertSame(['media_id' => 'media-1'], $service->officialRequest($this->account(), 'cgi-bin/material/add_material', [], 'POST', [
                'anonymous' => true,
                'query' => ['type' => 'image'],
                'multipart' => [['name' => 'media', 'contents' => $stream, 'filename' => 'fixture.jpg', 'headers' => ['Content-Type' => 'image/jpeg']]],
            ]));
            self::assertIsResource($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testJsonPostKeepsQuerySeparate(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::once())->method('send')->willReturnCallback(function (RequestInterface $request): Response {
            self::assertSame('lang=zh_CN', $request->getUri()->getQuery());
            self::assertSame(['button' => [['name' => '菜单']]], json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR));
            return new Response(200, ['Content-Type' => 'application/json'], '{"errcode":0}');
        });
        $service = new WechatClientAccountService(new WechatClientAccountMapper(), $transport, new NullCacheStore());
        self::assertSame(['errcode' => 0], $service->officialRequest($this->account(), 'cgi-bin/menu/create', ['button' => [['name' => '菜单']]], 'POST', ['query' => ['lang' => 'zh_CN'], 'anonymous' => true]));
    }

    public function testCallbackNeedsNoAppSecretOrNetwork(): void
    {
        $account = $this->account();
        $account->appsecret = '';
        $account->token = Secret::encrypt('callback-token');
        $account->encodingaeskey = Secret::encrypt(rtrim(base64_encode(str_repeat('k', 32)), '='));
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::never())->method('send');
        $service = new WechatClientAccountService(new WechatClientAccountMapper(), $transport);
        $reply = $service->officialRequest($account, 'encrypt_message', ['body' => '<xml><Content>hello</Content></xml>', 'timestamp' => '12345', 'nonce' => 'nonce']);
        $fields = WechatMessageCrypto::decodeXml($reply['xml']);
        self::assertSame(['Content' => 'hello'], $service->officialRequest($account, 'decrypt_message', ['body' => $reply['xml'], 'msg_signature' => $fields['MsgSignature'], 'timestamp' => '12345', 'nonce' => 'nonce']));
    }

    public function testTokenCannotBeInjectedByCaller(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::never())->method('send');
        $service = new WechatClientAccountService(new WechatClientAccountMapper(), $transport, new NullCacheStore());
        $this->expectException(InvalidCallException::class);
        $service->officialRequest($this->account(), 'cgi-bin/user/get', ['access_token' => 'untrusted'], 'GET');
    }

    public function testUnsafeTransportOptionIsRejected(): void
    {
        $this->expectException(ErrorResponseException::class);
        SdkRequest::make('cgi-bin/user/get', [], 'GET', ['sink' => '/tmp/forbidden']);
    }

    public function testSameAppidKeepsTenantTokenCachesIsolated(): void
    {
        $keys = [];
        $cache = $this->createMock(StoreCacheInterface::class);
        $cache->expects(self::exactly(2))->method('get')->willReturnCallback(static function (string $key) use (&$keys): string {
            $keys[] = $key;

            return 'cached-token';
        });
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects(self::exactly(2))->method('send')->willReturnCallback(static fn (): Response => new Response(200, ['Content-Type' => 'application/json'], '{}'));
        $service = new WechatClientAccountService(new WechatClientAccountMapper(), $transport, $cache);
        $account = $this->account();
        $service->officialRequest($account, 'cgi-bin/user/get', [], 'GET');
        $account->tenant_id = 8;
        $service->officialRequest($account, 'cgi-bin/user/get', [], 'GET');
        self::assertNotSame($keys[0], $keys[1]);
    }

    private function account(string $type = 'official_account'): WechatClientAccount
    {
        return new WechatClientAccount(['id' => 1, 'tenant_id' => 7, 'appid' => 'wx-fixture', 'appsecret' => Secret::encrypt('fixture-secret'), 'account_type' => $type, 'service_mode' => 0, 'status' => 1]);
    }
}
