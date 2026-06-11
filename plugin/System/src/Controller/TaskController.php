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
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Helper\TaskExtend;
use Library\Interfaces\UserModelInterface;

#[Controller(prefix: 'system/task')]
final class TaskController extends CoreController
{
    public function __construct(
        protected TaskExtend $task
    ) {}

    /**
     * 读取后台异步任务短时状态；接口同时服务 System 后台和插件独立账号，任务可见性仍由租户元数据 fail closed。
     */
    #[GetMapping(path: 'status')]
    #[Auth(name: '异步任务状态', type: Auth::LOGIN, menu: false, userModel: UserModelInterface::class, code: 'system.task.status')]
    public function status(RequestInterface $request): array
    {
        $this->success('获取成功', $this->task->status(
            trim((string)$request->input('task_id', '')),
            (int)$request->input('limit', 50)
        ));
    }
}
