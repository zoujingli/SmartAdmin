<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Service\LogsActionService;

/**
 * @internal
 */
#[CoversClass(LogsActionService::class)]
final class LogsActionServiceTest extends TestCase
{
    public function testFillIpLocationUsesIp2RegionWhenMissing(): void
    {
        $data = $this->fillIpLocation(['ip' => '10.0.0.8']);

        $this->assertSame('内网', $data['ip_location'] ?? null);
    }

    public function testFillIpLocationKeepsExplicitLocation(): void
    {
        $data = $this->fillIpLocation([
            'ip' => '10.0.0.8',
            'ip_location' => '自定义地区',
        ]);

        $this->assertSame('自定义地区', $data['ip_location'] ?? null);
    }

    public function testFillIpLocationSkipsEmptyIp(): void
    {
        $data = $this->fillIpLocation(['ip' => '']);

        $this->assertArrayNotHasKey('ip_location', $data);
    }

    public function testBuildChangeRowMapsSegmentToChangeLogTableFields(): void
    {
        $row = $this->buildChangeRow(10, [
            'tenant_id' => 3,
            'username' => 'admin',
            'created_by' => 1,
            'updated_by' => 1,
        ], [
            'model' => 'SystemTenant',
            'table' => 'system_tenant',
            'model_name' => '租户',
            'record_id' => 1,
            'record_label' => '测试租户',
            'event' => 'updated',
            'text' => '租户名称(name)旧名称改为新名称',
            'fields' => [[
                'field' => 'name',
                'label' => '租户名称',
                'old' => '旧名称',
                'new' => '新名称',
                'old_text' => '旧名称',
                'new_text' => '新名称',
            ]],
        ]);

        $this->assertSame(3, $row['tenant_id']);
        $this->assertSame(10, $row['action_id']);
        $this->assertSame('admin', $row['username']);
        $this->assertSame('SystemTenant', $row['model']);
        $this->assertSame('system_tenant', $row['table_name']);
        $this->assertSame('租户', $row['model_name']);
        $this->assertSame('1', $row['record_id']);
        $this->assertSame('测试租户', $row['record_label']);
        $this->assertSame('updated', $row['event']);
        $this->assertSame('租户(测试租户)：租户名称(name)旧名称改为新名称', $row['change_remark']);
        $this->assertStringContainsString('"field":"name"', $row['change_values']);
    }

    public function testBuildChangeRowSkipsSegmentWithoutFields(): void
    {
        $this->assertNull($this->buildChangeRow(10, [], ['model' => 'SystemTenant', 'fields' => []]));
    }

    public function testNormalizeCountMapKeepsStableNumericMap(): void
    {
        $this->assertSame([
            '200' => 12,
            '未记录' => 3,
            'admin' => 4,
        ], $this->normalizeCountMap([
            200 => '12',
            '' => '3',
            'admin' => 4.7,
        ]));
    }

    public function testFillNumberRangeBackfillsMissingBuckets(): void
    {
        $this->assertSame([
            '0' => 2,
            '1' => 0,
            '2' => 5,
        ], $this->fillNumberRange([
            '0' => '2',
            2 => 5,
        ], 0, 2));
    }

    /**
     * 直接反射归一化逻辑，避免测试为了覆盖日志补齐规则而真实写数据库。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fillIpLocation(array $data): array
    {
        $method = new \ReflectionMethod(LogsActionService::class, 'fillIpLocation');
        $method->setAccessible(true);

        return $method->invoke(null, $data);
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $segment
     * @return null|array<string, mixed>
     */
    private function buildChangeRow(int $actionId, array $action, array $segment): ?array
    {
        $method = new \ReflectionMethod(LogsActionService::class, 'buildChangeRow');
        $method->setAccessible(true);

        return $method->invoke(null, $actionId, $action, $segment);
    }

    /**
     * @param array<int|string, mixed> $counts
     * @return array<string, int>
     */
    private function normalizeCountMap(array $counts): array
    {
        $method = new \ReflectionMethod(LogsActionService::class, 'normalizeCountMap');
        $method->setAccessible(true);

        return $method->invoke(null, $counts);
    }

    /**
     * @param array<int|string, mixed> $counts
     * @return array<string, int>
     */
    private function fillNumberRange(array $counts, int $start, int $end): array
    {
        $method = new \ReflectionMethod(LogsActionService::class, 'fillNumberRange');
        $method->setAccessible(true);

        return $method->invoke(null, $counts, $start, $end);
    }
}
