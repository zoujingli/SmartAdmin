<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library;

use Library\CoreModel;
use Library\Support\ModelChangeFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ModelChangeFormatter::class)]
final class ModelChangeFormatterTest extends TestCase
{
    public function testEnumValuesIncludeRawValueInReadableText(): void
    {
        $model = new ChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        $segment = ModelChangeFormatter::buildSegment($model, 'updated', ['status' => 1], ['status' => 0]);
        $payload = ModelChangeFormatter::buildPayload([$segment]);

        $this->assertSame('系统用户(zhangsan)：状态(status)禁用(0)改为启用(1)', $payload['summary']);
        $this->assertSame('禁用(0)', $payload['segments'][0]['fields'][0]['old_text']);
        $this->assertSame('启用(1)', $payload['segments'][0]['fields'][0]['new_text']);
    }

    public function testUnitValuesAppendUnitWithoutEnumMapping(): void
    {
        $model = new ChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        $segment = ModelChangeFormatter::buildSegment($model, 'updated', ['age' => 14], ['age' => 13]);
        $payload = ModelChangeFormatter::buildPayload([$segment]);

        $this->assertSame('系统用户(zhangsan)：年龄(age)13岁改为14岁', $payload['summary']);
    }

    public function testUnchangedIgnoredAndHiddenFieldsAreNotDisplayed(): void
    {
        $model = new ChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        $segment = ModelChangeFormatter::buildSegment($model, 'updated', [
            'username' => 'lisi',
            'age' => 13,
            'password' => 'secret',
            'created_at' => '2026-01-01 00:00:00',
        ], [
            'username' => 'zhangsan',
            'age' => 13,
            'password' => 'old-secret',
            'created_at' => '2025-01-01 00:00:00',
        ]);

        $this->assertSame('用户名(username)zhangsan改为lisi', $segment['text']);
        $this->assertCount(1, $segment['fields']);
    }

    public function testEmptyValueDisplaysAsBlankTextWithoutUnit(): void
    {
        $model = new ChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        $segment = ModelChangeFormatter::buildSegment($model, 'updated', ['age' => 14], ['age' => null]);

        $this->assertSame('年龄(age)空改为14岁', $segment['text']);
    }

    public function testStoredRawArrayIsLimitedInChangeData(): void
    {
        $model = new ChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        $segment = ModelChangeFormatter::buildManualSegment($model, 'updated', [[
            'field' => 'roles',
            'label' => '角色',
            'old' => [],
            'new' => range(1, 25),
        ]]);

        // 结构化 old/new 只用于详情辅助，超大数组必须截断，避免单条日志行过大。
        $this->assertSame(25, $segment['fields'][0]['new']['total']);
        $this->assertTrue($segment['fields'][0]['new']['truncated']);
        $this->assertCount(20, $segment['fields'][0]['new']['items']);
    }
}

final class ChangeLogModelFixture extends CoreModel
{
    protected ?string $table = 'change_log_model_fixture';

    protected array $hidden = ['password'];

    protected array $fillable = ['id', 'username', 'age', 'status', 'password', 'created_at'];

    protected array $logRules = [
        'name' => '系统用户',
        'title' => 'username',
        'fields' => [
            'username' => '用户名',
            'age' => ['name' => '年龄', 'unit' => '岁'],
            'status' => ['name' => '状态', 'values' => [0 => '禁用', 1 => '启用']],
            'password' => '密码',
        ],
    ];
}
