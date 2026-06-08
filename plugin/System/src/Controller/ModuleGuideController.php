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
use Library\CoreController;
use System\Service\DataService;

#[Controller(prefix: 'system/module-guide')]
final class ModuleGuideController extends CoreController
{
    public function __construct(
        protected DataService $service
    ) {}

    /**
     * 获取未登录首页模块引导配置。
     */
    #[GetMapping(path: 'index')]
    public function index(): array
    {
        // 模块引导页是公开入口，只返回插件清单中的展示字段；是否进入插件后台仍由各插件登录态自行处理。
        $this->success('获取成功', $this->service->getModuleGuide());
    }
}
