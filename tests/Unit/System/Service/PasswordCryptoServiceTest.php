<?php

declare(strict_types=1);

namespace Tests\Unit\System\Service;

use DateInterval;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\TranslatorInterface;
use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use System\Service\PasswordCryptoService;

#[CoversClass(PasswordCryptoService::class)]
#[UsesClass(ErrorResponseException::class)]
final class PasswordCryptoServiceTest extends TestCase
{
    private ?ContainerInterface $originalContainer = null;

    protected function setUp(): void
    {
        $this->originalContainer = ApplicationContext::getContainer();
    }

    protected function tearDown(): void
    {
        if ($this->originalContainer instanceof ContainerInterface) {
            ApplicationContext::setContainer($this->originalContainer);
        }
    }

    public function testIssueParametersAndDecryptsCipherPayload(): void
    {
        [$service, $config] = $this->makeService();
        $params = $service->issueParameters(2);

        $this->assertStringStartsWith('runtime-', $params['kid']);
        $this->assertSame('RSA-OAEP', $params['alg']);
        $this->assertSame('SHA-1', $params['hash']);
        $this->assertCount(2, $params['nonces']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $params['public_key']);
        $this->assertFileExists($config['password_crypto']['key_path']);

        $payload = $this->encryptPayload(
            $params,
            PasswordCryptoService::PURPOSE_LOGIN_PASSWORD,
            'admin123'
        );

        $this->assertSame('admin123', $service->decryptPassword($payload, PasswordCryptoService::PURPOSE_LOGIN_PASSWORD));

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('密码加密随机数无效或已过期');
        $service->decryptPassword($payload, PasswordCryptoService::PURPOSE_LOGIN_PASSWORD);
    }

    public function testRejectsPlainTextPayload(): void
    {
        [$service] = $this->makeService();

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('password 必须使用加密对象传输');

        $service->decryptPassword('admin123', PasswordCryptoService::PURPOSE_LOGIN_PASSWORD);
    }

    public function testRejectsPurposeMismatchAndConsumesNonce(): void
    {
        [$service] = $this->makeService();
        $params = $service->issueParameters(1);
        $payload = $this->encryptPayload(
            $params,
            PasswordCryptoService::PURPOSE_USER_RESET_PASSWORD,
            'admin123'
        );

        try {
            $service->decryptPassword($payload, PasswordCryptoService::PURPOSE_LOGIN_PASSWORD);
            $this->fail('Expected purpose mismatch exception was not thrown.');
        } catch (ErrorResponseException $exception) {
            $this->assertSame('密码密文用途不匹配', $exception->getMessage());
        }

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('密码加密随机数无效或已过期');
        $service->decryptPassword($payload, PasswordCryptoService::PURPOSE_LOGIN_PASSWORD);
    }

    public function testRejectsTamperedCiphertext(): void
    {
        [$service] = $this->makeService();
        $params = $service->issueParameters(1);
        $payload = $this->encryptPayload(
            $params,
            PasswordCryptoService::PURPOSE_LOGIN_PASSWORD,
            'admin123'
        );
        $bytes = base64_decode($payload['ciphertext'], true);
        $this->assertIsString($bytes);
        $bytes[0] = chr(ord($bytes[0]) ^ 0x01);
        $payload['ciphertext'] = base64_encode($bytes);

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('密码密文解密失败');

        $service->decryptPassword($payload, PasswordCryptoService::PURPOSE_LOGIN_PASSWORD);
    }

    public function testAutoMaintainsRuntimeKeyWhenMissing(): void
    {
        $cache = new ArrayCache();
        $directory = sys_get_temp_dir() . '/smartadmin-password-crypto-' . bin2hex(random_bytes(6));
        $keyPath = $directory . '/password_crypto.pem';
        $config = [
            'app_env' => 'prod',
            'password_crypto' => [
                'key_path' => $keyPath,
                'key_bits' => 3072,
            ],
        ];
        ApplicationContext::setContainer(new PasswordCryptoTestContainer($config));

        $params = (new PasswordCryptoService($cache))->issueParameters();

        $this->assertFileExists($keyPath);
        $this->assertFileExists($directory . '/openssl.cnf');
        $this->assertStringStartsWith('runtime-', $params['kid']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $params['public_key']);
    }

