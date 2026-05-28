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
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('website_site')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Db::table('website_site')->updateOrInsert(['code' => 'dmsai'], [
            'tenant_id' => 0,
            'name' => '德玛仕智慧厨房',
            'domain' => 'dmsai.cn',
            'aliases' => json_encode(['www.dmsai.cn'], JSON_UNESCAPED_UNICODE),
            'logo' => '',
            'favicon' => '',
            'seo' => json_encode(['title' => '德玛仕智慧厨房', 'keywords' => '智慧厨房,单位食堂,食安管理,设备物联', 'description' => '德玛仕整体智慧厨房一站式解决方案服务商'], JSON_UNESCAPED_UNICODE),
            'contact' => json_encode(['phone' => '400-863-7077', 'sales' => '1803-8852-487', 'address' => '广东省佛山市顺德区科技创新中心1座21层'], JSON_UNESCAPED_UNICODE),
            'config' => json_encode(['source' => 'dmsai.cn static baseline'], JSON_UNESCAPED_UNICODE),
            'status' => 1,
            'updated_at' => $now,
        ]);
        $siteId = (int)Db::table('website_site')->where('code', 'dmsai')->value('id');
        if ($siteId <= 0) {
            return;
        }

        $channels = [
            ['home', 0, '首页', '/', 'page', 100],
            ['restaurant', 0, '前厅售卖', '/restaurant/', 'page', 90],
            ['safe', 0, '智慧食安', '/safe/', 'page', 80],
            ['stock', 0, '智慧仓储', '/stock/', 'page', 70],
            ['devices', 0, '设备物联', '/devices/', 'page', 60],
            ['products', 0, '产品中心', '/products/', 'list', 50],
            ['solutions', 0, '解决方案', '/solutions/', 'list', 40],
            ['cases', 0, '客户案例', '/cases/', 'list', 30],
            ['about', 0, '走进德玛仕', '/about/', 'page', 20],
            ['news', 0, '新闻动态', '/news/', 'list', 10],
            ['restaurant-self', 'restaurant', '称重式自助打餐解决方案', '/restaurant/self/', 'page', 40],
            ['restaurant-dish', 'restaurant', '小碗菜打餐解决方案', '/restaurant/dish/', 'page', 30],
            ['restaurant-smart', 'restaurant', '智慧收银解决方案', '/restaurant/smart/', 'page', 20],
            ['restaurant-interactive', 'restaurant', '智慧交互类产品', '/restaurant/interactive/', 'page', 10],
            ['products-all', 'products', '全部产品', '/products/all/', 'list', 50],
            ['solutions-army', 'solutions', '部队食堂解决方案', '/solutions/army/', 'page', 20],
            ['solutions-unit', 'solutions', '单位食堂解决方案', '/solutions/unit/', 'page', 10],
            ['cases-army', 'cases', '部队', '/cases/army/', 'list', 40],
            ['cases-gov', 'cases', '政府单位', '/cases/gov/', 'list', 30],
            ['cases-company', 'cases', '集团企业', '/cases/company/', 'list', 20],
            ['cases-school', 'cases', '学校', '/cases/school/', 'list', 10],
            ['about-brand', 'about', '品牌实力', '/about/brand/', 'page', 20],
        ];
        $channelIds = [];
        foreach ($channels as [$code, $parentCode, $name, $route, $type, $sort]) {
            $parentId = is_string($parentCode) ? (int)($channelIds[$parentCode] ?? 0) : 0;
            Db::table('website_channel')->updateOrInsert(['site_id' => $siteId, 'code' => $code], [
                'tenant_id' => 0,
                'parent_id' => $parentId,
                'name' => $name,
                'route' => $route,
                'type' => $type,
                'seo' => json_encode(['title' => $name], JSON_UNESCAPED_UNICODE),
                'sort' => $sort,
                'status' => 1,
                'updated_at' => $now,
            ]);
            $channelIds[$code] = (int)Db::table('website_channel')->where('site_id', $siteId)->where('code', $code)->value('id');
        }

        $topNavs = [
            ['首页', '/', 100],
            ['前厅售卖', '/restaurant/self/', 90],
            ['智慧食安', '/safe/', 80],
            ['智慧仓储', '/stock/', 70],
            ['设备物联', '/devices/', 60],
            ['客户案例', '/cases/army/', 50],
            ['行业解决方案', '/solutions/army/', 40],
            ['产品中心', '/products/all/', 30],
        ];
        foreach ($topNavs as [$title, $route, $sort]) {
            Db::table('website_nav')->updateOrInsert(['site_id' => $siteId, 'position' => 'top', 'title' => $title], [
                'tenant_id' => 0,
                'parent_id' => 0,
                'link_type' => 'route',
                'route' => $route,
                'url' => '',
                'channel_id' => (int)($this->channelIdByRoute($channelIds, $channels, $route) ?? 0),
                'content_id' => 0,
                'target' => 'self',
                'sort' => $sort,
                'status' => 1,
                'updated_at' => $now,
            ]);
        }
        $bottomNavs = [
            ['走进德玛仕', '/about/', 50],
            ['品牌实力', '/about/brand/', 40],
            ['新闻动态', '/news/', 30],
            ['德玛仕设备官网', 'https://www.demashi.net.cn', 20],
        ];
        foreach ($bottomNavs as [$title, $target, $sort]) {
            $isUrl = str_starts_with($target, 'http');
            Db::table('website_nav')->updateOrInsert(['site_id' => $siteId, 'position' => 'bottom', 'title' => $title], [
                'tenant_id' => 0,
                'parent_id' => 0,
                'link_type' => $isUrl ? 'url' : 'route',
                'route' => $isUrl ? '' : $target,
                'url' => $isUrl ? $target : '',
                'channel_id' => $isUrl ? 0 : (int)($this->channelIdByRoute($channelIds, $channels, $target) ?? 0),
                'content_id' => 0,
                'target' => $isUrl ? 'blank' : 'self',
                'sort' => $sort,
                'status' => 1,
                'updated_at' => $now,
            ]);
        }

        $blocks = [
            ['home', 'hero', 'main-hero', '首页首屏', 'hero', '德玛仕智慧厨房', '整体智慧厨房一站式解决方案服务商', ['items' => [['title' => '智慧食安'], ['title' => '智慧仓储'], ['title' => '设备物联']]], 100],
            ['home', 'solution', 'scene-solution', '场景方案', 'section', '场景方案', '覆盖单位食堂、团餐、部队、学校等智慧厨房场景。', ['routes' => ['/solutions/unit/', '/solutions/army/']], 90],
            ['home', 'product', 'product-center', '产品中心', 'section', '产品中心', '覆盖前厅售卖、智慧食安、智慧仓储、设备物联等产品能力。', ['routes' => ['/products/all/']], 80],
        ];
        foreach ($blocks as [$pageCode, $groupCode, $code, $name, $type, $title, $subtitle, $payload, $sort]) {
            Db::table('website_block')->updateOrInsert(['site_id' => $siteId, 'page_code' => $pageCode, 'code' => $code], [
                'tenant_id' => 0,
                'group_code' => $groupCode,
                'name' => $name,
                'type' => $type,
                'title' => $title,
                'subtitle' => $subtitle,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'media' => '{}',
                'link' => '{}',
                'sort' => $sort,
                'publish_status' => 'published',
                'published_at' => $now,
                'offline_at' => null,
                'status' => 1,
                'updated_at' => $now,
            ]);
        }

        if (($newsChannelId = (int)($channelIds['news'] ?? 0)) > 0) {
            Db::table('website_content')->updateOrInsert(['site_id' => $siteId, 'slug' => 'website-module'], [
                'tenant_id' => 0,
                'channel_id' => $newsChannelId,
                'type' => 'news',
                'title' => '官网管理模块初始化',
                'route' => '/news/website-module/',
                'summary' => '官网内容已接入通用后台数据模块，前端可通过公开接口读取站点、栏目、导航、区块和内容。',
                'cover' => '',
                'content_html' => '<p>官网管理模块提供多站点、栏目、导航、内容、区块和线索数据接口。</p>',
                'payload' => '{}',
                'tags' => json_encode(['官网管理'], JSON_UNESCAPED_UNICODE),
                'seo' => json_encode(['title' => '官网管理模块初始化'], JSON_UNESCAPED_UNICODE),
                'sort' => 0,
                'is_top' => 0,
                'publish_status' => 'published',
                'published_at' => $now,
                'offline_at' => null,
                'status' => 1,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 初始化内容属于业务运行数据，回滚迁移不删除，避免覆盖运营后在后台调整过的官网资料。
    }

    /**
     * @param array<string, int> $channelIds
     * @param array<int, array{0:string,1:int|string,2:string,3:string,4:string,5:int}> $channels
     */
    private function channelIdByRoute(array $channelIds, array $channels, string $route): ?int
    {
        foreach ($channels as [$code, , , $channelRoute]) {
            if ($channelRoute === $route) {
                return $channelIds[$code] ?? null;
            }
        }

        return null;
    }
};
