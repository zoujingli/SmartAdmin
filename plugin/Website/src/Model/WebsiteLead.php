<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;
use Plugin\Website\Support\WebsiteLeadStatus;

/**
 * 官网访客线索模型。
 */
final class WebsiteLead extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'website_lead';

    protected array $hidden = ['deleted_at'];

    protected array $fillable = ['id', 'tenant_id', 'site_id', 'name', 'mobile', 'email', 'company', 'subject', 'content', 'source_url', 'ip', 'user_agent', 'status', 'handled_at', 'handled_by', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'site_id' => 'integer', 'handled_at' => 'datetime', 'handled_by' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '官网访客线索',
        'title' => 'subject',
        'fields' => [
            'site_id' => '所属站点',
            'name' => '联系人',
            'mobile' => '手机号',
            'email' => '邮箱',
            'company' => '公司',
            'subject' => '咨询主题',
            'content' => '咨询内容',
            'status' => ['name' => '处理状态', 'values' => [
                WebsiteLeadStatus::PENDING => '待处理',
                WebsiteLeadStatus::PROCESSING => '处理中',
                WebsiteLeadStatus::HANDLED => '已处理',
                WebsiteLeadStatus::INVALID => '无效线索',
            ]],
            'handled_at' => '处理时间',
            'handled_by' => '处理人',
            'remark' => '处理备注',
        ],
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'site_id', 'id');
    }
}
