<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Controller;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\Auth\Token;
use Library\Exception\ErrorResponseException;
use Library\Exception\UnauthorizedResponseException;
use Library\Interfaces\UserModelInterface;
use Library\Service\LoginService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use System\Controller\AuthController;
use System\Model\SystemUser;
use System\Service\DataService;
use System\Service\MenuService;
use System\Service\PasswordCryptoService;
use System\Service\SystemUserSessionService;
use System\Service\UserService;

/**
 * @internal
 */
#[CoversClass(AuthController::class)]
#[UsesClass(ErrorResponseException::class)]
#[UsesClass(UnauthorizedResponseException::class)]
#[UsesClass(PasswordCryptoService::class)]
#[UsesClass(SystemUserSessionService::class)]
#[UsesClass(UserService::class)]
final class AuthControllerTest extends TestCase
{
    public function testLoginRequiresCredentialsWithBusinessErrorStatus(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('all')->willReturn([]);

        $controller = new AuthController(
            $this->newInstanceWithoutConstructor(UserService::class),
            $this->getMockBuilder(Token::class)->disableOriginalConstructor()->getMock(),
            $this->newInstanceWithoutConstructor(MenuService::class),
            $this->newInstanceWithoutConstructor(DataService::class),
            $this->newInstanceWithoutConstructor(PasswordCryptoService::class),
        );
        $this->setProtectedProperty($controller, 'request', $request);

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('用户名和密码不能为空');

        $controller->login($request);
    }

    public function testRefreshThrowsUnauthorizedWhenCurrentUserCannotBeResolved(): void
    {
        $token = $this->getMockBuilder(Token::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['refresh'])
            ->getMock();
        $token->expects($this->never())->method('refresh');

        $controller = new AuthController(
            $this->makeUnauthorizedUserService(),
            $token,
            $this->newInstanceWithoutConstructor(MenuService::class),
            $this->newInstanceWithoutConstructor(DataService::class),
            $this->newInstanceWithoutConstructor(PasswordCryptoService::class),
        );

        $this->expectException(UnauthorizedResponseException::class);
        $this->expectExceptionMessage('未登录');

        $controller->refresh();
    }

