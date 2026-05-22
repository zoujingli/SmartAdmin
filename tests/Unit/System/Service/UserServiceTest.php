<?php

declare(strict_types=1);

namespace Tests\Unit\System\Service;

use Hyperf\Context\Context;
use Library\Auth\Token;
use Library\Constants\Status;
use Library\Constants\System;
use Library\Exception\ErrorResponseException;
use Library\Interfaces\UserModelInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use System\Mapper\UserMapper;
use System\Service\AuthCacheService;
use System\Service\SystemUserSessionService;
use System\Service\UserAuthorizationBoundaryService;
use System\Service\UserListSnapshotService;
use System\Service\UserRelationAssignmentService;
use System\Service\UserPreferenceService;
use System\Service\UserService;

#[CoversClass(UserService::class)]
#[CoversClass(SystemUserSessionService::class)]
#[CoversClass(UserPreferenceService::class)]
#[CoversClass(UserAuthorizationBoundaryService::class)]
#[CoversClass(UserRelationAssignmentService::class)]
#[CoversClass(UserListSnapshotService::class)]
#[UsesClass(Token::class)]
final class UserServiceTest extends TestCase
{
    public function testGetUserRejectsNonSystemUserTokenWhenUserModelIsMissing(): void
    {
        $service = $this->newInstanceWithoutConstructor(SystemUserSessionService::class);
        $token = $this->getMockBuilder(Token::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getHeaderToken', 'getParserData'])
            ->getMock();
        $token->expects($this->never())->method('getHeaderToken');
        $token->method('getParserData')->with('')->willReturn([
            'uid' => 11,
            'class' => FakeFrontendUser::class,
        ]);

        $this->setProtectedProperty($service, 'token', $token);

        Context::destroy('system_user_object_11');
        try {
            $user = $service->getUser('', null);

            // System 登录态服务不再根据 claims 切换用户体系；非 SystemUser Token 必须直接拒绝。
            $this->assertNull($user);
            $this->assertFalse(Context::has('system_user_object_11'));
        } finally {
            Context::destroy('system_user_object_11');
        }
    }

    public function testNormalizeUiPreferencesInputFiltersInvalidKeysAndTypes(): void
    {
        $service = new UserPreferenceService();

        $result = $service->normalizeUiPreferencesInput([
            'app' => [
                'locale' => 'en-US',
                'name' => 'ignored',
                'watermark' => true,
                'enableCheckUpdates' => 'yes',
            ],
            'theme' => [
                'fontSize' => 18,
                'mode' => 'light',
                'semiDarkHeader' => false,
            ],
            'widget' => [
                'refresh' => false,
                'timezone' => true,
            ],
            'logo' => [
                'enable' => false,
            ],
        ]);

        $this->assertSame([
            'app' => [
                'locale' => 'en-US',
                'watermark' => true,
            ],
            'theme' => [
                'fontSize' => 18,
                'mode' => 'light',
                'semiDarkHeader' => false,
            ],
            'widget' => [
                'refresh' => false,
            ],
        ], $result);
    }

    public function testMergeUserExtraUiPreferencesKeepsOtherExtraFieldsAndClearsEmptyPayload(): void
    {
        $service = new UserPreferenceService();

        $merged = $service->mergeUserExtraUiPreferences([
            'foo' => 'bar',
            'ui_preferences' => [
                'app' => ['locale' => 'zh-CN'],
            ],
        ], [
            'theme' => ['mode' => 'light'],
        ]);

        $this->assertSame([
            'foo' => 'bar',
            'ui_preferences' => [
                'theme' => ['mode' => 'light'],
            ],
        ], $merged);

        $cleared = $service->mergeUserExtraUiPreferences([
            'foo' => 'bar',
            'ui_preferences' => [
                'theme' => ['mode' => 'light'],
            ],
        ], []);

        $this->assertSame([
            'foo' => 'bar',
        ], $cleared);
    }

    public function testWarmDefaultUserListSnapshotDoesNotTouchCacheWithoutRequestContext(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('set');

        $service = new UserListSnapshotService(new UserMapper(), $cache);

        // 登录链路不再预热用户列表，避免在无完整登录/租户上下文时写入 fail-closed 空快照。
        $service->warmDefaultUserListSnapshotAsync(System::getSuperId());
        $this->addToAssertionCount(1);
    }

    public function testChangeStatusRejectsDisablingSuperAdmin(): void
    {
        $service = $this->makeUserServiceWithBoundary();

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('超级管理员账号不允许禁用');

        $service->changeStatus(System::getSuperId(), Status::DISABLED);
    }

    public function testUpdateRejectsDisablingSuperAdmin(): void
    {
        $service = $this->makeUserServiceWithBoundary();

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('超级管理员账号不允许禁用');

        $service->update(System::getSuperId(), ['status' => Status::DISABLED]);
    }

    public function testDeleteRejectsSuperAdmin(): void
    {
        $service = $this->makeUserServiceWithBoundary();

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('超级管理员账号不允许删除');

        $service->delete([System::getSuperId()]);
    }

    public function testRealDeleteRejectsSuperAdmin(): void
    {
        $service = $this->makeUserServiceWithBoundary();

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('超级管理员账号不允许彻底删除');

        $service->delreal([System::getSuperId()]);
    }

    private function makeUserServiceWithBoundary(): UserService
    {
        $service = $this->newInstanceWithoutConstructor(UserService::class);
        $mapper = new UserMapper();
        $boundary = new UserAuthorizationBoundaryService($mapper);
        $relations = new UserRelationAssignmentService(
            $mapper,
            new AuthCacheService($this->createStub(CacheInterface::class)),
            $boundary,
        );

        $this->setProtectedProperty($service, 'boundary', $boundary);
        $this->setProtectedProperty($service, 'relations', $relations);

        return $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function newInstanceWithoutConstructor(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}

final class FakeFrontendUser implements UserModelInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isSuper(): bool
    {
        return false;
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function hasPermission(string $permission): bool
    {
        return false;
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
