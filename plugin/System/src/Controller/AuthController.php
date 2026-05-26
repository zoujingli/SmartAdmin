<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Lcobucci\JWT\Token as JwtToken;
use Library\Auth\Exception\JwtException;
use Library\Auth\Exception\TokenExpireException;
use Library\Auth\Exception\TokenValidException;
use Library\Auth\Token;
use Library\Constants\Status;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Library\Exception\ErrorResponseException;
use Library\Exception\UnauthorizedResponseException;
use Library\Helper\RequestHelper;
use Library\Interfaces\UserModelInterface;
use System\Model\SystemUser;
use System\Service\DataService;
use System\Service\MenuService;
use System\Service\PasswordCryptoService;
use System\Service\UserService;

#[Controller(prefix: 'system/auth')]
final class AuthController extends CoreController
{
    /**
     * @param UserService $user 用户认证与资料服务
     * @param Token $token Token 管理服务
     * @param MenuService $menus 菜单与首页路径服务
     * @param DataService $data 系统界面元信息服务
     * @param PasswordCryptoService $passwordCrypto 密码传输层解密服务
     */
    public function __construct(
        protected UserService $user,
        protected Token $token,
        protected MenuService $menus,
        protected DataService $data,
        protected PasswordCryptoService $passwordCrypto,
    ) {}

    /**
     * 获取密码传输层加密参数。
     *
     * 客户端先用该接口获取公钥和一次性 nonce，再用 RSA-OAEP(SHA-1) 加密密码字段。
     */
    #[GetMapping(path: 'password-crypto')]
    public function passwordCrypto(RequestInterface $request): array
    {
        $count = (int)$request->input('count', 1);

        $this->success('获取成功', $this->passwordCrypto->issueParameters($count));
    }

    /**
     * 用户账号密码登录。
     */
    #[PostMapping(path: 'login')]
    #[Logger(name: '用户登录', remark: '用户登录接口', excludeFields: ['password'])]
    public function login(RequestInterface $request): array
    {
        $params = $request->all();
        $username = trim((string)($params['username'] ?? ''));

        if ($username === '' || !array_key_exists('password', $params)) {
            throw new ErrorResponseException('用户名和密码不能为空');
        }

        $password = $this->passwordCrypto->decryptPassword(
            $params['password'],
            PasswordCryptoService::PURPOSE_LOGIN_PASSWORD,
            'password'
        );
        if ($password === '') {
            throw new ErrorResponseException('用户名和密码不能为空');
        }

        /** @var null|SystemUser $user */
        $user = $this->user->getUserByUsername($username);
        if (!$user || !$user->passVerify($password)) {
            throw new ErrorResponseException('用户名或密码错误');
        }

        if (!Status::isEnabled((int)$user->status)) {
            throw new ErrorResponseException('用户已被禁用');
        }

        // 登录 IP 统一读取代理头，避免 Nginx 反代后落库为 127.0.0.1。
        $this->user->updateLastLogin($user->id, RequestHelper::getClientIp($request));
        $user->load(['roles', 'depts', 'posts']);

        $this->success('登录成功', [
            'token' => $this->user->login($user)->toString(),
            'user' => $user->toArray(),
            'auth_user_model' => SystemUser::class,
        ]);
    }

    /**
     * 刷新登录 Token。
     */
    #[PostMapping(path: 'refresh')]
    #[Auth(name: '刷新登录令牌', type: Auth::LOGIN)]
    public function refresh(): array
    {
        try {
            $this->currentUser();
            $newToken = $this->token->refresh();
            $token = $newToken instanceof JwtToken ? $newToken->toString() : (string)$newToken;

            $this->success('刷新成功', $token);
        } catch (JwtException|TokenExpireException|TokenValidException) {
            // 刷新令牌失败同样走标准响应异常，避免认证接口直接返回裸 JSON。
            throw new UnauthorizedResponseException('未登录或登录状态已失效');
        }
    }

    /**
     * 获取当前登录用户资料。
     */
    #[PostMapping(path: 'profile')]
    #[Auth(name: '获取个人资料', type: Auth::LOGIN)]
    public function profile(): array
    {
        $userId = $this->currentUser()->getId();

        $this->success('获取成功', $this->composeProfileData($userId));
    }

    /**
     * 获取界面 UI 元信息（主题、偏好默认值等）。
     */
    #[GetMapping(path: 'ui-meta')]
    public function uiMeta(): array
    {
        $this->success('获取成功', $this->data->getUiMeta());
    }

    /**
     * 更新当前用户个人资料。
     */
    #[PutMapping(path: 'profile')]
    #[Auth(name: '更新个人资料', type: Auth::LOGIN)]
    #[Logger(name: '更新个人资料')]
    public function updateProfile(RequestInterface $request): array
    {
        $userId = $this->currentUser()->getId();

        $this->user->updateSelfProfile($userId, $request->all());

        // 走 success 异常链路，由 ResponseExceptionHandler + #[Logger] 写入操作日志（final 控制器上 AOP 可能未织入）
        $this->success('更新成功', $this->composeProfileData($userId));
    }

    /**
     * 保存当前用户界面偏好配置。
     */
    #[PutMapping(path: 'preferences')]
    #[Auth(name: '保存界面配置', type: Auth::LOGIN)]
    #[Logger(name: '保存界面配置')]
    public function updatePreferences(RequestInterface $request): array
    {
        $userId = $this->currentUser()->getId();

        $this->user->updateSelfPreferences($userId, $request->all());

        $this->success('保存成功', $this->composeProfileData($userId));
    }

