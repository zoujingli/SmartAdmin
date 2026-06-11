<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Listener;

use Hyperf\Contract\ProcessInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Psr\Container\ContainerInterface;
use System\Annotation\SystemProcess;

/**
 * 在主服务启动前注册 System 默认进程。
 */
final class SystemProcessRegisterListener implements ListenerInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function listen(): array
    {
        return [BeforeMainServerStart::class];
    }

    public function process(object $event): void
    {
        if (!$event instanceof BeforeMainServerStart) {
            return;
        }

        foreach ($this->processClasses() as $class) {
            $process = $this->container->get($class);
            if ($process instanceof ProcessInterface && $process->isEnable($event->server)) {
                $process->bind($event->server);
            }
        }
    }

    /**
     * @return array<int, class-string>
     */
    public function processClasses(): array
    {
        $classes = [];
        foreach (AnnotationCollector::getClassesByAnnotation(SystemProcess::class) as $class => $annotation) {
            if ($annotation instanceof SystemProcess && $annotation->default && is_a($class, ProcessInterface::class, true)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
