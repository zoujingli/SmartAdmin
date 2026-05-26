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
use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $scene 上传场景(image图片,file文件,video视频或业务自定义)
 * @property string $driver 上传通道(local本地,oss阿里云OSS,qiniu七牛云,cos腾讯云COS,alist AList)
 * @property string $url 文件地址
 * @property string $hash 文件哈希
 * @property string $suffix 文件后缀
 * @property int $storage_mode 存储方式(1本地,2阿里云OSS,3七牛云,4腾讯云COS,5Alist)
 * @property string $origin_name 原始文件名
 * @property string $object_name 存储文件名
 * @property string $storage_path 存储路径
 * @property string $mime_type MIME类型
 * @property int $size_byte 文件大小(字节)
 * @property string $size_info 文件大小描述
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 */
final class SystemFile extends CoreModel
{
    use SoftDeletes;

    public const STORAGE_MODE_LOCAL = 1;

    public const STORAGE_MODE_OSS = 2;

    public const STORAGE_MODE_QINIU = 3;

    public const STORAGE_MODE_COS = 4;

    public const STORAGE_MODE_ALIST = 5;

    protected array $fillable = ['id', 'tenant_id', 'scene', 'driver', 'url', 'hash', 'suffix', 'storage_mode', 'origin_name', 'object_name', 'storage_path', 'mime_type', 'size_byte', 'size_info', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'storage_mode' => 'integer', 'size_byte' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '系统文件',
        'title' => 'origin_name',
        'fields' => [
            'scene' => '业务场景',
            'driver' => '存储驱动',
            'url' => '文件地址',
            'hash' => '文件哈希',
            'suffix' => '文件后缀',
            'storage_mode' => [
                'name' => '存储方式',
                'values' => [
                    self::STORAGE_MODE_LOCAL => '本地',
                    self::STORAGE_MODE_OSS => '阿里云OSS',
                    self::STORAGE_MODE_QINIU => '七牛云',
                    self::STORAGE_MODE_COS => '腾讯云COS',
                    self::STORAGE_MODE_ALIST => 'Alist',
                ],
            ],
            'origin_name' => '原始文件名',
            'object_name' => '存储文件名',
            'storage_path' => '存储路径',
            'mime_type' => 'MIME类型',
            'size_byte' => ['name' => '文件大小', 'unit' => '字节'],
            'size_info' => '文件大小描述',
            'remark' => '备注',
        ],
    ];
}
