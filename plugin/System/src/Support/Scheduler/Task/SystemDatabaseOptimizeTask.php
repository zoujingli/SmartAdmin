<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Scheduler\Task;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use System\Annotation\ScheduledTask;
use System\Contract\ScheduledTaskHandlerInterface;
use System\Support\Scheduler\ScheduledTaskContext;

#[ScheduledTask(
    code: 'system.database.optimize',
    name: '优化数据库',
    description: '按当前数据库驱动执行受控的表优化或统计刷新。',
    group: 'system',
    timeout: 3600
)]
final class SystemDatabaseOptimizeTask implements ScheduledTaskHandlerInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    public function handle(ScheduledTaskContext $context): array
    {
        $driver = strtolower((string)$this->config->get('databases.default.driver', ''));
        $tables = $this->tables((array)($context->params['tables'] ?? []));
        if ($driver === 'sqlite') {
            Db::statement('VACUUM');
            Db::statement('ANALYZE');

            return ['driver' => $driver, 'optimized' => ['VACUUM', 'ANALYZE']];
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $optimized = [];
            foreach ($tables as $table) {
                Db::statement(sprintf('OPTIMIZE TABLE `%s`', str_replace('`', '``', $table)));
                $optimized[] = $table;
            }

            return ['driver' => $driver, 'optimized' => $optimized];
        }

        return ['driver' => $driver, 'optimized' => [], 'message' => '当前数据库驱动无需优化'];
    }

    /**
     * @param array<int|string, mixed> $requested
     * @return array<int, string>
     */
    private function tables(array $requested): array
    {
        $all = array_values(array_filter(array_map(
            static function (mixed $table): string {
                if (is_string($table)) {
                    return $table;
                }
                if (is_array($table)) {
                    return (string)($table['name'] ?? $table['TABLE_NAME'] ?? reset($table) ?: '');
                }
                if (is_object($table)) {
                    return (string)($table->name ?? $table->TABLE_NAME ?? '');
                }

                return '';
            },
            Schema::getAllTables()
        ), static fn (string $table): bool => $table !== ''));
        $allow = array_fill_keys($all, true);
        $requested = array_values(array_filter(array_map(static fn (mixed $table): string => trim((string)$table), $requested)));
        if ($requested === []) {
            return $all;
        }

        return array_values(array_filter($requested, static fn (string $table): bool => isset($allow[$table])));
    }
}