    public function testProfileRejectsRawClaimsWithoutEffectiveLoginUser(): void
    {
        $originalContainer = ApplicationContext::getContainer();
        $claimsToken = $this->getMockBuilder(Token::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParserData'])
            ->getMock();
        $claimsToken->method('getParserData')->willReturn([
            'uid' => 123,
            'class' => 'System\Model\SystemUser',
        ]);

        ApplicationContext::setContainer($this->makeContainer($claimsToken, $this->makeTranslator()));

        try {
            $controller = new AuthController(
                $this->makeUnauthorizedUserService(),
                $this->getMockBuilder(Token::class)->disableOriginalConstructor()->getMock(),
                $this->newInstanceWithoutConstructor(MenuService::class),
                $this->newInstanceWithoutConstructor(DataService::class),
                $this->newInstanceWithoutConstructor(PasswordCryptoService::class),
            );

            $this->expectException(UnauthorizedResponseException::class);
            $controller->profile();
        } finally {
            ApplicationContext::setContainer($originalContainer);
        }
    }

    public function testRefreshDoesNotConvertServerErrorToUnauthorized(): void
    {
        $originalContainer = ApplicationContext::getContainer();
        $capturedUserModel = null;
        ApplicationContext::setContainer($this->makeContainer(
            $this->makeClaimsToken([
                'uid' => 7,
                'class' => SystemUser::class,
            ]),
            $this->makeTranslator(),
            $this->makeLoginService(static function (?string $token, ?string $userModel) use (&$capturedUserModel): UserModelInterface {
                $capturedUserModel = $userModel;

                return new class implements UserModelInterface {
                    public function getId(): int
                    {
                        return 7;
                    }

                    public function getName(): string
                    {
                        return 'custom-user';
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
                        return [];
                    }
                };
            })
        ));

        try {
            $token = $this->getMockBuilder(Token::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['refresh'])
                ->getMock();
            $token->method('refresh')->willThrowException(new \RuntimeException('refresh failed'));

            $controller = new AuthController(
                $this->newInstanceWithoutConstructor(UserService::class),
                $token,
                $this->newInstanceWithoutConstructor(MenuService::class),
                $this->newInstanceWithoutConstructor(DataService::class),
                $this->newInstanceWithoutConstructor(PasswordCryptoService::class),
            );

            try {
                $controller->refresh();
                $this->fail('Expected RuntimeException was not thrown.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('refresh failed', $exception->getMessage());
                $this->assertSame(SystemUser::class, $capturedUserModel);
            }
        } finally {
            ApplicationContext::setContainer($originalContainer);
        }
    }

    public function testCurrentUserUsesSystemUserModelForLookup(): void
    {
        $originalContainer = ApplicationContext::getContainer();
        $capturedUserModel = null;
        ApplicationContext::setContainer($this->makeContainer(
            $this->makeClaimsToken([
                'uid' => 9,
                'class' => 'Custom\User',
            ]),
            $this->makeTranslator(),
            $this->makeLoginService(static function (?string $token, ?string $userModel) use (&$capturedUserModel): UserModelInterface {
                $capturedUserModel = $userModel;

                return new class implements UserModelInterface {
                    public function getId(): int
                    {
                        return 9;
                    }

                    public function getName(): string
                    {
                        return 'custom-user';
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
                        return [];
                    }
                };
            })
        ));

        try {
            $controller = new AuthController(
                $this->newInstanceWithoutConstructor(UserService::class),
                $this->getMockBuilder(Token::class)->disableOriginalConstructor()->getMock(),
                $this->newInstanceWithoutConstructor(MenuService::class),
                $this->newInstanceWithoutConstructor(DataService::class),
                $this->newInstanceWithoutConstructor(PasswordCryptoService::class),
            );

            $method = new \ReflectionMethod($controller, 'currentUser');
            $user = $method->invoke($controller);

            $this->assertInstanceOf(UserModelInterface::class, $user);
            $this->assertSame(9, $user->getId());
            $this->assertSame(SystemUser::class, $capturedUserModel);
        } finally {
            ApplicationContext::setContainer($originalContainer);
        }
    }

    public function testBuildProfilePayloadPreservesExtraData(): void
    {
        $controller = new AuthController(
            $this->newInstanceWithoutConstructor(UserService::class),
            $this->getMockBuilder(Token::class)->disableOriginalConstructor()->getMock(),
            $this->newInstanceWithoutConstructor(MenuService::class),
            $this->newInstanceWithoutConstructor(DataService::class),
            $this->newInstanceWithoutConstructor(PasswordCryptoService::class),
        );

        $method = new \ReflectionMethod($controller, 'buildProfilePayload');
        $payload = $method->invoke($controller, [
            'extra' => [
                'ui_preferences' => [
                    'app' => ['locale' => 'en-US'],
                ],
            ],
        ]);

        $this->assertSame([
            'ui_preferences' => [
                'app' => ['locale' => 'en-US'],
            ],
        ], $payload['extra']);
    }

    private function makeUnauthorizedUserService(): UserService
    {
        $service = $this->newInstanceWithoutConstructor(UserService::class);
        $sessions = $this->newInstanceWithoutConstructor(SystemUserSessionService::class);
        $token = $this->getMockBuilder(Token::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getHeaderToken', 'getParserData'])
            ->getMock();
        $token->method('getHeaderToken')->willReturn('');
        $token->method('getParserData')->willReturn([]);

        $this->setProtectedProperty($sessions, 'token', $token);
        $this->setProtectedProperty($service, 'sessions', $sessions);

        return $service;
    }

    private function makeTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            private string $locale = 'zh_CN';

            public function trans(string $key, array $replace = [], ?string $locale = null): array|string
            {
                return strtr($key, $replace);
            }

            public function transChoice(string $key, $number, array $replace = [], ?string $locale = null): string
            {
                return (string)$this->trans($key, $replace, $locale);
            }

            public function getLocale(): string
            {
                return $this->locale;
            }

            public function setLocale(string $locale)
            {
                $this->locale = $locale;
            }
        };
    }

    private function makeClaimsToken(array $claims): Token
    {
        $token = $this->getMockBuilder(Token::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParserData'])
            ->getMock();
        $token->method('getParserData')->willReturn($claims);

        return $token;
    }

    private function makeLoginService(callable $resolver): object
    {
        return new class($resolver) {
            private \Closure $resolver;

            public function __construct(callable $resolver)
            {
                $this->resolver = $resolver instanceof \Closure
                    ? $resolver
                    : \Closure::fromCallable($resolver);
            }

            public function getUser(?string $token = null, ?string $userModel = null): ?UserModelInterface
            {
                return ($this->resolver)($token, $userModel);
            }
        };
    }

    private function makeContainer(Token $token, TranslatorInterface $translator, ?object $loginService = null): ContainerInterface
    {
        return new class($token, $translator, $loginService) implements ContainerInterface {
            public function __construct(
                private readonly Token $token,
                private readonly TranslatorInterface $translator,
                private readonly ?object $loginService = null,
            ) {}

            public function get(string $id)
            {
                return match ($id) {
                    Token::class => $this->token,
                    LoginService::class => $this->loginService ?? throw new class('Login service not configured.') extends \RuntimeException implements NotFoundExceptionInterface {},
                    TranslatorInterface::class => $this->translator,
                    default => throw new class(sprintf('Service "%s" not found.', $id)) extends \RuntimeException implements NotFoundExceptionInterface {},
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [Token::class, TranslatorInterface::class], true)
                    || ($id === LoginService::class && $this->loginService !== null);
            }
        };
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
