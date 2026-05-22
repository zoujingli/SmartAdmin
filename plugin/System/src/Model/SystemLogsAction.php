<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $username 操作用户名
 * @property string $method HTTP请求方法(GET,POST,PUT,DELETE等)
 * @property string $router 请求路由
 * @property string $name 操作名称
 * @property string $remark 操作摘要
 * @property string $ip 请求IP
 * @property string $ip_location IP归属地
 * @property string $os 客户端操作系统
 * @property string $browser 客户端浏览器
 * @property string $request_data 脱敏后的请求内容(JSON)
 * @property string $response_code 业务响应码(200成功,401未认证,403无权限,404路由不存在,500业务失败)
 * @property string $response_data 脱敏后的响应内容(JSON)
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 */
final class SystemLogsAction extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'system_logs_action';

    /**
     * 操作日志只保存接口行为主体；具体业务字段变化写入 system_logs_change，通过 action_id 关联。
     */
    protected array $fillable = ['id', 'tenant_id', 'username', 'method', 'router', 'name', 'remark', 'ip', 'ip_location', 'os', 'browser', 'request_data', 'response_code', 'response_data', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];
}
