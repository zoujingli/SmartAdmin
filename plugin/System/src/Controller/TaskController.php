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

#[Controller(prefix: 'system/task')]
final class TaskController extends CoreController
{
    public function __construct(
        protected TaskExtend $task
    ) {}

    /**
     * 读取后台异步任务短时状态；只允许读取当前租户投递的任务，未知或越权任务统一返回 unknown。
     */
    #[GetMapping(path: 'status')]
    #[Auth(name: '异步任务状态', type: Auth::LOGIN, menu: false, code: 'system.task.status')]
    public function status(RequestInterface $request): array
    {
        $this->success('获取成功', $this->task->status(
            trim((string)$request->input('task_id', '')),
            (int)$request->input('limit', 50)
        ));
    }
}
