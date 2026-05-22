<?php

declare(strict_types=1);

namespace Tests\Unit\WechatClient;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Service\WechatClientReplyRuleService;
use ReflectionClass;

#[CoversClass(WechatClientReplyRuleService::class)]
final class ReplyRuleServiceTest extends TestCase
{
    public function testAssertReplyContentRejectsEmptyTextReply(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('文本回复内容不能为空');

        $this->invokePrivate('assertReplyContent', 'text', ['content' => '  ']);
    }

    public function testDecodeReplyContentRejectsInvalidReplyContentJson(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('回复内容格式错误');

        $this->invokePrivate('decodeReplyContent', '{"content":');
    }

    public function testAssertReplyContentRejectsMediaReplyWithoutMediaReference(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('素材回复必须选择本地素材或填写 MediaID');

        $this->invokePrivate('assertReplyContent', 'image', []);
    }

    public function testAssertReplyContentRejectsNewsReplyWithoutArticle(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('图文回复必须选择本地文章');

        $this->invokePrivate('assertReplyContent', 'news', ['url' => 'https://example.com']);
    }

    public function testAssertReplyContentAllowsCompleteTextAndMediaReply(): void
    {
        $this->expectNotToPerformAssertions();

        $this->invokePrivate('assertReplyContent', 'text', ['content' => '已配置']);
        $this->invokePrivate('assertReplyContent', 'image', ['media_local_id' => 1]);
        $this->invokePrivate('assertReplyContent', 'voice', ['media_id' => 'MEDIA_ID']);
        $this->invokePrivate('assertReplyContent', 'news', ['article_id' => 1]);
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionClass(WechatClientReplyRuleService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invoke($service, ...$args);
    }
}
