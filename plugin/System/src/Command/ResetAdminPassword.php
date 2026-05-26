<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Library\Constants\Status;
use Symfony\Component\Console\Input\InputOption;
use System\Model\SystemUser;
use System\Support\SystemBootstrapSeed;

/**
 * 重置系统超级管理员密码。
 *
 * 该命令只处理初始化超级管理员账号 `admin`，用于本地开发或生产应急恢复。
 * 密码写入仍走 SystemUser 模型访问器，由 password_hash() 生成数据库哈希。
 */
#[Command(name: 'xadmin:password:reset', description: 'Reset admin password')]
final class ResetAdminPassword extends HyperfCommand
{
    /**
     * 定义命令参数。
     */
    public function configure(): void
    {
        $this->setDescription('Reset admin password')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'New admin password. Omit it to input a hidden password interactively')
            ->addOption('default', null, InputOption::VALUE_NONE, 'Reset password to the bootstrap default: admin')
            ->addOption('enable', null, InputOption::VALUE_NONE, 'Enable the admin account while resetting password')
            ->addOption('restore', null, InputOption::VALUE_NONE, 'Restore the admin account if it is soft deleted')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit report as JSON');
    }

    /**
     * 执行密码重置。
     *
     * 明文密码只来自命令行参数或隐藏输入，不输出到终端；保存时由模型访问器统一哈希。
     */
    public function handle(): int
    {
        $json = (bool)$this->input->getOption('json');
        $enable = (bool)$this->input->getOption('enable');
        $restore = (bool)$this->input->getOption('restore');
        $username = SystemBootstrapSeed::SUPER_USERNAME;

        if ((bool)$this->input->getOption('default') && is_string($this->input->getOption('password')) && $this->input->getOption('password') !== '') {
            return $this->fail('Use only one of --password or --default.', $json);
        }

        $password = $this->resolvePassword();
        if ($password === null) {
            return $this->fail('Password is required or confirmation does not match. Use --password, --default, or run interactively.', $json);
        }
        if ($password !== SystemBootstrapSeed::DEFAULT_PASSWORD && strlen($password) < 6) {
            return $this->fail('Password length must be at least 6 characters.', $json);
        }

        /** @var null|SystemUser $user */
        $user = SystemUser::query()
            ->withTrashed()
            ->where('username', $username)
            ->first();
        if (!$user instanceof SystemUser) {
            return $this->fail('Admin user not found.', $json, ['username' => $username]);
        }

        $wasDeleted = $user->trashed();
        if ($wasDeleted && !$restore) {
            return $this->fail('Admin user is soft deleted. Re-run with --restore to recover it.', $json, [
                'username' => $username,
                'user_id' => (int)$user->id,
            ]);
        }

        if ($wasDeleted) {
            $user->restore();
        }
        if ($enable) {
            $user->status = Status::ENABLED;
        }

        $user->fill(['password' => $password]);
        if (!$user->save()) {
            return $this->fail('Failed to reset admin password.', $json, [
                'username' => $username,
                'user_id' => (int)$user->id,
            ]);
        }

        $report = [
            'success' => true,
            'username' => $username,
            'user_id' => (int)$user->id,
            'restored' => $wasDeleted,
            'enabled' => $enable,
            'password' => '***',
        ];

        if ($json) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->line(sprintf('Admin password reset successfully. user_id=%d username=%s', (int)$user->id, $username));
            if ($wasDeleted) {
                $this->warn('Admin user was restored from recycle state.');
            }
            if ($enable) {
                $this->line('Admin user was enabled.');
            }
            $this->warn('Existing login tokens are not forcibly invalidated by this command.');
        }

        return self::SUCCESS;
    }

    /**
     * 解析新密码。
     */
    private function resolvePassword(): ?string
    {
        $useDefault = (bool)$this->input->getOption('default');
        $passwordOption = $this->input->getOption('password');
        $hasPasswordOption = is_string($passwordOption) && $passwordOption !== '';

        if ($useDefault) {
            return SystemBootstrapSeed::DEFAULT_PASSWORD;
        }
        if ($hasPasswordOption) {
            return $passwordOption;
        }
        if (!$this->input->isInteractive()) {
            return null;
        }

        $password = (string)$this->secret('New admin password');
        $confirm = (string)$this->secret('Confirm admin password');
        if ($password === '' || $password !== $confirm) {
            return null;
        }

        return $password;
    }

    /**
     * 输出失败响应并返回标准失败退出码。
     *
     * @param array<string, mixed> $context
     */
    private function fail(string $message, bool $json, array $context = []): int
    {
        if ($json) {
            $this->line(json_encode(array_merge([
                'success' => false,
                'message' => $message,
            ], $context), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
