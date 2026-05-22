<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Logger;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Hyperf\Context\Context;
use Hyperf\Logger\LoggerFactory;
use Library\Auth\Token;
use Library\Logger\RequestIdHolder;
use Library\Logger\RequestLogRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

#[CoversClass(RequestLogRecorder::class)]
final class RequestLogRecorderTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            RequestIdHolder::REQUEST_ID,
            '__library.request_log.start_time',
            '__library.request_log.request_id',
            '__library.request_log.request_logged',
            '__library.request_log.response_logged',
        ] as $key) {
            Context::destroy($key);
        }
    }

    public function testBeginAndLogResponseWriteRequestAndResponse(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * @var array<int, array{level:mixed,message:string,context:array<string, mixed>}>
             */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string)$message,
                    'context' => $context,
                ];
            }
        };

        $recorder = $this->makeRecorder($logger);
        $request = (new ServerRequest('POST', 'https://admin.example.com/system/login?page=1', [
            'Content-Type' => 'application/json',
            'X-Forwarded-For' => '10.0.0.8',
        ], json_encode([
            'username' => 'admin',
            'password' => 'secret',
        ], JSON_THROW_ON_ERROR)))->withQueryParams(['page' => '1']);
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'data' => ['token' => 'secret'],
        ], JSON_THROW_ON_ERROR));

        $context = $recorder->begin($request);
        $recorder->logResponse($request, $response, $context['start_time'], $context['request_id']);

        $this->assertCount(2, $logger->records);
        $this->assertSame('onRequest', $logger->records[0]['message']);
        $this->assertSame('10.0.0.8', $logger->records[0]['context']['ip']);
        $this->assertSame('内网', $logger->records[0]['context']['ip_location']);
        $this->assertSame('***', $logger->records[0]['context']['body']['password']);
        $this->assertSame('onResponse', $logger->records[1]['message']);
        $this->assertSame('10.0.0.8', $logger->records[1]['context']['ip']);
        $this->assertSame('内网', $logger->records[1]['context']['ip_location']);
        $this->assertSame('***', $logger->records[1]['context']['body']['data']['token']);
    }

    public function testResponseLogIsWrittenOnce(): void
    {
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

        $recorder = $this->makeRecorder($logger);
        $request = new ServerRequest('GET', 'https://admin.example.com/system/user');
        $response = new Response(200);

        $context = $recorder->begin($request);
        $recorder->logResponse($request, $response, $context['start_time'], $context['request_id']);
        $recorder->logResponse($request, $response, $context['start_time'], $context['request_id']);

        $this->assertSame(['onRequest', 'onResponse'], $logger->messages);
    }

    public function testLargeBodyIsTruncatedWithEllipsis(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * @var array<int, array<string, mixed>>
             */
            public array $contexts = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->contexts[] = $context;
            }
        };

        $recorder = $this->makeRecorder($logger);
        $request = new ServerRequest('POST', 'https://admin.example.com/system/data', [], 'token=secret&data=' . str_repeat('a', 2100));
        $response = new Response(200, [], json_encode([
            'token' => 'secret',
            'data' => str_repeat('b', 2100),
        ], JSON_THROW_ON_ERROR));

        $context = $recorder->begin($request);
        $recorder->logResponse($request, $response, $context['start_time'], $context['request_id']);

        $this->assertStringEndsWith('...', $logger->contexts[0]['body']['raw']);
        $this->assertStringEndsWith('...', $logger->contexts[1]['body']['raw']);
        $this->assertStringContainsString('token=***', $logger->contexts[0]['body']['raw']);
        $this->assertStringContainsString('"token":"***"', $logger->contexts[1]['body']['raw']);
        $this->assertStringNotContainsString('secret', $logger->contexts[0]['body']['raw']);
        $this->assertStringNotContainsString('secret', $logger->contexts[1]['body']['raw']);
    }

    public function testTruncatedRawJsonMasksPasswordObject(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * @var array<int, array<string, mixed>>
             */
            public array $contexts = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->contexts[] = $context;
            }
        };

        $recorder = $this->makeRecorder($logger);
        $body = json_encode([
            'username' => 'admin',
            'password' => [
                'kid' => 'runtime-key-id',
                'nonce' => 'server-issued-nonce',
                'ciphertext' => 'base64-rsa-oaep-ciphertext',
            ],
            'remark' => str_repeat('a', 2100),
        ], JSON_THROW_ON_ERROR);
        $request = new ServerRequest('POST', 'https://admin.example.com/system/auth/login', [
            'Content-Type' => 'application/json',
        ], $body);

        $recorder->begin($request);

        $raw = $logger->contexts[0]['body']['raw'];
        $this->assertStringContainsString('"password":"***"', $raw);
        $this->assertStringNotContainsString('server-issued-nonce', $raw);
        $this->assertStringNotContainsString('base64-rsa-oaep-ciphertext', $raw);
    }

    private function makeRecorder(AbstractLogger $logger): RequestLogRecorder
    {
        $factory = $this->createMock(LoggerFactory::class);
        $factory->method('get')->with('log')->willReturn($logger);

        return new RequestLogRecorder($factory, $this->createStub(Token::class));
    }
}
