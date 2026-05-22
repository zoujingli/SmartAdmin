<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Helper;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidatorFactory;
use Library\Exception\CoreResponseException;
use Library\Helper\ValidateHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidateHelper::class)]
final class ValidateHelperTest extends TestCase
{
    public function testOptionalMissingFieldsAreNotReturned(): void
    {
        $helper = $this->makeHelper();

        $data = $helper->check([
            'name.max:20' => '名称最多 20 个字符',
            'remark.max:50' => '备注最多 50 个字符',
            'status.default' => 1,
            'status.in:1,0' => '状态值错误',
        ], [
            'name' => 'Admin',
            'unknown' => 'ignored',
        ]);

        $this->assertSame([
            'status' => 1,
            'name' => 'Admin',
        ], $data);
    }

    public function testRequiredMissingFieldStillFails(): void
    {
        $helper = $this->makeHelper();

        $this->expectException(CoreResponseException::class);
        $this->expectExceptionMessage('名称不能为空');

        $helper->check([
            'name.required' => '名称不能为空',
        ], []);
    }

    public function testDefaultValueIsValidatedRegardlessOfRuleOrder(): void
    {
        $helper = $this->makeHelper();

        $this->expectException(CoreResponseException::class);
        $this->expectExceptionMessage('状态值错误');

        $helper->check([
            'status.in:1,0' => '状态值错误',
            'status.default' => 2,
        ], []);
    }

    public function testAliasFieldReturnsNormalizedKey(): void
    {
        $helper = $this->makeHelper();

        $data = $helper->check([
            'nickname.max:20#nick' => '昵称最多 20 个字符',
        ], [
            'nick' => 'Alice',
        ]);

        $this->assertSame(['nickname' => 'Alice'], $data);
    }

    private function makeHelper(): ValidateHelper
    {
        $request = $this->createMock(RequestInterface::class);
        $translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);

        return new ValidateHelper($request, new ValidatorFactory($translator));
    }
}
