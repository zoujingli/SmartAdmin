<?php

declare(strict_types=1);

namespace Tests\Unit\WechatClient;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Service\WechatClientMenuService;
use ReflectionClass;

#[CoversClass(WechatClientMenuService::class)]
final class MenuServiceTest extends TestCase
{
    public function testBuildOfficialButtonsConvertsViewAndSubButtons(): void
    {
        $buttons = $this->officialButtons([
            ['name' => '官网', 'type' => 'view', 'url' => 'https://example.com'],
            [
                'name' => '服务',
                'children' => [
                    ['name' => '客服', 'type' => 'click', 'key' => 'CONTACT'],
                ],
            ],
        ]);

        $this->assertSame([
            ['type' => 'view', 'name' => '官网', 'url' => 'https://example.com'],
            [
                'name' => '服务',
                'sub_button' => [
                    ['type' => 'click', 'name' => '客服', 'key' => 'CONTACT'],
                ],
            ],
        ], $buttons);
    }

    public function testBuildOfficialButtonsRejectsMoreThanThreeTopButtons(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('一级菜单最多 3 个');

        $this->officialButtons([
            ['name' => '一', 'type' => 'view', 'url' => 'https://a.example.com'],
            ['name' => '二', 'type' => 'view', 'url' => 'https://b.example.com'],
            ['name' => '三', 'type' => 'view', 'url' => 'https://c.example.com'],
            ['name' => '四', 'type' => 'view', 'url' => 'https://d.example.com'],
        ]);
    }

    public function testBuildOfficialButtonsRejectsMoreThanFiveSubButtons(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('最多包含 5 个二级菜单');

        $this->officialButtons([[
            'name' => '服务',
            'children' => [
                ['name' => '一', 'type' => 'click', 'key' => 'K1'],
                ['name' => '二', 'type' => 'click', 'key' => 'K2'],
                ['name' => '三', 'type' => 'click', 'key' => 'K3'],
                ['name' => '四', 'type' => 'click', 'key' => 'K4'],
                ['name' => '五', 'type' => 'click', 'key' => 'K5'],
                ['name' => '六', 'type' => 'click', 'key' => 'K6'],
            ],
        ]]);
    }

    public function testBuildOfficialButtonsRejectsWechatNameWidthOverflow(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('微信官方限制最多 4 个汉字');

        $this->officialButtons([
            ['name' => '超过四个字', 'type' => 'view', 'url' => 'https://example.com'],
        ]);
    }

    public function testBuildOfficialButtonsRejectsInvalidViewUrl(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网页链接必须是完整 URL');

        $this->officialButtons([
            ['name' => '官网', 'type' => 'view', 'url' => '/local/path'],
        ]);
    }

    public function testBuildOfficialButtonsRejectsEmptyMenu(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('至少需要 1 个一级菜单');

        $this->officialButtons([]);
    }

    public function testBuildOfficialButtonsRejectsInvalidRawJsonLeaf(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网页链接不能为空');

        $this->officialButtons([
            ['name' => '官网', 'type' => 'json', 'raw_json' => ['type' => 'view', 'name' => '官网']],
        ]);
    }

    public function testBuildOfficialButtonsRejectsRawJsonSyntaxError(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('高级 JSON 格式错误');

        $this->officialButtons([
            ['name' => '官网', 'type' => 'json', 'raw_json' => '{"type":"view"'],
        ]);
    }

    public function testBuildOfficialButtonsRejectsInvalidRawJsonChild(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('子菜单必须是对象');

        $this->officialButtons([
            ['name' => '服务', 'type' => 'json', 'raw_json' => ['name' => '服务', 'sub_button' => ['bad']]],
        ]);
    }

    public function testDecodeButtonsRejectsInvalidButtonsJson(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('菜单按钮结构格式错误');

        $this->invokePrivate('decodeButtons', '{"name":"坏数据"}', '菜单按钮结构');
    }

    public function testAssertLocalLeafButtonRejectsUnsupportedTypeOnSave(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('类型不支持');

        $this->invokePrivate('assertLocalLeafButton', ['type' => 'bad_type'], '第 1 个一级菜单');
    }

    /**
     * 通过反射调用菜单发布前的官方结构构建逻辑，避免单测访问真实微信接口或数据库资源。
     *
     * @param array<int,mixed> $buttons
     * @return array<int,array<string,mixed>>
     */
    private function officialButtons(array $buttons): array
    {
        $reflection = new ReflectionClass(WechatClientMenuService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildOfficialButtons');
        $method->setAccessible(true);

        /** @var array<int,array<string,mixed>> $result */
        $result = $method->invoke($service, $buttons);

        return $result;
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionClass(WechatClientMenuService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invoke($service, ...$args);
    }
}
