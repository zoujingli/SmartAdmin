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

use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Database\Model\Events\ForceDeleted;
use Hyperf\Database\Model\Events\Restored;
use Library\CoreModel;
use Library\Events\Listener\ModelChangeLogListener;
use Library\Support\ModelChangeLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ModelChangeLogListener::class)]
final class ModelChangeLogListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        ModelChangeLog::clear();
    }

    public function testDeletedEventCreatesReadableActionSegment(): void
    {
        $model = new ListenerChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        (new ModelChangeLogListener())->process(new Deleted($model));
        $payload = ModelChangeLog::pull();

        $this->assertSame('系统用户(zhangsan)：操作(action)正常改为已删除', $payload['summary']);
    }

    public function testRestoredEventCreatesReadableActionSegment(): void
    {
        $model = new ListenerChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        (new ModelChangeLogListener())->process(new Restored($model));
        $payload = ModelChangeLog::pull();

        $this->assertSame('系统用户(zhangsan)：操作(action)已删除改为正常', $payload['summary']);
    }

    public function testForceDeletedEventCreatesReadableActionSegment(): void
    {
        $model = new ListenerChangeLogModelFixture();
        $model->id = 1;
        $model->username = 'zhangsan';

        (new ModelChangeLogListener())->process(new ForceDeleted($model));
        $payload = ModelChangeLog::pull();

        $this->assertSame('系统用户(zhangsan)：操作(action)正常改为彻底删除', $payload['summary']);
    }
}

final class ListenerChangeLogModelFixture extends CoreModel
{
    protected ?string $table = 'listener_change_log_model_fixture';

    protected array $fillable = ['id', 'username'];

    protected array $logRules = [
        'name' => '系统用户',
        'title' => 'username',
        'fields' => [
            'username' => '用户名',
        ],
    ];
}
