<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Service;

use Library\Exception\ErrorResponseException;
use Psr\SimpleCache\CacheInterface;

use function Hyperf\Config\config;

/**
 * 密码传输层加密服务。
 *
 * 该服务只负责“请求传输值 -> 业务明文密码”的短生命周期转换：
 * - 公钥和 nonce 公开发放，私钥由服务端自动维护在运行目录。
 * - nonce 一次性消费，任意解密尝试都会删除 nonce，避免重放和反复试探。
 * - 解密后的明文只在当前调用栈中短暂存在，后续仍由 SystemUser 访问器 password_hash() 入库。
 */
final class PasswordCryptoService
{
    public const ALG = 'RSA-OAEP';
    public const HASH = 'SHA-1';
    private const OPENSSL_CONFIG = <<<CONF
[ req ]
distinguished_name = req_distinguished_name

[ req_distinguished_name ]

[ v3_ca ]
CONF;

    public const PURPOSE_LOGIN_PASSWORD = 'system.auth.login.password';
    public const PURPOSE_CHANGE_OLD_PASSWORD = 'system.auth.password.old_password';
    public const PURPOSE_CHANGE_NEW_PASSWORD = 'system.auth.password.new_password';
    public const PURPOSE_USER_CREATE_PASSWORD = 'system.user.create.password';
    public const PURPOSE_USER_UPDATE_PASSWORD = 'system.user.update.password';
    public const PURPOSE_USER_RESET_PASSWORD = 'system.user.reset_password.password';

    /**
     * @param CacheInterface $cache nonce 缓存，必须支持 TTL；生产多实例需使用共享缓存如 Redis。
     */
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    /**
     * 发放客户端加密密码所需的公钥和一次性 nonce。
     *
     * @return array{kid:string,alg:string,hash:string,public_key:string,nonce_ttl:int,nonces:array<int,string>}
     */
    public function issueParameters(int $count = 1): array
    {
        $this->ensureKeyPairAvailable();

        $count = max(1, min($count, 20));
        $ttl = $this->nonceTtl();
        $kid = $this->keyId();
        $nonces = [];
        for ($index = 0; $index < $count; $index++) {
            $nonce = bin2hex(random_bytes(24));
            $this->cache->set($this->nonceCacheKey($kid, $nonce), [
                'kid' => $kid,
                'issued_at' => time(),
            ], $ttl);
            $nonces[] = $nonce;
        }

        return [
            'kid' => $kid,
            'alg' => self::ALG,
            'hash' => self::HASH,
            'public_key' => $this->readPublicKey(),
            'nonce_ttl' => $ttl,
            'nonces' => $nonces,
        ];
    }

    /**
     * 批量解密请求体中的密码字段。
     *
     * @param array<string, mixed> $data 请求体
     * @param array<string, string> $fieldPurposes 字段名 => 加密用途
     * @return array<string, mixed>
     */
    public function decryptFields(array $data, array $fieldPurposes): array
    {
        foreach ($fieldPurposes as $field => $purpose) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $data[$field] = $this->decryptPassword($data[$field], $purpose, $field);
        }

