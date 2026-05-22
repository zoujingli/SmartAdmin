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
use System\Mapper\UserMapper;
use System\Model\SystemUser;

/**
 * System 用户密码凭证服务。
 *
 * 只处理密码业务规则和写入，密码传输层解密仍由 Controller 调用 PasswordCryptoService 完成。
 */
final class UserPasswordCredentialService
{
    public function __construct(
        private readonly UserMapper $mapper,
    ) {}

    /**
     * 新增用户必须提交符合强度要求的密码。
     *
     * @param array<string, mixed> $data
     */
    public function assertCreatePassword(array $data): void
    {
        $password = (string)($data['password'] ?? '');
        if ($password === '') {
            throw new ErrorResponseException('密码不能为空');
        }
        if (strlen($password) < 6) {
            throw new ErrorResponseException('密码长度至少 6 位');
        }
    }

    /**
     * 更新用户时空密码表示不修改密码；非空密码必须符合强度要求。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizeUpdatePassword(array $data): array
    {
        if (!array_key_exists('password', $data)) {
            return $data;
        }

        if ((string)$data['password'] === '') {
            unset($data['password']);
            return $data;
        }

        if (strlen((string)$data['password']) < 6) {
            throw new ErrorResponseException('密码长度至少 6 位');
        }

        return $data;
    }

    /**
     * 当前用户修改登录密码，必须先校验旧密码。
     */
    public function changeOwnPassword(int $userId, string $oldPassword, string $newPassword): void
    {
        if ($newPassword === '') {
            throw new ErrorResponseException('新密码不能为空');
        }
        if (strlen($newPassword) < 6) {
            throw new ErrorResponseException('新密码长度至少 6 位');
        }

        $user = $this->mapper->read($userId, ['*'], false);
        if (!$user instanceof SystemUser) {
            throw new ErrorResponseException('用户不存在');
        }
        if (!$user->passVerify($oldPassword)) {
            throw new ErrorResponseException('原密码错误');
        }

        $user->fill(['password' => $newPassword])->save();
    }

    /**
     * 管理员重置指定用户密码。
     *
     * @return array<string, mixed>
     */
    public function resetPassword(int $id, string $password): array
    {
        if ($password === '') {
            throw new ErrorResponseException('密码不能为空');
        }
        if (strlen($password) < 6) {
            throw new ErrorResponseException('密码长度至少 6 位');
        }
        if (!$this->mapper->changePassword($id, $password)) {
            throw new ErrorResponseException('用户不存在或无权限操作');
        }

        return $this->mapper->getUserWithRelations($id)->toArray();
    }
}
