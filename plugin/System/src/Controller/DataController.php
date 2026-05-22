<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Library\Interfaces\UserModelInterface;
use System\Service\DataService;

#[Auth(name: '系统数据管理')]
#[Controller(prefix: 'system/data')]
final class DataController extends CoreController
{
    /**
     * @param DataService $service 系统数据服务
     */
    public function __construct(
        protected DataService $service
    ) {}

    /**
     * 获取系统统计概览数据。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统数据统计', type: Auth::CHECK, menu: true)]
    public function index(): array
    {
        $this->success('获取成功', $this->service->getStatistics());
    }

    /**
     * 获取系统运行信息。
     */
    #[GetMapping(path: 'info')]
    #[Auth(name: '系统信息', type: Auth::CHECK, menu: false, code: 'system.data.index')]
    public function info(): array
    {
        $this->success('获取成功', $this->service->getSystemInfo());
    }

    /**
     * 获取系统配置快照。
     */
    #[GetMapping(path: 'config')]
    #[Auth(name: '系统配置', type: Auth::CHECK, menu: false, code: 'system.data.index')]
    public function config(): array
    {
        $this->success('获取成功', $this->service->getConfig());
    }

    /**
     * 获取系统模块能力信息。
     */
    #[GetMapping(path: 'capabilities')]
    #[Auth(name: '系统模块能力概览', type: Auth::CHECK, menu: false, code: 'system.data.index')]
    public function capabilities(): array
    {
        $this->success('获取成功', $this->service->getCapabilities());
    }

    /**
     * 获取工作台待办数据。
     */
    #[GetMapping(path: 'todos')]
    #[Auth(name: '工作台待办', type: Auth::CHECK, menu: false, code: 'system.data.index')]
    public function todos(): array
    {
        $this->success('获取成功', $this->service->getWorkbenchTodos());
    }

    /**
     * 获取界面 UI 元数据。
     */
    #[GetMapping(path: 'ui-meta')]
    #[Auth(name: '系统界面元信息', type: Auth::LOGIN, userModel: UserModelInterface::class)]
    public function uiMeta(): array
    {
        // UI 元信息是前后端共用的品牌展示配置；旧版前端可能在 Project 登录后仍访问本入口，
        // 因此只要求 token 合法，不限定 SystemUser，避免把 ProjectAccount 误踢下线。
        $this->success('获取成功', $this->service->getUiMeta());
    }

    /**
     * 清理系统业务缓存。
     */
    #[PostMapping(path: 'clear-cache')]
    #[Auth(name: '清理业务缓存', type: Auth::CHECK, menu: false, code: 'system.data.clear-cache')]
    #[Logger(name: '清理业务缓存')]
    public function clearCache(): array
    {
        $this->success('清理成功', $this->service->clearCache());
    }

    /**
     * 更新系统配置。
     */
    #[PutMapping(path: 'config')]
    #[Auth(name: '更新系统配置', type: Auth::CHECK, menu: false, code: 'system.data.save')]
    #[Logger(name: '更新系统配置')]
    public function updateConfig(RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->updateConfig($request->all()));
    }
}
