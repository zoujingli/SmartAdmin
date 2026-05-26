<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Model;

use Carbon\Carbon;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $session_id 上传会话编号
 * @property string $scene 上传场景(image图片,file文件,video视频或业务自定义)
 * @property string $driver 上传通道(local本地,oss阿里云OSS,qiniu七牛云,cos腾讯云COS,alist AList)
 * @property string $transport 上传方式(relay-single服务端单文件,relay-chunk服务端分片,direct-single客户端直传,direct-multipart客户端分片直传)
 * @property string $status 会话状态(initialized初始化,uploading上传中,aborted已中止)
 * @property string $origin_name 原始文件名
 * @property string $suffix 文件后缀
 * @property string $mime_type MIME 类型
 * @property int $size_byte 文件大小(字节)
 * @property string $hash 文件哈希
 * @property string $object_name 对象文件名
 * @property string $storage_path 对象路径
 * @property string $upload_id OSS Multipart Upload ID
 * @property int $part_size 分片大小(字节)
 * @property int $part_count 分片数量
 * @property string $parts 分片状态 JSON
 * @property string $complete_token 完成令牌
 * @property int $file_id 完成后的文件ID
 * @property string $expired_at 过期时间
 * @property string $completed_at 完成时间
 * @property string $aborted_at 中止时间
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
final class SystemFileUpload extends CoreModel
{
    protected array $fillable = ['id', 'tenant_id', 'session_id', 'scene', 'driver', 'transport', 'status', 'origin_name', 'suffix', 'mime_type', 'size_byte', 'hash', 'object_name', 'storage_path', 'upload_id', 'part_size', 'part_count', 'parts', 'complete_token', 'file_id', 'expired_at', 'completed_at', 'aborted_at', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'size_byte' => 'integer', 'part_size' => 'integer', 'part_count' => 'integer', 'file_id' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
