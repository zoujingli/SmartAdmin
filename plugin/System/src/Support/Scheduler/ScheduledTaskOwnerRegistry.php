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
use Library\Exception\ErrorResponseException;
use Psr\Container\ContainerInterface;
use System\Annotation\ScheduledTaskOwner;
use System\Contract\ScheduledTaskOwnerResolverInterface;

/**
 * 收集业务插件声明的定时任务 owner 解析器。
 */
final class ScheduledTaskOwnerRegistry
{
    /**
     * @var null|array<string, class-string<ScheduledTaskOwnerResolverInterface>>
     */
    private ?array $resolverClasses = null;

    /**
     * @var null|array<string, ScheduledTaskOwnerType>
     */
    private ?array $types = null;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function types(string $ownerPlugin): array
    {
        $items = [];
        foreach ($this->allTypes() as $type) {
            if ($type->ownerPlugin === $ownerPlugin) {
                $items[] = $type->toArray();
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{items:list<array<string,mixed>>,pageInfo:array<string,int>}
     */
    public function options(string $ownerPlugin, string $ownerType, array $params = []): array
    {
        return $this->resolver($ownerPlugin, $ownerType)->options($params);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function resolve(string $ownerPlugin, string $ownerType, int $ownerId, array $payload = []): ScheduledTaskOwnerOption
    {
        return $this->resolver($ownerPlugin, $ownerType)->resolve($ownerId, $payload);
    }

    public function has(string $ownerPlugin, string $ownerType): bool
    {
        $this->discover();

        return isset($this->resolverClasses[$this->key($ownerPlugin, $ownerType)]);
    }

    private function resolver(string $ownerPlugin, string $ownerType): ScheduledTaskOwnerResolverInterface
    {
        $this->discover();
        $key = $this->key($ownerPlugin, $ownerType);
        $class = $this->resolverClasses[$key] ?? null;
        if ($class === null) {
            throw new ErrorResponseException('任务归属解析器不存在，不能在 System 中管理该业务任务');
        }

        $resolver = $this->container->get($class);
        if (!$resolver instanceof ScheduledTaskOwnerResolverInterface) {
            throw new ErrorResponseException('任务归属解析器无效');
        }

        return $resolver;
    }

    /**
     * @return array<string, ScheduledTaskOwnerType>
     */
    private function allTypes(): array
    {
        $this->discover();

        return $this->types ?? [];
    }

    private function discover(): void
    {
        if ($this->resolverClasses !== null && $this->types !== null) {
            return;
        }

        $classes = [];
        $types = [];
        foreach (AnnotationCollector::getClassesByAnnotation(ScheduledTaskOwner::class) as $class => $annotation) {
            if (!$annotation instanceof ScheduledTaskOwner || !is_a($class, ScheduledTaskOwnerResolverInterface::class, true)) {
                continue;
            }

            $key = $this->key($annotation->ownerPlugin, $annotation->ownerType);
            $classes[$key] = $class;
            $types[$key] = new ScheduledTaskOwnerType(
                $annotation->ownerPlugin,
                $annotation->ownerType,
                $annotation->name,
                $annotation->description,
            );
        }
        ksort($classes);
        ksort($types);

        $this->resolverClasses = $classes;
        $this->types = $types;
    }

    private function key(string $ownerPlugin, string $ownerType): string
    {
        return $ownerPlugin . ':' . $ownerType;
    }
}
