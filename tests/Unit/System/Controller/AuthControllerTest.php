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
use Lcobucci\JWT\Token as JwtToken;
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
            $this->tokenStub(),
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
        $token = $this->tokenStub(refresh: static fn (): never => throw new \RuntimeException('refresh should not be called'));

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
        $claimsToken = $this->tokenStub(parserData: [
            'uid' => 123,
            'class' => 'System\Model\SystemUser',
        ]);

        ApplicationContext::setContainer($this->makeContainer($claimsToken, $this->makeTranslator()));

        try {
            $controller = new AuthController(
                $this->makeUnauthorizedUserService(),
                $this->tokenStub(),
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
            $token = $this->tokenStub(refresh: static fn (): never => throw new \RuntimeException('refresh failed'));

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
                $this->tokenStub(),
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
            $this->tokenStub(),
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
        $token = $this->tokenStub(headerToken: '', parserData: []);

        $this->setProtectedProperty($sessions, 'token', $token);
        $this->setProtectedProperty($service, 'sessions', $sessions);

        return $service;
    }

    private function tokenStub(string $headerToken = '', array $parserData = [], ?callable $refresh = null): Token
    {
        $refreshCallback = $refresh instanceof \Closure ? $refresh : ($refresh === null ? null : \Closure::fromCallable($refresh));

        return new class($headerToken, $parserData, $refreshCallback) extends Token {
            /**
             * @param array<string, mixed> $parserData
             */
            public function __construct(
                private readonly string $headerToken = '',
                private readonly array $parserData = [],
                private readonly ?\Closure $refreshCallback = null,
            ) {}

            public function getHeaderToken(): string
            {
                return $this->headerToken;
            }

            public function getParserData(?string $token = null): array
            {
                return $this->parserData;
            }

            public function refresh(?string $token = null): JwtToken|string
            {
                if ($this->refreshCallback) {
                    return ($this->refreshCallback)($token);
                }

                return '';
            }
        };
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
        return $this->tokenStub(parserData: $claims);
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
