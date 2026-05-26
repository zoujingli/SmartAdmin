<?php

declare(strict_types=1);

namespace Plugin\Project\Model;

use Hyperf\Database\Model\Builder;
use Library\CoreModel;
use Library\Interfaces\UserModelInterface;

/** SmartAdmin 公共仓独立单测使用的 Project 账号桩，只验证 LoginService 不调用 toArray() 的租户边界。 */
class ProjectAccount extends CoreModel implements UserModelInterface
{
    protected ?string $table = 'project_account';
    protected array $fillable = ['id', 'tenant_id', 'username', 'nickname', 'status'];

    public static function query(): Builder
    {
        throw new \LogicException('PHPStan stub only.');
    }

    public function getId(): int { return (int) ($this->getAttribute('id') ?? 0); }
    public function getName(): string { return (string) ($this->getAttribute('username') ?? ''); }
    public function isSuper(): bool { return false; }
    public function getPermissions(): array { return []; }
    public function hasPermission(string $permission): bool { return false; }
    public function toArray(): array
    {
        throw new \LogicException('ProjectAccount::toArray() must not be called before tenant context is ready.');
    }
}
