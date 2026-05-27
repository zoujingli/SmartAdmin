<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Hyperf\Contract\ConfigInterface;
use Library\Exception\ErrorResponseException;
use Library\Middleware\DemoMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
#[CoversClass(DemoMiddleware::class)]
final class DemoMiddlewareTest extends TestCase
{
    public function testDevEnvironmentDoesNotBlockDangerousWrites(): void
    {
        $handler = new DemoGuardFakeHandler();
        $middleware = new DemoMiddleware(new DemoGuardFakeConfig('dev'));

        $response = $middleware->process(new ServerRequest('DELETE', '/system/user/delete/1'), $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(1, $handler->handled);
    }

    public function testDemoBlocksSystemWriteButAllowsAuthPostsAndGetRequests(): void
    {
        $middleware = new DemoMiddleware(new DemoGuardFakeConfig('demo'));

        $this->assertAllowed($middleware, 'GET', '/system/user/index');
        $this->assertAllowed($middleware, 'POST', '/system/auth/login');
        $this->assertAllowed($middleware, 'POST', '/system/auth/refresh');
        $this->assertAllowed($middleware, 'POST', '/system/auth/profile');
        $this->assertAllowed($middleware, 'POST', '/system/auth/logout');

        $this->assertBlocked($middleware, 'PUT', '/system/auth/profile');
        $this->assertBlocked($middleware, 'POST', '/system/data/clear-cache');
        $this->assertBlocked($middleware, 'DELETE', '/system/user/delete/1');
    }

    public function testDemoAllowsProjectBusinessFlowAndBlocksProtectedProjectWrites(): void
    {
        $middleware = new DemoMiddleware(new DemoGuardFakeConfig('demo'));

        $this->assertAllowed($middleware, 'POST', '/project/task/create');
        $this->assertAllowed($middleware, 'PUT', '/project/task/finish/9');
        $this->assertAllowed($middleware, 'PUT', '/project/bug/status/9');
        $this->assertAllowed($middleware, 'POST', '/project/smart/analyze');

        $this->assertBlocked($middleware, 'POST', '/project/account/create');
        $this->assertBlocked($middleware, 'PUT', '/project/account/roles/product/permissions');
        $this->assertBlocked($middleware, 'PUT', '/project/dingtalk/config');
        $this->assertBlocked($middleware, 'DELETE', '/project/task/delete/9');
        $this->assertBlocked($middleware, 'PUT', '/project/product/status/9');
    }

    public function testDemoBlocksSmartConfigAndKeepsPublicWechatCallbacks(): void
    {
        $middleware = new DemoMiddleware(new DemoGuardFakeConfig('demo'));

        $this->assertBlocked($middleware, 'POST', '/smart/config/create');
        $this->assertBlocked($middleware, 'POST', '/smart/config/restore');
        $this->assertBlocked($middleware, 'PUT', '/smart/pool/status');
        $this->assertAllowed($middleware, 'POST', '/smart/pool/test');

        $this->assertBlocked($middleware, 'POST', '/wechat-client/account/create');
        $this->assertBlocked($middleware, 'POST', '/wechat-service/config/save');
        $this->assertAllowed($middleware, 'POST', '/wechat-client/api/push/wx123');
        $this->assertAllowed($middleware, 'POST', '/wechat-service/api/callback/ticket');
    }

    private function assertAllowed(DemoMiddleware $middleware, string $method, string $path): void
    {
        $handler = new DemoGuardFakeHandler();
        $response = $middleware->process(new ServerRequest($method, $path), $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(1, $handler->handled);
    }

    private function assertBlocked(DemoMiddleware $middleware, string $method, string $path): void
    {
        $handler = new DemoGuardFakeHandler();

        try {
            $middleware->process(new ServerRequest($method, $path), $handler);
            self::fail(sprintf('%s %s should be blocked in demo mode.', $method, $path));
        } catch (ErrorResponseException $exception) {
            self::assertSame(500, $exception->getCode());
            self::assertSame('演示环境禁止修改关键数据', $exception->getMessage());
            self::assertSame(0, $handler->handled);
        }
    }
}

final class DemoGuardFakeConfig implements ConfigInterface
{
    public function __construct(
        private readonly string $appEnv,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $key === 'app_env' ? $this->appEnv : $default;
    }

    public function has(string $keys): bool
    {
        return $keys === 'app_env';
    }

    public function set(string $key, mixed $value): void {}
}

final class DemoGuardFakeHandler implements RequestHandlerInterface
{
    public int $handled = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->handled;

        return new Response(204);
    }
}