    public function testRegeneratesBrokenRuntimeKeyFile(): void
    {
        $cache = new ArrayCache();
        $directory = sys_get_temp_dir() . '/smartadmin-password-crypto-' . bin2hex(random_bytes(6));
        $keyPath = $directory . '/password_crypto.pem';
        mkdir($directory, 0755, true);
        file_put_contents($keyPath, '');
        ApplicationContext::setContainer(new PasswordCryptoTestContainer([
            'app_env' => 'prod',
            'password_crypto' => [
                'key_path' => $keyPath,
                'key_bits' => 3072,
            ],
        ]));

        $params = (new PasswordCryptoService($cache))->issueParameters();

        $this->assertFileExists($keyPath);
        $this->assertGreaterThan(0, (int)filesize($keyPath));
        $this->assertStringStartsWith('runtime-', $params['kid']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $params['public_key']);
    }

    /**
     * @return array{0:PasswordCryptoService,1:array<string,mixed>}
     */
    private function makeService(): array
    {
        $directory = sys_get_temp_dir() . '/smartadmin-password-crypto-' . bin2hex(random_bytes(6));
        $keyPath = $directory . '/password_crypto.pem';

        $config = [
            'app_env' => 'prod',
            'password_crypto' => [
                'key_path' => $keyPath,
                'key_bits' => 3072,
            ],
        ];
        ApplicationContext::setContainer(new PasswordCryptoTestContainer($config));

        return [new PasswordCryptoService(new ArrayCache()), $config];
    }

    /**
     * @param array{kid:string,public_key:string,nonces:array<int,string>} $params
     * @return array{kid:string,nonce:string,ciphertext:string}
     */
    private function encryptPayload(array $params, string $purpose, string $value): array
    {
        $nonce = $params['nonces'][0];
        $plain = json_encode([
            'v' => 1,
            'purpose' => $purpose,
            'nonce' => $nonce,
            'ts' => time(),
            'value' => $value,
        ], JSON_THROW_ON_ERROR);

        $ok = openssl_public_encrypt($plain, $ciphertext, $params['public_key'], OPENSSL_PKCS1_OAEP_PADDING);
        $this->assertTrue($ok, 'RSA-OAEP test encryption failed.');

        return [
            'kid' => $params['kid'],
            'nonce' => $nonce,
            'ciphertext' => base64_encode($ciphertext),
        ];
    }

}

/**
 * 单测内存缓存，按秒级时间戳模拟 SimpleCache TTL 语义。
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value:mixed,expires_at:null|int}> */
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            return $default;
        }
        $item = $this->values[$key];
        if ($item['expires_at'] !== null && $item['expires_at'] < time()) {
            unset($this->values[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $expiresAt = is_int($ttl) ? time() + $ttl : null;
        $this->values[$key] = ['value' => $value, 'expires_at' => $expiresAt];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->get((string)$key, $default);
        }

        return $items;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string)$key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string)$key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}

/**
 * 提供 config() 与异常翻译依赖，避免测试依赖完整 Hyperf 容器。
 */
final class PasswordCryptoTestContainer implements ContainerInterface
{
    private ConfigInterface $config;
    private TranslatorInterface $translator;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = new class ($config) implements ConfigInterface {
            /**
             * @param array<string, mixed> $values
             */
            public function __construct(private array $values) {}

            public function get(string $key, mixed $default = null): mixed
            {
                $segments = explode('.', $key);
                $value = $this->values;
                foreach ($segments as $segment) {
                    if (!is_array($value) || !array_key_exists($segment, $value)) {
                        return $default;
                    }
                    $value = $value[$segment];
                }

                return $value;
            }

            public function has(string $key): bool
            {
                return $this->get($key, '__missing__') !== '__missing__';
            }

            public function set(string $key, mixed $value): void
            {
                $segments = explode('.', $key);
                $target = &$this->values;
                foreach ($segments as $segment) {
                    if (!isset($target[$segment]) || !is_array($target[$segment])) {
                        $target[$segment] = [];
                    }
                    $target = &$target[$segment];
                }
                $target = $value;
            }
        };

        $this->translator = new class implements TranslatorInterface {
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
                return 'zh_CN';
            }

            public function setLocale(string $locale) {}
        };
    }

    public function get(string $id)
    {
        return match ($id) {
            ConfigInterface::class => $this->config,
            TranslatorInterface::class => $this->translator,
            default => throw new class (sprintf('Service "%s" not found.', $id)) extends \RuntimeException implements NotFoundExceptionInterface {},
        };
    }

    public function has(string $id): bool
    {
        return in_array($id, [ConfigInterface::class, TranslatorInterface::class], true);
    }
}
