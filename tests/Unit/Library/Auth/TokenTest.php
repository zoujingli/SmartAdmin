<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Auth;

use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Library\Auth\Constant\JwtAbstract;
use Library\Auth\Constant\JwtConstant;
use Library\Auth\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Token::class)]
#[UsesClass(JwtAbstract::class)]
#[UsesClass(JwtConstant::class)]
final class TokenTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy('__library.jwt.scene');
    }

    public function testGetParserDataSupportsBase64UrlEncodedScenePayload(): void
    {
        $token = $this->makeTokenService('>');
        $token->setScene('>');

        $jwt = $token->create([
            'uid' => 1,
            'class' => 'System\\Model\\SystemUser',
        ], false)->toString();

        $this->assertStringContainsString('-', explode('.', $jwt)[1]);
        $this->assertSame('>', $token->getParserData($jwt)['jwt_scene'] ?? null);
    }

    public function testSceneIsStoredInCoroutineContext(): void
    {
        $token = $this->makeTokenService('admin');

        $token->setScene('admin');
        $this->assertSame('admin', $token->getScene());

        Context::destroy('__library.jwt.scene');
        $this->assertSame('default', $token->getScene());
    }

    private function makeTokenService(string $scene = 'default'): Token
    {
        $config = new class ($scene) implements ConfigInterface {
            /**
             * @var array<string, mixed>
             */
            private array $items;

            public function __construct(string $scene)
            {
                $this->items = [
                    'jwt' => [
                        'alg' => 'HS256',
                        'secret' => base64_encode(str_repeat('a', 32)),
                        'supported_algs' => [
                            'HS256' => 'Lcobucci\\JWT\\Signer\\Hmac\\Sha256',
                        ],
                        'symmetry_algs' => ['HS256'],
                        'asymmetric_algs' => [],
                        'blacklist_enabled' => false,
                        'blacklist_prefix' => 'jwt_black',
                        'ttl' => 3600,
                        'type' => 'mpop',
                        'scene' => [
                            'default' => [],
                            $scene => [],
                        ],
                    ],
                ];
            }

            public function get(string $key, mixed $default = null): mixed
            {
                $current = $this->items;
                foreach (explode('.', $key) as $segment) {
                    if (!is_array($current) || !array_key_exists($segment, $current)) {
                        return $default;
                    }
                    $current = $current[$segment];
                }

                return $current;
            }

            public function has(string $keys): bool
            {
                $sentinel = new \stdClass();

                return $this->get($keys, $sentinel) !== $sentinel;
            }

            public function set(string $key, mixed $value): void
            {
                $segments = explode('.', $key);
                $current = &$this->items;
                foreach ($segments as $segment) {
                    if (!isset($current[$segment]) || !is_array($current[$segment])) {
                        $current[$segment] = [];
                    }
                    $current = &$current[$segment];
                }

                $current = $value;
            }
        };

        return new Token($this->createStub(CacheInterface::class), $config);
    }
}
