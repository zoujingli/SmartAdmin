<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Model;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidatorFactory;
use Library\Helper\ValidateHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use System\Model\SystemRole;
use System\Service\RoleService;

/**
 * @internal
 */
#[CoversClass(SystemRole::class)]
#[CoversClass(RoleService::class)]
final class SystemRoleCodeRemovalTest extends TestCase
{
    private ?ContainerInterface $originContainer = null;

    protected function setUp(): void
    {
        $this->originContainer = ApplicationContext::getContainer();
    }

    protected function tearDown(): void
    {
        if ($this->originContainer) {
            ApplicationContext::setContainer($this->originContainer);
        }
    }

    public function testRoleModelAndFreshMigrationDoNotExposeCodeField(): void
    {
        $role = new SystemRole();
        $root = dirname(__DIR__, 4);
        $migration = (string)file_get_contents($root . '/plugin/System/stc/migrations/2026_05_01_000010_system_role.php');

        // 系统角色身份只依赖主键 ID；权限编码属于菜单/节点，不再给角色表保留独立 code。
        self::assertNotContains('code', $role->getFillable());
        self::assertStringNotContainsString("'code'", $migration);
        self::assertStringNotContainsString('角色内部编码', $migration);
    }

    public function testRoleFilterDropsLegacyCodePayload(): void
    {
        $this->installValidateHelperContainer();

        $service = (new \ReflectionClass(RoleService::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(RoleService::class))->getMethod('filterData');
        $method->setAccessible(true);

        $data = ['name' => ' 运营管理员 ', 'code' => 'legacy-role', 'scope' => 4];
        $filtered = $method->invokeArgs($service, [&$data, ['id' => 1, 'name' => '运营管理员']]);

        // 历史客户端若仍提交角色 code，Service 仅保留当前角色模型允许写入的基础字段，避免写回已删除字段。
        self::assertSame('运营管理员', $filtered['name']);
        self::assertArrayNotHasKey('code', $filtered);
        self::assertArrayNotHasKey('code', $data);
    }

    private function installValidateHelperContainer(): void
    {
        $origin = $this->originContainer;
        $request = $this->createMock(RequestInterface::class);
        $translator = $origin?->get(TranslatorInterface::class);
        $helper = new ValidateHelper($request, new ValidatorFactory($translator));

        ApplicationContext::setContainer(new class($origin, $helper) implements ContainerInterface {
            public function __construct(private ?ContainerInterface $origin, private ValidateHelper $helper) {}

            public function make(string $id, array $parameters = []): mixed
            {
                if ($id === ValidateHelper::class) {
                    return $this->helper;
                }
                if ($this->origin && method_exists($this->origin, 'make')) {
                    return $this->origin->make($id, $parameters);
                }

                return $this->get($id);
            }

            public function get(string $id): mixed
            {
                if ($id === ValidateHelper::class) {
                    return $this->helper;
                }
                if ($this->origin && $this->origin->has($id)) {
                    return $this->origin->get($id);
                }

                throw new class(sprintf('Service "%s" not found.', $id)) extends \RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return $id === ValidateHelper::class || ($this->origin?->has($id) ?? false);
            }
        });
    }
}