    /**
     * 当前用户修改密码。
     */
    #[PutMapping(path: 'password')]
    #[Auth(name: '修改登录密码', type: Auth::LOGIN)]
    #[Logger(name: '修改登录密码', remark: '当前用户修改登录密码', excludeFields: ['old_password', 'new_password'])]
    public function changePassword(RequestInterface $request): array
    {
        $userId = $this->currentUser()->getId();

        $body = $request->all();
        $body = $this->passwordCrypto->decryptFields($body, [
            'old_password' => PasswordCryptoService::PURPOSE_CHANGE_OLD_PASSWORD,
            'new_password' => PasswordCryptoService::PURPOSE_CHANGE_NEW_PASSWORD,
        ]);
        $oldPassword = (string)($body['old_password'] ?? '');
        $newPassword = (string)($body['new_password'] ?? '');
        $this->user->changeOwnPassword($userId, $oldPassword, $newPassword);

        $this->success('密码修改成功', []);
    }

    /**
     * 获取当前用户权限码集合。
     */
    #[GetMapping(path: 'codes')]
    #[Auth(name: '获取个人权限码', type: Auth::LOGIN)]
    public function codes(): array
    {
        $codes = is_super_login()
            ? ['*']
            : $this->user->getUserAccessCodes($this->currentUser()->getId());

        $this->success('获取成功', $codes);
    }

    /**
     * 用户退出登录。
     */
    #[PostMapping(path: 'logout')]
    #[Logger(name: '用户登出', remark: '用户登出接口')]
    public function logout(): array
    {
        $this->user->logout();

        $this->success('登出成功', []);
    }

    /**
     * 与 {@see profile()} 接口一致的资料结构，供客户端刷新会话展示。
     *
     * @return array<string, mixed>
     */
    private function composeProfileData(int $userId): array
    {
        $profile = $this->user->getUserWithRelations($userId, false);
        $roles = array_values(array_filter(array_unique(array_map(
            static fn (array $role): string => (string)($role['name'] ?? ''),
            $profile['roles'] ?? []
        ))));

        return [
            'userId' => (string)($profile['id'] ?? $userId),
            'username' => (string)($profile['username'] ?? ''),
            'realName' => (string)($profile['nickname'] ?? $profile['username'] ?? ''),
            'avatar' => (string)($profile['avatar'] ?? ''),
            'roles' => $roles,
            'desc' => (string)($profile['signed'] ?? ''),
            'homePath' => $this->menus->getUserHomePath(),
            'token' => $this->token->getHeaderToken(),
            'profile' => $this->buildProfilePayload($profile),
            'auth_user_model' => SystemUser::class,
        ];
    }

    /**
     * 获取当前登录用户模型，不存在时抛出未登录异常。
     */
    private function currentUser(): UserModelInterface
    {
        // System 认证入口只服务后台用户，拒绝 ProjectAccount 等前台 Token 混用后台资料与刷新接口。
        $user = user(SystemUser::class) ?? $this->user->getUser(null, SystemUser::class);
        if (!$user) {
            throw new UnauthorizedResponseException('未登录');
        }

        return $user;
    }

    /**
     * 规范化当前用户资料，客户端登录后只依赖这份结构。
     *
     * @param array<string, mixed> $user
     */
    private function buildProfilePayload(array $user): array
    {
        return [
            'id' => (int)($user['id'] ?? 0),
            'tenant_id' => (int)($user['tenant_id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'nickname' => (string)($user['nickname'] ?? ''),
            'phone' => (string)($user['phone'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'avatar' => (string)($user['avatar'] ?? ''),
            'signed' => (string)($user['signed'] ?? ''),
            'status' => (int)($user['status'] ?? 0),
            'remark' => (string)($user['remark'] ?? ''),
            'login_ip' => (string)($user['login_ip'] ?? ''),
            'login_time' => (string)($user['login_time'] ?? ''),
            'extra' => $user['extra'] ?? [],
            'created_by' => (int)($user['created_by'] ?? 0),
            'updated_by' => (int)($user['updated_by'] ?? 0),
            'created_at' => $this->stringifyDate($user['created_at'] ?? null),
            'updated_at' => $this->stringifyDate($user['updated_at'] ?? null),
            'deleted_at' => $user['deleted_at'] ?? null,
            'roles' => array_map(static fn (array $role): array => [
                'id' => (int)($role['id'] ?? 0),
                'name' => (string)($role['name'] ?? ''),
                'code' => (string)($role['code'] ?? ''),
                'scope' => (int)($role['scope'] ?? 0),
                'sort' => (int)($role['sort'] ?? 0),
                'status' => (int)($role['status'] ?? 0),
                'remark' => (string)($role['remark'] ?? ''),
                'created_by' => (int)($role['created_by'] ?? 0),
                'updated_by' => (int)($role['updated_by'] ?? 0),
                'created_at' => $role['created_at'] ?? null,
                'updated_at' => $role['updated_at'] ?? null,
                'pivot' => $role['pivot'] ?? null,
            ], $user['roles'] ?? []),
            'depts' => array_map(static fn (array $dept): array => [
                'id' => (int)($dept['id'] ?? 0),
                'name' => (string)($dept['name'] ?? ''),
                'pid' => (int)($dept['pid'] ?? 0),
                'status' => (int)($dept['status'] ?? 0),
            ], $user['depts'] ?? []),
            'posts' => array_map(static fn (array $post): array => [
                'id' => (int)($post['id'] ?? 0),
                'name' => (string)($post['name'] ?? ''),
                'code' => (string)($post['code'] ?? ''),
                'status' => (int)($post['status'] ?? 0),
            ], $user['posts'] ?? []),
        ];
    }

    /**
     * 统一日期字段输出格式。
     */
    private function stringifyDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_scalar($value) && $value !== '' ? (string)$value : null;
    }
}
