<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library\Events;

use FastRoute\Dispatcher as FastRouteDispatcher;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\Handler;
use Library\CoreController;
use Library\CoreModel;
use Library\Events\Annotation\Logger;
use Library\Events\Aspect\LoggerAspect;
use Library\Events\Event\Logger as LoggerEvent;
use Library\Events\OperateLogRecorder;
use Library\Exception\BaseResponseException;
use Library\Exception\SuccessResponseException;
use Library\Support\ModelChangeLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(OperateLogRecorder::class)]
#[CoversClass(LoggerAspect::class)]
#[CoversClass(CoreController::class)]
final class OperateLogRecorderTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy('__library.operate_log.sent');
        ModelChangeLog::clear();
        AnnotationCollector::clear(LoggerAspectFixture::class);
    }

    public function testFilterSensitiveDataSupportsNestedDotPaths(): void
    {
        $filtered = $this->filterSensitiveData([
            'password' => 'secret',
            'drivers' => [
                'oss' => [
                    'access_id' => 'keep',
                    'access_secret' => 'mask-me',
                ],
                'cos' => [
                    'secret_id' => 'mask-me',
                    'bucket' => 'keep',
                ],
            ],
        ], [
            'drivers.oss.access_secret',
            'drivers.cos.secret_id',
        ]);

        $this->assertSame('***', $filtered['password']);
        $this->assertSame('keep', $filtered['drivers']['oss']['access_id']);
        $this->assertSame('***', $filtered['drivers']['oss']['access_secret']);
        $this->assertSame('***', $filtered['drivers']['cos']['secret_id']);
        $this->assertSame('keep', $filtered['drivers']['cos']['bucket']);
    }

    public function testDispatchSupportsPlainPsrRequest(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public ?LoggerEvent $event = null;

            public function dispatch(object $event): object
            {
                $this->event = $event instanceof LoggerEvent ? $event : null;
                return $event;
            }
        };
        $request = (new ServerRequest('POST', 'https://admin.example.com/system/login?page=1', [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 Chrome/120.0',
            'X-Forwarded-For' => '10.0.0.8',
        ], json_encode([
            'username' => 'admin',
            'password' => 'secret',
        ], JSON_THROW_ON_ERROR)))->withQueryParams(['page' => '1']);
        $annotation = new Logger(
            name: '登录',
            excludeFields: ['password'],
            recordRequest: true,
            recordChange: false,
        );
        $responseData = OperateLogRecorder::formatResponseData([
            'code' => 200,
            'info' => 'ok',
            'data' => ['token' => 'secret'],
        ]);

        (new OperateLogRecorder($dispatcher))->dispatch($annotation, $request, 'Fallback::login', '200', $responseData);

        $data = $dispatcher->event?->getRequestInfo();
        $this->assertSame('登录', $data['name'] ?? null);
        $this->assertSame('admin', $data['username'] ?? null);
        $this->assertSame('/system/login', $data['router'] ?? null);
        $this->assertSame('10.0.0.8', $data['ip'] ?? null);
        $this->assertSame('内网', $data['ip_location'] ?? null);
        $this->assertSame('***', $data['request_data']['password'] ?? null);
        $this->assertSame('1', $data['request_data']['page'] ?? null);
        $this->assertSame('{"code":200,"info":"ok","data":{"token":"***"}}', $data['response_data'] ?? null);
    }

    public function testFailedDispatchKeepsChangePayloadForRetry(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public int $times = 0;

            public ?LoggerEvent $event = null;

            public function dispatch(object $event): object
            {
                ++$this->times;
                if ($this->times === 1) {
                    throw new \RuntimeException('temporary log failure');
                }

                $this->event = $event instanceof LoggerEvent ? $event : null;
                return $event;
            }
        };
        $model = new LoggerAspectChangeModelFixture();
        $model->id = 1;
        $model->name = '旧名称';
        ModelChangeLog::recordFields($model, 'updated', [[
            'field' => 'name',
            'label' => '名称',
            'old' => '旧名称',
            'new' => '新名称',
        ]]);
        $request = $this->mockRequest('/system/demo/update/1', ['name' => '新名称']);
        $annotation = new Logger(name: '失败重试测试');
        $recorder = new OperateLogRecorder($dispatcher);

        try {
            $recorder->dispatch($annotation, $request, 'Fallback::update', '200', '{}');
            $this->fail('First dispatch should expose the dispatcher failure.');
        } catch (\RuntimeException) {
        }

        $this->assertSame('测试记录(旧名称)：名称(name)旧名称改为新名称', ModelChangeLog::peek()['summary'] ?? null);

        $recorder->dispatch($annotation, $request, 'Fallback::update', '200', '{}');

        $data = $dispatcher->event?->getRequestInfo();
        $this->assertSame(2, $dispatcher->times);
        $this->assertSame('测试记录(旧名称)：名称(name)旧名称改为新名称', $data['change_payload']['summary'] ?? null);
        $this->assertNull(ModelChangeLog::peek());
    }

    public function testLoggerAspectRecordsThrownStandardResponseAndChangePayload(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public ?LoggerEvent $event = null;

            public function dispatch(object $event): object
            {
                $this->event = $event instanceof LoggerEvent ? $event : null;
                return $event;
            }
        };
        AnnotationCollector::collectMethod(
            LoggerAspectFixture::class,
            'update',
            Logger::class,
            new Logger(name: '编辑测试记录', excludeFields: ['password'])
        );
        $model = new LoggerAspectChangeModelFixture();
        $model->id = 1;
        $model->name = '旧名称';
        ModelChangeLog::recordFields($model, 'updated', [[
            'field' => 'name',
            'label' => '名称',
            'old' => '旧名称',
            'new' => '新名称',
        ]]);
        $request = $this->mockRequest('/system/demo/update/1', ['name' => '新名称', 'password' => 'secret']);
        $joinPoint = new ProceedingJoinPoint(
            static fn () => null,
            LoggerAspectFixture::class,
            'update',
            ['order' => [], 'keys' => []]
        );
        $joinPoint->pipe = static function (): never {
            throw new SuccessResponseException('更新成功', ['id' => 1, 'token' => 'secret']);
        };

        try {
            (new LoggerAspect($request, new OperateLogRecorder($dispatcher)))->process($joinPoint);
            $this->fail('LoggerAspect should rethrow standard response exceptions.');
        } catch (SuccessResponseException) {
        }

        $data = $dispatcher->event?->getRequestInfo();
        $this->assertSame('编辑测试记录', $data['name'] ?? null);
        $this->assertSame('/system/demo/update/1', $data['router'] ?? null);
        $this->assertSame('***', $data['request_data']['password'] ?? null);
        $this->assertStringContainsString('"token":"***"', $data['response_data'] ?? '');
        $this->assertSame('测试记录(旧名称)：名称(name)旧名称改为新名称', $data['change_payload']['summary'] ?? null);
        $this->assertSame('测试记录(旧名称)：名称(name)旧名称改为新名称', $data['remark'] ?? null);
    }

    public function testLoggerAspectRecordsNormalArrayReturn(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public ?LoggerEvent $event = null;

            public function dispatch(object $event): object
            {
                $this->event = $event instanceof LoggerEvent ? $event : null;
                return $event;
            }
        };
        AnnotationCollector::collectMethod(
            LoggerAspectFixture::class,
            'update',
            Logger::class,
            new Logger(name: '直接返回测试', recordChange: false)
        );
        $request = $this->mockRequest('/system/demo/direct', ['keyword' => 'test']);
        $joinPoint = new ProceedingJoinPoint(
            static fn () => null,
            LoggerAspectFixture::class,
            'update',
            ['order' => [], 'keys' => []]
        );
        $joinPoint->pipe = static fn (): array => [
            'code' => 200,
            'info' => 'ok',
            'data' => ['token' => 'secret'],
        ];

        $result = (new LoggerAspect($request, new OperateLogRecorder($dispatcher)))->process($joinPoint);

        $data = $dispatcher->event?->getRequestInfo();
        $this->assertSame(200, $result['code']);
        $this->assertSame('直接返回测试', $data['name'] ?? null);
        $this->assertSame('200', $data['response_code'] ?? null);
        $this->assertSame('{"code":200,"info":"ok","data":{"token":"***"}}', $data['response_data'] ?? null);
    }

    public function testLoggerAspectDoesNotReplaceBusinessResponseWhenDispatchFails(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new \RuntimeException('log writer unavailable');
            }
        };
        AnnotationCollector::collectMethod(
            LoggerAspectFixture::class,
            'update',
            Logger::class,
            new Logger(name: '日志失败测试')
        );
        $request = $this->mockRequest('/system/demo/update/1', ['name' => '新名称']);
        $joinPoint = new ProceedingJoinPoint(
            static fn () => null,
            LoggerAspectFixture::class,
            'update',
            ['order' => [], 'keys' => []]
        );
        $joinPoint->pipe = static function (): never {
            throw new SuccessResponseException('更新成功', ['id' => 1]);
        };

        try {
            (new LoggerAspect($request, new OperateLogRecorder($dispatcher)))->process($joinPoint);
            $this->fail('LoggerAspect should rethrow the original business response exception.');
        } catch (SuccessResponseException $exception) {
            $this->assertSame('更新成功', $exception->getMessage());
        }
    }

    public function testCoreControllerStandardResponseDoesNotDispatchOperationLog(): void
    {
        $dispatched = new Dispatched([
            FastRouteDispatcher::FOUND,
            new Handler([CoreControllerLoggerFixture::class, 'update'], '/system/demo/update'),
            [],
        ]);
        $request = $this->mockRequest(
            '/system/demo/update',
            ['name' => '新名称', 'password' => 'secret'],
            $dispatched
        );
        $model = new LoggerAspectChangeModelFixture();
        $model->id = 1;
        $model->name = '旧名称';
        ModelChangeLog::recordFields($model, 'updated', [[
            'field' => 'name',
            'label' => '名称',
            'old' => '旧名称',
            'new' => '新名称',
        ]]);
        $controller = new CoreControllerLoggerFixture();
        $controller->boot($request);

        try {
            $controller->update();
            $this->fail('CoreController should throw the standard response exception.');
        } catch (BaseResponseException $exception) {
            $this->assertSame(200, $exception->getCode());
        }

        // CoreController 只负责标准响应，操作日志由 LoggerAspect/ResponseExceptionHandler 统一派发。
        $this->assertSame('测试记录(旧名称)：名称(name)旧名称改为新名称', ModelChangeLog::peek()['summary'] ?? null);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $excludeFields
     * @return array<string, mixed>
     */
    private function filterSensitiveData(array $data, array $excludeFields): array
    {
        $method = new \ReflectionMethod(OperateLogRecorder::class, 'filterSensitiveData');
        $method->setAccessible(true);

        return $method->invoke(null, $data, $excludeFields);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mockRequest(string $path, array $payload, ?Dispatched $dispatched = null): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('PUT');
        $request->method('getPathInfo')->willReturn($path);
        $request->method('getUri')->willReturn(new Uri('https://admin.example.com' . $path));
        $request->method('getServerParams')->willReturn(['remote_addr' => '127.0.0.1']);
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getHeader')->willReturnCallback(static function (string $name): array {
            return strtolower($name) === 'user-agent' ? ['Mozilla/5.0 Chrome/120.0'] : [];
        });
        $request->method('getHeaderLine')->willReturnCallback(static function (string $name): string {
            return strtolower($name) === 'user-agent' ? 'Mozilla/5.0 Chrome/120.0' : '';
        });
        $request->method('all')->willReturn($payload);
        $request->method('input')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $payload[$key] ?? $default
        );
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => $name === Dispatched::class ? $dispatched : $default
        );

        return $request;
    }
}

final class LoggerAspectFixture
{
    public function update(): void {}
}

final class CoreControllerLoggerFixture extends CoreController
{
    public function boot(RequestInterface $request): void
    {
        $this->request = $request;
    }

    #[Logger(name: '核心响应测试', excludeFields: ['password'])]
    public function update(): never
    {
        $this->success('更新成功', ['id' => 1, 'token' => 'secret']);
    }
}

final class LoggerAspectChangeModelFixture extends CoreModel
{
    protected ?string $table = 'logger_aspect_change_model_fixture';

    protected array $fillable = ['id', 'name'];

    protected array $logRules = [
        'name' => '测试记录',
        'title' => 'name',
        'fields' => [
            'name' => '名称',
        ],
    ];
}
