<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Scheduler;

use Hyperf\Di\Annotation\AnnotationCollector;
use Psr\Container\ContainerInterface;
use System\Annotation\ScheduledTask;
use System\Contract\ScheduledTaskHandlerInterface;

/**
 * 收集可被后台选择的定时任务白名单。
 */
final class ScheduledTaskRegistry
{
    /**
     * @var null|array<string, ScheduledTaskDefinition>
     */
    private ?array $definitions = null;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @return array<string, ScheduledTaskDefinition>
     */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $items = [];
        foreach (AnnotationCollector::getClassesByAnnotation(ScheduledTask::class) as $class => $annotation) {
            if (!$annotation instanceof ScheduledTask || !is_a($class, ScheduledTaskHandlerInterface::class, true)) {
                continue;
            }
            $definition = ScheduledTaskDefinition::fromAnnotation($class, $annotation);
            $items[$definition->code] = $definition;
        }
        ksort($items);

        return $this->definitions = $items;
    }

    public function get(string $code): ?ScheduledTaskDefinition
    {
        return $this->all()[$code] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        return array_map(static fn (ScheduledTaskDefinition $definition): array => $definition->toArray(), array_values($this->all()));
    }

    public function handler(ScheduledTaskDefinition $definition): ScheduledTaskHandlerInterface
    {
        $handler = $this->container->get($definition->handler);
        if (!$handler instanceof ScheduledTaskHandlerInterface) {
            throw new \RuntimeException(sprintf('Scheduled task handler [%s] is invalid.', $definition->handler));
        }

        return $handler;
    }
}
