<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->createWebsiteSite();
        $this->createWebsiteChannel();
        $this->createWebsiteNav();
        $this->createWebsiteContent();
        $this->createWebsiteBlock();
        $this->createWebsiteLead();
    }

    public function down(): void
    {
        Schema::dropIfExists('website_lead');
        Schema::dropIfExists('website_block');
        Schema::dropIfExists('website_content');
        Schema::dropIfExists('website_nav');
        Schema::dropIfExists('website_channel');
        Schema::dropIfExists('website_site');
    }

    private function createWebsiteSite(): void
    {
        if (Schema::hasTable('website_site')) {
            return;
        }

        Schema::create('website_site', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('string', 'code', ['length' => 60])->nullable()->default('')->comment('站点编码');
            $table->addColumn('string', 'name', ['length' => 120])->nullable()->default('')->comment('站点名称');
            $table->addColumn('string', 'domain', ['length' => 120])->nullable()->default('')->comment('主域名');
            $table->addColumn('text', 'aliases')->nullable()->comment('备用域名 JSON');
            $table->addColumn('string', 'logo', ['length' => 500])->nullable()->default('')->comment('Logo 地址');
            $table->addColumn('string', 'favicon', ['length' => 500])->nullable()->default('')->comment('Favicon 地址');
            $table->addColumn('text', 'seo')->nullable()->comment('SEO 配置 JSON');
            $table->addColumn('text', 'contact')->nullable()->comment('联系方式 JSON');
            $table->addColumn('text', 'config')->nullable()->comment('站点扩展配置 JSON');
            $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->unique(['code'], 'uni_website_site_code');
            $table->unique(['domain'], 'uni_website_site_domain');
            $table->index(['tenant_id', 'status'], 'idx_website_site_tenant_status');
            $table->comment('官网站点表');
        });
    }

    private function createWebsiteChannel(): void
    {
        if (Schema::hasTable('website_channel')) {
            return;
        }

        Schema::create('website_channel', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'site_id')->nullable()->default(0)->comment('站点ID');
            $table->addColumn('bigInteger', 'parent_id')->nullable()->default(0)->comment('父级栏目ID');
            $table->addColumn('string', 'code', ['length' => 80])->nullable()->default('')->comment('栏目编码');
            $table->addColumn('string', 'name', ['length' => 120])->nullable()->default('')->comment('栏目名称');
            $table->addColumn('string', 'route', ['length' => 255])->nullable()->default('')->comment('栏目路由');
            $table->addColumn('string', 'type', ['length' => 30])->nullable()->default('page')->comment('栏目类型');
            $table->addColumn('text', 'seo')->nullable()->comment('SEO 配置 JSON');
            $table->addColumn('bigInteger', 'sort')->nullable()->default(0)->comment('排序');
            $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->unique(['site_id', 'code'], 'uni_website_channel_site_code');
            $table->unique(['site_id', 'route'], 'uni_website_channel_site_route');
            $table->index(['tenant_id', 'site_id', 'status'], 'idx_website_channel_tenant_site_status');
            $table->index(['parent_id'], 'idx_website_channel_parent');
            $table->index(['sort'], 'idx_website_channel_sort');
            $table->comment('官网栏目表');
        });
    }

    private function createWebsiteNav(): void
    {
        if (Schema::hasTable('website_nav')) {
            return;
        }

        Schema::create('website_nav', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'site_id')->nullable()->default(0)->comment('站点ID');
            $table->addColumn('bigInteger', 'parent_id')->nullable()->default(0)->comment('父级导航ID');
            $table->addColumn('string', 'position', ['length' => 30])->nullable()->default('top')->comment('导航位置(top,bottom等)');
            $table->addColumn('string', 'title', ['length' => 120])->nullable()->default('')->comment('导航标题');
            $table->addColumn('string', 'link_type', ['length' => 30])->nullable()->default('route')->comment('链接类型(route,url,channel,content)');
            $table->addColumn('string', 'route', ['length' => 255])->nullable()->default('')->comment('站内路由');
            $table->addColumn('string', 'url', ['length' => 500])->nullable()->default('')->comment('外部地址');
            $table->addColumn('bigInteger', 'channel_id')->nullable()->default(0)->comment('关联栏目ID');
            $table->addColumn('bigInteger', 'content_id')->nullable()->default(0)->comment('关联内容ID');
            $table->addColumn('string', 'target', ['length' => 20])->nullable()->default('self')->comment('打开方式(self,blank)');
            $table->addColumn('bigInteger', 'sort')->nullable()->default(0)->comment('排序');
            $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->index(['tenant_id', 'site_id', 'position', 'status'], 'idx_website_nav_site_position');
            $table->index(['parent_id'], 'idx_website_nav_parent');
            $table->index(['sort'], 'idx_website_nav_sort');
            $table->comment('官网导航表');
        });
    }

    private function createWebsiteContent(): void
    {
        if (Schema::hasTable('website_content')) {
            return;
        }

        Schema::create('website_content', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'site_id')->nullable()->default(0)->comment('站点ID');
            $table->addColumn('bigInteger', 'channel_id')->nullable()->default(0)->comment('栏目ID');
            $table->addColumn('string', 'type', ['length' => 30])->nullable()->default('article')->comment('内容类型');
            $table->addColumn('string', 'title', ['length' => 180])->nullable()->default('')->comment('标题');
            $table->addColumn('string', 'slug', ['length' => 160])->nullable()->default('')->comment('访问标识');
            $table->addColumn('string', 'route', ['length' => 255])->nullable()->default('')->comment('访问路由');
            $table->addColumn('string', 'summary', ['length' => 1000])->nullable()->default('')->comment('摘要');
            $table->addColumn('string', 'cover', ['length' => 500])->nullable()->default('')->comment('封面地址');
            $table->addColumn('text', 'content_html')->nullable()->comment('富文本正文');
            $table->addColumn('text', 'payload')->nullable()->comment('扩展数据 JSON');
            $table->addColumn('text', 'tags')->nullable()->comment('标签 JSON');
            $table->addColumn('text', 'seo')->nullable()->comment('SEO 配置 JSON');
            $table->addColumn('bigInteger', 'sort')->nullable()->default(0)->comment('排序');
            $table->addColumn('bigInteger', 'is_top')->nullable()->default(0)->comment('是否置顶');
            $table->addColumn('string', 'publish_status', ['length' => 30])->nullable()->default('draft')->comment('发布状态');
            $table->addColumn('timestamp', 'published_at')->nullable()->comment('发布时间');
            $table->addColumn('timestamp', 'offline_at')->nullable()->comment('下线时间');
            $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->index(['tenant_id', 'site_id', 'status'], 'idx_website_content_site_status');
            $table->index(['site_id', 'channel_id'], 'idx_website_content_channel');
            $table->index(['site_id', 'slug'], 'idx_website_content_slug');
            $table->index(['site_id', 'route'], 'idx_website_content_route');
            $table->index(['publish_status', 'published_at'], 'idx_website_content_publish');
            $table->index(['sort'], 'idx_website_content_sort');
            $table->comment('官网通用内容表');
        });
    }

    private function createWebsiteBlock(): void
    {
        if (Schema::hasTable('website_block')) {
            return;
        }

        Schema::create('website_block', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'site_id')->nullable()->default(0)->comment('站点ID');
            $table->addColumn('string', 'page_code', ['length' => 80])->nullable()->default('')->comment('页面编码');
            $table->addColumn('string', 'group_code', ['length' => 80])->nullable()->default('main')->comment('分组编码');
            $table->addColumn('string', 'code', ['length' => 80])->nullable()->default('')->comment('区块编码');
            $table->addColumn('string', 'name', ['length' => 120])->nullable()->default('')->comment('区块名称');
            $table->addColumn('string', 'type', ['length' => 30])->nullable()->default('custom')->comment('区块类型');
            $table->addColumn('string', 'title', ['length' => 180])->nullable()->default('')->comment('区块标题');
            $table->addColumn('string', 'subtitle', ['length' => 500])->nullable()->default('')->comment('区块副标题');
            $table->addColumn('text', 'payload')->nullable()->comment('区块数据 JSON');
            $table->addColumn('text', 'media')->nullable()->comment('媒体数据 JSON');
            $table->addColumn('text', 'link')->nullable()->comment('链接数据 JSON');
            $table->addColumn('bigInteger', 'sort')->nullable()->default(0)->comment('排序');
            $table->addColumn('string', 'publish_status', ['length' => 30])->nullable()->default('draft')->comment('发布状态');
            $table->addColumn('timestamp', 'published_at')->nullable()->comment('发布时间');
            $table->addColumn('timestamp', 'offline_at')->nullable()->comment('下线时间');
            $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->index(['tenant_id', 'site_id', 'status'], 'idx_website_block_site_status');
            $table->index(['site_id', 'page_code', 'group_code'], 'idx_website_block_page_group');
            $table->index(['publish_status', 'published_at'], 'idx_website_block_publish');
            $table->index(['sort'], 'idx_website_block_sort');
            $table->comment('官网页面区块表');
        });
    }

    private function createWebsiteLead(): void
    {
        if (Schema::hasTable('website_lead')) {
            return;
        }

        Schema::create('website_lead', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'site_id')->nullable()->default(0)->comment('站点ID');
            $table->addColumn('string', 'name', ['length' => 60])->nullable()->default('')->comment('联系人');
            $table->addColumn('string', 'mobile', ['length' => 30])->nullable()->default('')->comment('手机号');
            $table->addColumn('string', 'email', ['length' => 120])->nullable()->default('')->comment('邮箱');
            $table->addColumn('string', 'company', ['length' => 120])->nullable()->default('')->comment('公司');
            $table->addColumn('string', 'subject', ['length' => 180])->nullable()->default('')->comment('咨询主题');
            $table->addColumn('string', 'content', ['length' => 2000])->nullable()->default('')->comment('咨询内容');
            $table->addColumn('string', 'source_url', ['length' => 500])->nullable()->default('')->comment('来源页面');
            $table->addColumn('string', 'ip', ['length' => 60])->nullable()->default('')->comment('访客IP');
            $table->addColumn('string', 'user_agent', ['length' => 500])->nullable()->default('')->comment('浏览器UA');
            $table->addColumn('string', 'status', ['length' => 30])->nullable()->default('pending')->comment('处理状态');
            $table->addColumn('timestamp', 'handled_at')->nullable()->comment('处理时间');
            $table->addColumn('bigInteger', 'handled_by')->nullable()->default(0)->comment('处理人');
            $table->addColumn('string', 'remark', ['length' => 1000])->nullable()->default('')->comment('处理备注');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->index(['tenant_id', 'site_id', 'status'], 'idx_website_lead_site_status');
            $table->index(['mobile'], 'idx_website_lead_mobile');
            $table->index(['email'], 'idx_website_lead_email');
            $table->index(['created_at'], 'idx_website_lead_created_at');
            $table->comment('官网访客线索表');
        });
    }
};
