<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Exception;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\Logger\LoggerFactory;
use Library\Auth\Token;
use Library\Constants\System;
use Library\Exception\BaseResponseException;
use Library\Exception\ErrorResponseException;
use Library\Exception\Handler\AppExceptionHandler;
use Library\Exception\Handler\ResponseExceptionHandler;
use Library\Exception\NotAllowResponseException;
use Library\Exception\NotFoundResponseException;
use Library\Exception\SuccessResponseException;
use Library\Exception\UnauthorizedResponseException;
use Library\Logger\RequestIdHolder;
use Library\Logger\RequestLogRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

#[CoversClass(BaseResponseException::class)]
#[CoversClass(UnauthorizedResponseException::class)]
#[CoversClass(NotAllowResponseException::class)]
#[CoversClass(NotFoundResponseException::class)]
#[CoversClass(SuccessResponseException::class)]
#[CoversClass(ErrorResponseException::class)]
#[CoversClass(AppExceptionHandler::class)]
#[CoversClass(ResponseExceptionHandler::class)]
final class ResponseExceptionStatusTest extends TestCase
{
    public function testBaseResponseExceptionNormalizesCustomCodeToSystemError(): void
    {
        $exception = new BaseResponseException('业务失败', ['id' => 1], 422, 409);

        $this->assertSame('业务失败', $exception->getMessage());
        $this->assertSame(['id' => 1], $exception->getData());
        $this->assertSame(System::ERROR, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testResponsePayloadUsesInfoAsStandardMessageField(): void
    {
        $payload = (new BaseResponseException('业务失败', ['id' => 1], 422, 409))->toArray();

        $this->assertSame(System::ERROR, $payload['code']);
        $this->assertSame('业务失败', $payload['info']);
        $this->assertSame(['id' => 1], $payload['data']);
        $this->assertArrayHasKey('path', $payload);
        $this->assertSame(['code', 'info', 'data', 'path'], array_keys($payload));
        $this->assertArrayNotHasKey('message', $payload);
    }

    public function testInvalidResponseCodeFallsBackToSystemError(): void
    {
        $exception = new BaseResponseException('业务失败', null, 0);

        $this->assertSame(System::ERROR, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testNonStandardBusinessCodeFallsBackToSystemError(): void
    {
        $exception = new BaseResponseException('业务失败', null, 1001);

        $this->assertSame(System::ERROR, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testInvalidStatusFallsBackToSystemError(): void
    {
        $exception = (new BaseResponseException('业务失败'))->setStatus(999);

        $this->assertSame(System::ERROR, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testSetStatusOnlyUpdatesBodyCodeForLegacyCompatibility(): void
    {
        $exception = (new BaseResponseException('业务失败'))->setStatus(System::NOT_ALLOW);

        $this->assertSame(System::NOT_ALLOW, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testBaseResponseExceptionKeepsLegacyPreviousArgument(): void
    {
        $previous = new \RuntimeException('previous');
        $exception = new BaseResponseException('业务失败', null, System::ERROR, $previous);

        $this->assertSame(System::SUCCESS, $exception->getStatus());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testUnauthorizedResponseExceptionUses401BodyCode(): void
    {
        $exception = new UnauthorizedResponseException('未登录');

        $this->assertSame(System::UNAUTHORIZED, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testNotAllowResponseExceptionUses403BodyCode(): void
    {
        $exception = new NotAllowResponseException('无权限访问');

        $this->assertSame(System::NOT_ALLOW, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testNotFoundResponseExceptionUses404BodyCode(): void
    {
        $exception = new NotFoundResponseException('页面不存在');

        $this->assertSame(System::NOT_FOUND, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testErrorResponseExceptionUses500BodyCode(): void
    {
        $exception = new ErrorResponseException('服务异常');

        $this->assertSame(System::ERROR, $exception->getCode());
        $this->assertSame(System::SUCCESS, $exception->getStatus());
    }

    public function testHttpStatusAlwaysUses200(): void
    {
        $response = (new UnauthorizedResponseException('未登录'))->withResponse(new Response());

        $this->assertSame(System::SUCCESS, $response->getStatusCode());
    }

    public function testResponseContentTypeIsJsonAndNotDuplicated(): void
    {
        $response = (new ErrorResponseException('服务异常'))->withResponse(
            new Response(200, ['Content-Type' => 'text/plain'])
        );

        $this->assertSame(['application/json; charset=utf-8'], $response->getHeader('Content-Type'));
    }

    public function testResponseExceptionHandlerConvertsRouteNotFoundToStandardPayload(): void
    {
        $response = (new ResponseExceptionHandler())->handle(new NotFoundHttpException(), new Response());
        $payload = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        // 路由 404 也进入统一响应异常处理器：HTTP 固定 200，业务码写入 body.code。
        $this->assertSame(System::SUCCESS, $response->getStatusCode());
        $this->assertSame(System::NOT_FOUND, $payload['code']);
        $this->assertSame('页面不存在', $payload['info']);
    }

    public function testBusinessResponseExceptionDoesNotWriteTraceWithoutPrevious(): void
    {
        $originalContainer = ApplicationContext::getContainer();
        $traceLogger = new class extends AbstractLogger {
            /**
             * @var array<int, string>
             */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string)$message;
            }
        };

        ApplicationContext::setContainer(new class ($originalContainer, $traceLogger) implements ContainerInterface {
            public function __construct(
                private readonly ContainerInterface $fallback,
                private readonly LoggerInterface $traceLogger,
            ) {}

            public function get(string $id)
            {
                return $id === LoggerInterface::class ? $this->traceLogger : $this->fallback->get($id);
            }

            public function has(string $id): bool
            {
                return $id === LoggerInterface::class || $this->fallback->has($id);
            }
        });

        try {
            (new ResponseExceptionHandler())->handle(new ErrorResponseException('业务失败'), new Response());

            $this->assertSame([], $traceLogger->messages);
        } finally {
            ApplicationContext::setContainer($originalContainer);
        }
    }

    public function testResponseExceptionHandlerWritesGlobalResponseLogForThrowResponse(): void
    {
        $originalContainer = ApplicationContext::getContainer();
        $logger = new class extends AbstractLogger {
            /**
             * @var array<int, string>
             */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string)$message;
            }
        };
        $factory = $this->createMock(LoggerFactory::class);
        $factory->method('get')->with('log')->willReturn($logger);
        $requestLogRecorder = new RequestLogRecorder($factory, $this->createStub(Token::class));
        $traceLogger = new class extends AbstractLogger {
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        ApplicationContext::setContainer(new class ($originalContainer, $requestLogRecorder, $traceLogger) implements ContainerInterface {
            public function __construct(
                private readonly ContainerInterface $fallback,
                private readonly RequestLogRecorder $requestLogRecorder,
                private readonly LoggerInterface $traceLogger,
            ) {}

            public function get(string $id)
            {
                return match ($id) {
                    RequestLogRecorder::class => $this->requestLogRecorder,
                    LoggerInterface::class => $this->traceLogger,
                    default => $this->fallback->get($id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [RequestLogRecorder::class, LoggerInterface::class], true) || $this->fallback->has($id);
            }
        });
        Context::set(ServerRequestInterface::class, new ServerRequest('GET', 'https://admin.example.com/system/user'));

        try {
            (new ResponseExceptionHandler())->handle(new SuccessResponseException('操作成功'), new Response());

            $this->assertSame(['onResponse'], $logger->messages);
        } finally {
            ApplicationContext::setContainer($originalContainer);
            Context::destroy(ServerRequestInterface::class);
            Context::destroy(RequestIdHolder::REQUEST_ID);
            foreach ([
                '__library.request_log.start_time',
                '__library.request_log.request_id',
                '__library.request_log.request_logged',
                '__library.request_log.response_logged',
            ] as $key) {
                Context::destroy($key);
            }
        }
    }

    public function testAppExceptionHandlerWritesGlobalResponseLogForFallbackException(): void
    {
        $originalContainer = ApplicationContext::getContainer();
        $logger = new class extends AbstractLogger {
            /**
             * @var array<int, string>
             */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string)$message;
            }
        };
        $factory = $this->createMock(LoggerFactory::class);
        $factory->method('get')->with('log')->willReturn($logger);
        $requestLogRecorder = new RequestLogRecorder($factory, $this->createStub(Token::class));
        $traceLogger = new class extends AbstractLogger {
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        ApplicationContext::setContainer(new class ($originalContainer, $requestLogRecorder, $traceLogger) implements ContainerInterface {
            public function __construct(
                private readonly ContainerInterface $fallback,
                private readonly RequestLogRecorder $requestLogRecorder,
                private readonly LoggerInterface $traceLogger,
            ) {}

            public function get(string $id)
            {
                return match ($id) {
                    RequestLogRecorder::class => $this->requestLogRecorder,
                    LoggerInterface::class => $this->traceLogger,
                    default => $this->fallback->get($id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [RequestLogRecorder::class, LoggerInterface::class], true) || $this->fallback->has($id);
            }
        });
        Context::set(ServerRequestInterface::class, new ServerRequest('GET', 'https://admin.example.com/system/error'));

        $outputLevel = ob_get_level();
        ob_start();
        try {
            $response = (new AppExceptionHandler())->handle(new \RuntimeException('系统异常'), new Response());
            $payload = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(System::SUCCESS, $response->getStatusCode());
            $this->assertSame(System::ERROR, $payload['code']);
            $this->assertSame(['onResponse'], $logger->messages);
        } finally {
            while (ob_get_level() > $outputLevel) {
                ob_end_clean();
            }
            ApplicationContext::setContainer($originalContainer);
            Context::destroy(ServerRequestInterface::class);
            Context::destroy(RequestIdHolder::REQUEST_ID);
            foreach ([
                '__library.request_log.start_time',
                '__library.request_log.request_id',
                '__library.request_log.request_logged',
                '__library.request_log.response_logged',
            ] as $key) {
                Context::destroy($key);
            }
        }
    }

    public function testResponseExceptionFallsBackToRawMessageWithoutContainer(): void
    {
        $property = new \ReflectionProperty(ApplicationContext::class, 'container');
        $original = $property->getValue();
        $property->setValue(null, null);

        try {
            $exception = new ErrorResponseException('服务异常');

            $this->assertSame('服务异常', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        } finally {
            $property->setValue(null, $original);
        }
    }
}
