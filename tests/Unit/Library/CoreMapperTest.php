<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace Tests\Unit\Library;

use Hyperf\Database\Model\Model;
use Library\CoreMapper;
use Library\CoreModel;
use Library\Support\ModelChangeLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CoreMapper::class)]
#[CoversClass(ModelChangeLog::class)]
final class CoreMapperTest extends TestCase
{
    protected function tearDown(): void
    {
        ModelChangeLog::clear();
    }

    public function testFilterQueryAttrsOnlyAllowsModelFieldsAndExcludesHiddenFields(): void
    {
        $mapper = new CoreMapperFixture();

        $this->assertSame(
            ['id', 'name', 'test_mapper_model.sort'],
            $mapper->filter(['id', 'name', 'password', 'count(*) as total', 'system_user.id', 'test_mapper_model.sort'])
        );
    }

    public function testFilterQueryAttrsAllowsExplicitRegisteredAliases(): void
    {
        $mapper = new AliasCoreMapperFixture();

        $this->assertSame(
            ['id', 'COUNT(*) as total_count'],
            $mapper->filter(['id', 'COUNT(*) as total_count', 'SUM(amount) as amount'])
        );
    }

    public function testFilterModelDataCanRemovePrimaryKeyForCreatePayload(): void
    {
        $mapper = new CoreMapperFixture();

        // 标准写入只接受业务字段，主键、审计字段和时间戳必须交给框架统一维护。
        $this->assertSame(
            ['name' => 'demo', 'sort' => 10, 'password' => 'hidden'],
            $mapper->filterModelPayload([
                'id' => 99,
                'name' => 'demo',
                'sort' => 10,
                'password' => 'hidden',
                'created_by' => 1,
                'updated_by' => 2,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'deleted_at' => '2026-01-01 00:00:00',
                'missing' => 'ignored',
            ], true)
        );
    }

    public function testUpdateLeavesChangeLoggingToModelEventListener(): void
    {
        $model = new AuditedTestMapperModel();
        $model->setRawAttributes([
            'id' => 1,
            'name' => '旧租户',
            'status' => 1,
        ], true);

        $mapper = new AuditedCoreMapperFixture($model);

        $this->assertTrue($mapper->update(1, [
            'id' => 99,
            'name' => '新租户',
            'updated_by' => 2,
        ]));

        // CoreMapper 只负责执行模型写入；变更日志由 ModelChangeLogListener 或业务显式记录，避免基础 Mapper 耦合审计链路。
        $this->assertNull(ModelChangeLog::pull());
    }
}

class CoreMapperFixture extends CoreMapper
{
    protected string $model = TestMapperModel::class;

    public function filter(array $fields): array
    {
        return $this->filterQueryAttrs($fields);
    }

    public function filterModelPayload(array $data, bool $removePk = false): array
    {
        return $this->filterModelData($data, $removePk);
    }
}

final class AliasCoreMapperFixture extends CoreMapperFixture
{
    protected function querySelectableAliases(): array
    {
        return ['COUNT(*) as total_count'];
    }
}

final class TestMapperModel extends CoreModel
{
    protected ?string $table = 'test_mapper_model';

    protected array $hidden = ['password'];

    protected array $fillable = ['id', 'name', 'password', 'sort', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];
}

final class AuditedCoreMapperFixture extends CoreMapper
{
    protected string $model = AuditedTestMapperModel::class;

    public function __construct(private AuditedTestMapperModel $storedModel) {}

    public function read(mixed $id, array $column = ['*'], bool $isScope = true): ?Model
    {
        return $this->storedModel;
    }
}

final class AuditedTestMapperModel extends CoreModel
{
    protected ?string $table = 'system_tenant';

    protected array $fillable = ['id', 'name', 'status', 'created_by', 'updated_by'];

    protected array $logRules = [
        'name' => '租户',
        'title' => 'name',
        'fields' => [
            'name' => '租户名称',
            'status' => ['name' => '状态', 'values' => [0 => '禁用', 1 => '启用']],
        ],
    ];

    public function update(array $attributes = [], array $options = [])
    {
        foreach ($attributes as $field => $value) {
            $this->setAttribute((string)$field, $value);
        }

        return true;
    }
}
