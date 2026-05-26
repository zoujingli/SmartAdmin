<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Context\Context;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\Constants\System;
use Library\CoreModel;
use Library\Interfaces\UserModelInterface;
use Library\Service\ScopeService;
use Psr\SimpleCache\CacheInterface;
use System\Service\AuthCacheService;

use function Hyperf\Config\config;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $username 用户名
 * @property string $nickname 用户昵称
 * @property string $phone 手机号码
 * @property string $email 邮箱地址
 * @property string $avatar 用户头像
 * @property string $signed 个性签名
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property string $login_ip 最后登录IP
 * @property string $login_time 最后登录时间
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property null|Collection|SystemRole[] $roles
 * @property null|Collection|SystemPost[] $posts
 * @property null|Collection|SystemDept[] $depts
 * @property array|mixed $extra 扩展数据(JSON)
 * @property mixed $password 密码哈希
 */
final class SystemUser extends CoreModel implements UserModelInterface
{
    use SoftDeletes;

    protected array $hidden = ['password'];

    protected array $fillable = ['id', 'tenant_id', 'username', 'nickname', 'phone', 'email', 'password', 'avatar', 'signed', 'status', 'remark', 'login_ip', 'login_time', 'extra', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $logRules = [
        'name' => '系统用户',
        'title' => 'username',
        'ignore' => ['password', 'extra', 'login_ip', 'login_time'],
        'fields' => [
            'username' => '用户名',
            'nickname' => '用户昵称',
            'phone' => '手机号码',
            'email' => '邮箱地址',
            'avatar' => '头像',
            'signed' => '个性签名',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    /**
     * 用户-角色关联。
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SystemRole::class, 'system_user_role', 'user_id', 'role_id');
    }

    /**
     * 用户-岗位关联。
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(SystemPost::class, 'system_user_post', 'user_id', 'post_id');
    }

    /**
     * 用户-部门关联。
     */
    public function depts(): BelongsToMany
    {
        return $this->belongsToMany(SystemDept::class, 'system_user_dept', 'user_id', 'dept_id');
    }

    /**
     * 扩展字段写入访问器。
     */
    public function setExtraAttribute(mixed $value): string
    {
        return $this->_toJson($value, 'extra');
    }

    /**
     * 扩展字段读取访问器。
     */
    public function getExtraAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 密码写入访问器（自动哈希）。
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 校验明文密码。
     */
    public function passVerify(string $pass): bool
    {
        return password_verify($pass, $this->getOriginal('password'));
    }

    /**
     * 获取用户主键 ID。
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * 获取用户名。
     */
    public function getName(): string
    {
        return $this->username;
    }

    /**
     * 判断是否为超级管理员。
     */
    public function isSuper(): bool
    {
        return $this->id == System::getSuperId();
    }

    /**
     * 获取用户权限节点集合。
     *
     * 优先读取请求上下文与缓存，未命中时按角色关联回源计算。
     */
    public function getPermissions(): array
    {
        if (!Status::isEnabled((int)$this->status)) {
            return [];
        }

        if ($this->isSuper()) {
            return ['*'];
        }

        $ckey = 'system_user_permissions_' . $this->id;
        $cached = Context::get($ckey);
        if (is_array($cached)) {
            return $cached;
        }

        $cache = _once(CacheInterface::class);
        $cacheKey = _once(AuthCacheService::class)->getUserCacheKey($this->id);
        $hit = $cache->get($cacheKey);
        if (is_array($hit)) {
            Context::set($ckey, $hit);
            return $hit;
        }

        $roleIds = $this->roles()
            ->where('system_role.status', Status::ENABLED)
            ->pluck('system_role.id')
            ->toArray();

        if ($roleIds === []) {
            Context::set($ckey, []);
            return [];
        }

        $permissions = SystemNode::query()
            ->where('system_node.status', Status::ENABLED)
            ->whereHas('roles', function ($query) use ($roleIds) {
                $query->whereIn('system_role.id', $roleIds)
                    ->where('system_role.status', Status::ENABLED);
            })
            ->pluck('system_node.node')
            ->toArray();

        $permissions = array_values(array_unique(array_filter(array_map('strval', $permissions))));

        $ttl = (int)config('permission.cache_ttl', 600);
        $cache->set($cacheKey, $permissions, $ttl);
        Context::set($ckey, $permissions);

        return $permissions;
    }

    /**
     * 判断是否拥有指定权限节点。
     */
    public function hasPermission(string $permission): bool
    {
        if (!Status::isEnabled((int)$this->status)) {
            return false;
        }

        if ($this->isSuper()) {
            return true;
        }

        $skey = 'system_user_permissions_set_' . $this->id;
        $set = Context::get($skey);
        if (!is_array($set)) {
            $list = $this->getPermissions();
            $set = array_fill_keys($list, true);
            Context::set($skey, $set);
        }

        return isset($set['*']) || isset($set[$permission]);
    }

    /**
     * @deprecated use ScopeService::getUserScope() instead
     */
    public function getDataScope(): int
    {
        return _once(ScopeService::class)->getUserScope($this);
    }

    /**
     * @deprecated use ScopeService::getUserIds() instead
     */
    public function getAccessibleDeptIds(): array
    {
        return _once(ScopeService::class)->getUserIds($this);
    }
}
