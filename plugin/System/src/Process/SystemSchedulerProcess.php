<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Process;

use Hyperf\Contract\ProcessInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Server as CoHttpServer;
use Swoole\Coroutine\Server as CoServer;
use Swoole\Process;
use Swoole\Server;
use System\Annotation\SystemProcess;
use System\Contract\ScheduledTaskExecutorInterface;
use System\Mapper\ScheduledTaskMapper;
use System\Model\SystemScheduledTask;

#[SystemProcess(name: 'system.scheduler', default: true)]
final class SystemSchedulerProcess implements ProcessInterface
{
    private bool $running = true;

    public function __construct(
        private readonly ScheduledTaskMapper $tasks,
        private readonly ScheduledTaskExecutorInterface $executor,
        private readonly LoggerInterface $logger,
    ) {}

    public function bind($server): void
    {
        if (!$server instanceof Server) {
            return;
        }

        $server->addProcess(new Process(function (): void {
            $this->handle();
        }, false, 2, true));
    }

    public function isEnable($server): bool
    {
        // System 调度进程默认启动；具体任务是否执行由后台任务状态和计划配置控制。
        return $server instanceof Server || $server instanceof CoServer || $server instanceof CoHttpServer;
    }

    public function handle(): void
    {
        if (function_exists('swoole_set_process_name')) {
            @swoole_set_process_name('smartadmin system.scheduler');
        }

        while ($this->running) {
            try {
                $this->tick();
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf('System scheduler tick failed: %s', $exception->getMessage()));
            }
            Coroutine::sleep(30);
        }
    }

    public function tick(): int
    {
        $count = 0;
        foreach ($this->tasks->dueTasks() as $task) {
            if (!$task instanceof SystemScheduledTask) {
                continue;
            }

            $lockedUntil = date('Y-m-d H:i:s', time() + max(60, (int)$task->timeout));
            $claimed = $this->tasks->claim((int)$task->id, $lockedUntil);
            if (!$claimed instanceof SystemScheduledTask) {
                continue;
            }

            ++$count;
            $this->executor->execute($claimed);
        }

        return $count;
    }
}
