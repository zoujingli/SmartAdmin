<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Helper;

use GuzzleHttp\Psr7\ServerRequest;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\Helper\RequestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(RequestHelper::class)]
final class RequestHelperContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy(ServerRequestInterface::class);
        Context::destroy(RequestInterface::class);
        $this->setIp2Region(null);
    }

    public function testGetRequestReadsCurrentCoroutineContext(): void
    {
        $request = new ServerRequest('GET', 'https://admin.example.com/system');

        Context::set(ServerRequestInterface::class, $request);

        $this->assertSame($request, RequestHelper::getRequest());
    }

    public function testExplicitRequestHasPriorityOverContextRequest(): void
    {
        Context::set(
            ServerRequestInterface::class,
            new ServerRequest('GET', 'https://context.example.com/system', ['Host' => 'context.example.com'])
        );
        $explicit = new ServerRequest('GET', 'https://explicit.example.com/system', ['Host' => 'explicit.example.com']);

        $this->assertSame('explicit.example.com', RequestHelper::getDomain($explicit));
        $this->assertSame('https://explicit.example.com/a', RequestHelper::url('/a', [], RequestHelper::URL_HTTP, $explicit));
    }

    public function testMultipleExplicitRequestsDoNotPolluteEachOther(): void
    {
        $first = new ServerRequest('GET', 'https://one.example.com/system', [
            'Host' => 'one.example.com',
            'X-Port' => '8443',
        ]);
        $second = new ServerRequest('GET', 'http://two.example.com/system', [
            'Host' => 'two.example.com:8080',
        ]);

        $this->assertSame('https://one.example.com:8443', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $first));
        $this->assertSame('http://two.example.com:8080', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $second));
        $this->assertSame('https://one.example.com:8443', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $first));
    }

    public function testCustomProxyHeadersHaveHighestPriority(): void
    {
        $request = new ServerRequest('GET', 'http://internal.local/system', [
            'Host' => 'internal.local:8080',
            'Forwarded' => 'for=198.51.100.1;proto=http;host=forwarded.example.com:9000',
            'X-Forwarded-Proto' => 'http',
            'X-Forwarded-Host' => 'proxy.example.com:8081',
            'X-Forwarded-Port' => '8081',
            'X-Host' => 'admin.example.com',
            'X-Scheme' => 'https',
            'X-Port' => '8443',
        ]);

        $this->assertSame('https', RequestHelper::getScheme(RequestHelper::URL_HTTP, $request));
        $this->assertSame('admin.example.com', RequestHelper::getDomain($request));
        $this->assertSame(8443, RequestHelper::getPort($request));
        $this->assertSame('https://admin.example.com:8443', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $request));
    }

    public function testForwardedHeaderParsesForProtoAndHost(): void
    {
        $request = new ServerRequest('GET', 'http://internal.local/system', [
            'Forwarded' => 'for=203.0.113.7;proto=https;host=admin.example.com:9443',
        ], null, '1.1', ['remote_addr' => '10.0.0.1']);

        $this->assertSame('203.0.113.7', RequestHelper::getClientIp($request));
        $this->assertSame('https', RequestHelper::getScheme(RequestHelper::URL_HTTP, $request));
        $this->assertSame('admin.example.com', RequestHelper::getDomain($request));
        $this->assertSame(9443, RequestHelper::getPort($request));
        $this->assertSame('https://admin.example.com:9443', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $request));
    }

    public function testForwardedHeadersFallbackParsesSchemeDomainPortAndIp(): void
    {
        $request = new ServerRequest('GET', 'http://internal.local/system', [
            'X-Forwarded-For' => '203.0.113.8, 10.0.0.1',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'admin.example.com',
            'X-Forwarded-Port' => '9443',
        ], null, '1.1', ['remote_addr' => '10.0.0.1']);

        $this->assertSame('203.0.113.8', RequestHelper::getClientIp($request));
        $this->assertSame('https', RequestHelper::getScheme(RequestHelper::URL_HTTP, $request));
        $this->assertSame('admin.example.com', RequestHelper::getDomain($request));
        $this->assertSame(9443, RequestHelper::getPort($request));
        $this->assertSame('https://admin.example.com:9443', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $request));
    }

    public function testHostPortHasPriorityAndDefaultPortIsOmitted(): void
    {
        $request = new ServerRequest('GET', 'https://internal.local/system', [
            'Host' => 'admin.example.com:443',
            'X-Port' => '8443',
        ]);

        $this->assertSame(443, RequestHelper::getPort($request));
        $this->assertSame('https://admin.example.com', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $request));
    }

    public function testProxyPortHasPriorityOverUriPortWhenHostHeaderIsMissing(): void
    {
        $request = (new ServerRequest('GET', 'http://uri.example.com:8080/system', [
            'X-Port' => '9443',
        ]))->withoutHeader('Host');

        $this->assertSame('uri.example.com', RequestHelper::getDomain($request));
        $this->assertSame(9443, RequestHelper::getPort($request));
        $this->assertSame('http://uri.example.com:9443', RequestHelper::getOrigin(RequestHelper::URL_HTTP, $request));
    }

    public function testInvalidHigherPrioritySchemeFallsBackToNextValidScheme(): void
    {
        $request = new ServerRequest('GET', 'http://internal.local/system', [
            'X-Scheme' => 'ftp',
            'X-Forwarded-Proto' => 'https',
            'Host' => 'admin.example.com',
        ]);

        $this->assertSame('https', RequestHelper::getScheme(RequestHelper::URL_HTTP, $request));
        $this->assertSame('wss', RequestHelper::getScheme(RequestHelper::URL_WS, $request));
    }

    public function testUrlMapsHttpAndWebsocketSchemes(): void
    {
        $secure = new ServerRequest('GET', 'https://admin.example.com/system', ['Host' => 'admin.example.com:443']);
        $plain = new ServerRequest('GET', 'http://admin.example.com/system', ['Host' => 'admin.example.com:80']);

        $this->assertSame('https://admin.example.com/api?a=1', RequestHelper::url('/api', ['a' => 1], RequestHelper::URL_HTTP, $secure));
        $this->assertSame('wss://admin.example.com/ws', RequestHelper::url('/ws', [], RequestHelper::URL_WS, $secure));
        $this->assertSame('ws://admin.example.com/ws', RequestHelper::url('/ws', [], RequestHelper::URL_WS, $plain));
        $this->assertSame('https://cdn.example.com/file.png?a=1', RequestHelper::url('https://cdn.example.com/file.png', ['a' => 1], RequestHelper::URL_HTTP, $plain));
    }

    public function testNoRequestContextFailsSoft(): void
    {
        $this->assertNull(RequestHelper::getRequest());
        $this->assertSame('0.0.0.0', RequestHelper::getClientIp());
        $this->assertNull(RequestHelper::getScheme());
        $this->assertNull(RequestHelper::getDomain());
        $this->assertNull(RequestHelper::getPort());
        $this->assertNull(RequestHelper::getOrigin());
        $this->assertSame('/a?b=1', RequestHelper::url('/a', ['b' => 1]));
        $this->assertSame('/', RequestHelper::url());

        $info = RequestHelper::getSimpleRequestInfo();
        $this->assertSame('Unknown', $info['location']);
        $this->assertSame('Unknown', $info['os']);
        $this->assertSame('Unknown', $info['browser']);
        $this->assertSame('Unknown', $info['device']);
    }

    public function testWarmupIp2RegionInitializesReadonlyInstance(): void
    {
        $this->setIp2Region(null);

        RequestHelper::warmupIp2Region('10.0.0.8');

        $this->assertInstanceOf(\Ip2Region::class, $this->getIp2Region());
    }

    public function testIpLocationUsesSingleDisplayField(): void
    {
        $this->setIp2Region(new class() extends \Ip2Region {
            public function simple(string $ip): ?string
            {
                return '中国辽宁省沈阳市联通【CN】';
            }
        });

        $this->assertSame('中国辽宁省沈阳市联通【CN】', RequestHelper::getIpLocation('1.2.3.4'));
        $this->assertSame('中国辽宁省沈阳市联通【CN】', RequestHelper::getIpLocationSimple('1.2.3.4'));
    }

    public function testIpLocationFallsBackWhenRegionIsEmpty(): void
    {
        $this->setIp2Region(new class() extends \Ip2Region {
            public function simple(string $ip): ?string
            {
                return '';
            }
        });

        $this->assertSame('Unknown', RequestHelper::getIpLocation('8.8.8.8'));
        $this->assertSame('Unknown', RequestHelper::getIpLocationSimple('8.8.8.8'));
    }

    private function getIp2Region(): ?\Ip2Region
    {
        $property = new \ReflectionProperty(RequestHelper::class, 'ip2Region');
        $property->setAccessible(true);

        return $property->getValue();
    }

    private function setIp2Region(?\Ip2Region $ip2Region): void
    {
        $property = new \ReflectionProperty(RequestHelper::class, 'ip2Region');
        $property->setAccessible(true);
        $property->setValue(null, $ip2Region);
    }
}