        return $data;
    }

    /**
     * 解密单个密码字段。
     *
     * 客户端必须提交 `{kid, nonce, ciphertext}` 对象；提交明文字符串会被直接拒绝。
     */
    public function decryptPassword(mixed $payload, string $purpose, string $field = 'password'): string
    {
        $this->ensureKeyPairAvailable();

        if (!is_array($payload)) {
            throw new ErrorResponseException(sprintf('%s 必须使用加密对象传输', $field));
        }

        $kid = $this->stringField($payload, 'kid', $field);
        $nonce = $this->stringField($payload, 'nonce', $field);
        $ciphertext = $this->stringField($payload, 'ciphertext', $field);
        if ($kid !== $this->keyId()) {
            throw new ErrorResponseException('密码加密密钥标识无效');
        }

        $cacheKey = $this->nonceCacheKey($kid, $nonce);
        $nonceState = $this->cache->get($cacheKey);
        if (!is_array($nonceState)) {
            throw new ErrorResponseException('密码加密随机数无效或已过期');
        }

        // nonce 一旦被使用立即删除；即使后续密文错误也不能再次用于探测。
        $this->cache->delete($cacheKey);

        $cipherBinary = base64_decode($ciphertext, true);
        if (!is_string($cipherBinary) || $cipherBinary === '') {
            throw new ErrorResponseException('密码密文格式无效');
        }

        $privateKey = openssl_pkey_get_private($this->readPrivateKey());
        if ($privateKey === false) {
            throw new ErrorResponseException('密码加密私钥无效');
        }

        $plain = '';
        $ok = openssl_private_decrypt($cipherBinary, $plain, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok) {
            throw new ErrorResponseException('密码密文解密失败');
        }

        $decoded = json_decode($plain, true);
        if (!is_array($decoded)) {
            throw new ErrorResponseException('密码密文载荷格式无效');
        }

        if (($decoded['v'] ?? null) !== 1) {
            throw new ErrorResponseException('密码密文版本无效');
        }
        if (($decoded['purpose'] ?? '') !== $purpose) {
            throw new ErrorResponseException('密码密文用途不匹配');
        }
        if (($decoded['nonce'] ?? '') !== $nonce) {
            throw new ErrorResponseException('密码密文随机数不匹配');
        }

        $ts = (int)($decoded['ts'] ?? 0);
        $now = time();
        if ($ts <= 0 || $ts < ($now - $this->nonceTtl()) || $ts > ($now + 30)) {
            throw new ErrorResponseException('密码密文已过期');
        }

        if (!array_key_exists('value', $decoded) || !is_string($decoded['value'])) {
            throw new ErrorResponseException('密码密文缺少密码值');
        }

        return $decoded['value'];
    }

    /**
     * 自动维护运行时 RSA 私钥。
     *
     * 部署人员不需要配置证书或公钥路径；首次请求会在 runtime/keys 下生成私钥，
     * 后续公钥均由该私钥实时导出。多进程并发首次生成时通过文件锁串行化。
     */
    private function ensureKeyPairAvailable(): void
    {
        $keyPath = $this->keyPath();
        if ($this->hasUsablePrivateKeyFile($keyPath)) {
            return;
        }

        $directory = dirname($keyPath);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ErrorResponseException('密码加密密钥目录不可写');
        }

        $lock = @fopen($keyPath . '.lock', 'c');
        if ($lock === false) {
            throw new ErrorResponseException('密码加密密钥锁不可写');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new ErrorResponseException('密码加密密钥锁获取失败');
            }
            if ($this->hasUsablePrivateKeyFile($keyPath)) {
                return;
            }

            $openSslOptions = $this->openSslOptions();
            $resource = openssl_pkey_new([
                'private_key_bits' => $this->keyBits(),
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                // Swoole CLI 或精简容器环境可能没有系统 openssl.cnf，显式使用运行时自动维护的最小配置。
                'config' => $openSslOptions['config'],
            ]);
            if ($resource === false || !openssl_pkey_export($resource, $privatePem, null, $openSslOptions)) {
                throw new ErrorResponseException('密码加密密钥生成失败');
            }

            if (file_put_contents($keyPath, $privatePem, LOCK_EX) === false) {
                throw new ErrorResponseException('密码加密私钥不可写');
            }
            @chmod($keyPath, 0600);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringField(array $payload, string $name, string $field): string
    {
        $value = $payload[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw new ErrorResponseException(sprintf('%s.%s 不能为空', $field, $name));
        }

        return $value;
    }

    private function nonceCacheKey(string $kid, string $nonce): string
    {
        return sprintf('system:password_crypto:nonce:%s:%s', $kid, hash('sha256', $nonce));
    }

    private function readPrivateKey(): string
    {
        $content = @file_get_contents($this->keyPath());
        if (!is_string($content) || $content === '') {
            throw new ErrorResponseException('密码加密私钥不可读');
        }

        return $content;
    }

    private function readPublicKey(): string
    {
        $privateKey = openssl_pkey_get_private($this->readPrivateKey());
        if ($privateKey === false) {
            throw new ErrorResponseException('密码加密私钥无效');
        }
        $details = openssl_pkey_get_details($privateKey);
        $content = is_array($details) ? (string)($details['key'] ?? '') : '';
        if ($content === '') {
            throw new ErrorResponseException('密码加密公钥不可读');
        }

        return $content;
    }

    private function keyId(): string
    {
        return 'runtime-' . substr(hash('sha256', $this->readPublicKey()), 0, 16);
    }

    private function keyPath(): string
    {
        return (string)config('password_crypto.key_path', runpath('runtime/keys/password_crypto.pem'));
    }

    /**
     * 判断现有私钥文件是否可继续使用。
     *
     * 自动生成过程中如果进程异常退出，可能留下空文件或损坏文件；此时必须重新生成，
     * 不能只按文件存在判断，否则后续接口会反复失败在私钥读取或公钥导出阶段。
     */
    private function hasUsablePrivateKeyFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $content = @file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            return false;
        }

        return openssl_pkey_get_private($content) !== false;
    }

    /**
     * 生成 RSA 密钥时使用的 OpenSSL 配置。
     *
     * PHP OpenSSL 扩展在部分二进制运行时会尝试读取系统 openssl.cnf；如果系统文件不存在，
     * openssl_pkey_new() 会失败。这里在密钥目录内自动维护最小配置，避免部署人员手工配置证书环境。
     *
     * @return array{config:string}
     */
    private function openSslOptions(): array
    {
        $path = dirname($this->keyPath()) . DIRECTORY_SEPARATOR . 'openssl.cnf';
        $content = is_file($path) ? @file_get_contents($path) : false;
        if (!is_string($content) || !str_contains($content, '[ req ]')) {
            if (file_put_contents($path, self::OPENSSL_CONFIG . PHP_EOL, LOCK_EX) === false) {
                throw new ErrorResponseException('密码加密 OpenSSL 配置不可写');
            }
            @chmod($path, 0600);
        }

        return ['config' => $path];
    }

    private function nonceTtl(): int
    {
        return 120;
    }

    private function keyBits(): int
    {
        return max(2048, (int)config('password_crypto.key_bits', 3072));
    }
}
