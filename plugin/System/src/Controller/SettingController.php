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
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use System\Service\SettingService;

#[Auth(name: '系统参数管理')]
#[Controller(prefix: 'system/setting')]
final class SettingController extends CoreController
{
    /**
     * @param SettingService $service 系统参数服务
     */
    public function __construct(
        protected SettingService $service
    ) {}

    /**
     * 获取系统参数。
     */
    #[GetMapping(path: 'info')]
    #[Auth(name: '系统参数', type: Auth::CHECK, menu: true, code: 'system.setting.index')]
    public function info(): array
    {
        $this->success('获取成功', $this->service->getInfo());
    }

    /**
     * 更新系统参数。
     */
    #[PutMapping(path: 'info')]
    #[Auth(name: '保存系统参数', type: Auth::CHECK, menu: false, code: 'system.setting.save')]
    #[Logger(name: '保存系统参数')]
    public function update(RequestInterface $request): array
    {
        $this->success('保存成功', $this->service->updateInfo($request->all()));
    }
}
