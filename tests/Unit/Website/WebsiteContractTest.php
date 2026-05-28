<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Website;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Library\Events\OperateLogRecorder;
use Library\Support\SensitiveDataFilter;
use Plugin\Website\Support\RichText;
use Plugin\Website\Support\Secret;
use Plugin\Website\Support\WebsiteData;
use Plugin\Website\Support\WebsiteLeadStatus;
use Plugin\Website\Support\WebsiteOpenApiScope;
use Plugin\Website\Support\WebsiteOpenApiSignature;
use Plugin\Website\Support\WebsitePublishStatus;

/**
 * @internal
 */
#[CoversNothing]
final class WebsiteContractTest extends TestCase
{
    public function testPluginManifestIsBackendOnlyWebsiteModule(): void
    {
        $root = dirname(__DIR__, 3);
        $manifest = json_decode((string)file_get_contents($root . '/plugin/Website/plugin.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('website', $manifest['plugin']['code'] ?? null);
        $this->assertSame('官网管理', $manifest['plugin']['name'] ?? null);
        $this->assertSame('stc/migrations', $manifest['plugin']['migration_root'] ?? null);
        $this->assertContains('website_', $manifest['plugin']['table_prefixes'] ?? []);
        // 后台数据管理需要插件页面；前台官网展示仍只走公开 API，不在本期实现独立官网前端。
        $this->assertSame('stc/view', $manifest['plugin']['view_root'] ?? null);
        $this->assertNotEmpty($manifest['apps'] ?? []);
        $this->assertSame('/system/website', $manifest['apps'][0]['route'] ?? null);
        $menuCodes = array_column($manifest['apps'][0]['menus'] ?? [], 'code');
        $this->assertContains('website.site.index', $menuCodes);
        $this->assertContains('website.channel.index', $menuCodes);
        $this->assertContains('website.nav.index', $menuCodes);
        $this->assertContains('website.app.index', $menuCodes);
        $this->assertContains('website.content.index', $menuCodes);
        $this->assertContains('website.block.index', $menuCodes);
        $this->assertContains('website.lead.index', $menuCodes);
    }

    public function testPublishAndLeadStatusEnumsAreStable(): void
    {
        $this->assertSame(['draft', 'scheduled', 'published', 'offline'], WebsitePublishStatus::all());
        $this->assertTrue(WebsitePublishStatus::isValid(WebsitePublishStatus::SCHEDULED));
        $this->assertSame('定时发布', WebsitePublishStatus::label(WebsitePublishStatus::SCHEDULED));

        $this->assertSame(['pending', 'processing', 'handled', 'invalid'], WebsiteLeadStatus::all());
        $this->assertTrue(WebsiteLeadStatus::isValid(WebsiteLeadStatus::HANDLED));
        $this->assertSame('已处理', WebsiteLeadStatus::label(WebsiteLeadStatus::HANDLED));
    }

    public function testWebsiteDataNormalizesRoutesAndJsonPayloads(): void
    {
        $this->assertSame('/', WebsiteData::route(''));
        $this->assertSame('/safe/', WebsiteData::route('safe'));
        $this->assertSame('/solutions/unit/', WebsiteData::route('/solutions/unit'));
        $this->assertSame(['dmsai.cn', 'www.dmsai.cn'], WebsiteData::stringList('dmsai.cn，www.dmsai.cn'));
        $this->assertSame(['title' => '德玛仕'], WebsiteData::object('{"title":"德玛仕"}'));
        $this->assertSame([], WebsiteData::object('["not-object"]'));
    }

    public function testRichTextRemovesDangerousHtmlForPublicRendering(): void
    {
        $clean = RichText::sanitize('<p>正文</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">链接</a><img src="https://example.com/a.png" onerror="x()">');

        $this->assertStringContainsString('<p>正文</p>', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('https://example.com/a.png', $clean);
    }

    public function testOpenApiSignatureContractIsStable(): void
    {
        $query = ['type' => 'news', 'channel' => 'news', 'page' => 1];
        $string = WebsiteOpenApiSignature::buildStringToSign('get', '/website/content/index', $query, '', '1716888888', 'nonce-123456');

        $this->assertSame("GET\n/website/content/index\nchannel=news&page=1&type=news\n" . hash('sha256', '') . "\n1716888888\nnonce-123456", $string);
        $this->assertSame(hash_hmac('sha256', $string, 'demo-key'), WebsiteOpenApiSignature::sign('demo-key', 'get', '/website/content/index', $query, '', '1716888888', 'nonce-123456'));
    }

    public function testOpenApiScopesAndSecretRulesAreStable(): void
    {
        $this->assertContains(WebsiteOpenApiScope::LEAD_CREATE, WebsiteOpenApiScope::all());
        $this->assertNotContains(WebsiteOpenApiScope::LEAD_CREATE, WebsiteOpenApiScope::defaultReadScopes());
        $this->assertTrue(WebsiteOpenApiScope::allows([WebsiteOpenApiScope::CONTENT_READ], WebsiteOpenApiScope::CONTENT_READ));
        $this->assertFalse(WebsiteOpenApiScope::allows(WebsiteOpenApiScope::defaultReadScopes(), WebsiteOpenApiScope::LEAD_CREATE));

        $encrypted = Secret::encrypt('demo-app-key');
        $this->assertNotSame('demo-app-key', $encrypted);
        $this->assertSame('demo-app-key', Secret::decrypt($encrypted));
        $this->assertSame('******', Secret::mask($encrypted));
        $this->assertTrue(Secret::isMask('******'));
    }

    public function testOpenApiSecretsAreMaskedInCommonLogs(): void
    {
        $filtered = SensitiveDataFilter::apply([
            'app_key' => 'plain-openapi-key',
            'nested' => ['signature' => 'plain-signature'],
        ]);

        $this->assertSame('***', $filtered['app_key']);
        $this->assertSame('***', $filtered['nested']['signature']);

        $response = OperateLogRecorder::formatResponseData([
            'code' => 200,
            'data' => ['app_key' => 'plain-openapi-key'],
        ]);

        $this->assertStringNotContainsString('plain-openapi-key', $response);
        $this->assertStringContainsString('"app_key":"***"', $response);
    }

    public function testPublicServiceSourceKeepsFailClosedSiteAndPublishBoundary(): void
    {
        $root = dirname(__DIR__, 3);
        $source = (string)file_get_contents($root . '/plugin/Website/src/Service/WebsitePublicService.php');
        $siteMapper = (string)file_get_contents($root . '/plugin/Website/src/Mapper/WebsiteSiteMapper.php');
        $contentMapper = (string)file_get_contents($root . '/plugin/Website/src/Mapper/WebsiteContentMapper.php');
        $blockMapper = (string)file_get_contents($root . '/plugin/Website/src/Mapper/WebsiteBlockMapper.php');

        // 公开接口必须先解析站点，后续查询显式绑定 tenant_id + site_id；解析失败不能退化成全量查询。
        $this->assertStringContainsString('resolveSite', $source);
        $this->assertStringContainsString('开放接口应用无权访问该站点', $source);
        $this->assertStringNotContainsString('findPublicSite', $siteMapper);
        $this->assertStringContainsString("where('tenant_id'", $contentMapper);
        $this->assertStringContainsString("where('site_id'", $contentMapper);
        $this->assertStringContainsString("where('tenant_id'", $blockMapper);
        $this->assertStringContainsString("where('site_id'", $blockMapper);
        $this->assertStringContainsString('WebsitePublishStatus::PUBLISHED', $contentMapper);
        $this->assertStringContainsString('WebsitePublishStatus::SCHEDULED', $contentMapper);
        $this->assertStringContainsString("where('published_at', '<='", $contentMapper);

        $manifestSource = (string)file_get_contents($root . '/plugin/Website/plugin.json');
        $appController = (string)file_get_contents($root . '/plugin/Website/src/Controller/SystemAppController.php');
        $publicController = (string)file_get_contents($root . '/plugin/Website/src/Controller/PublicWebsiteController.php');
        $authenticator = (string)file_get_contents($root . '/plugin/Website/src/Service/WebsiteOpenApiAuthenticator.php');
        $appMigration = (string)file_get_contents($root . '/plugin/Website/stc/migrations/2026_05_28_800003_website_app.php');

        $this->assertStringContainsString('website.app.index', $manifestSource);
        $this->assertStringContainsString("excludeFields: ['app_key']", $appController);
        $this->assertStringContainsString('WebsiteOpenApiAuthenticator', $publicController);
        $this->assertStringContainsString('WebsiteOpenApiScope::PAGE_READ', $publicController);
        $this->assertStringContainsString('WebsiteOpenApiScope::CONTENT_READ', $publicController);
        $this->assertStringContainsString('不能只给 page:read 就绕开细分读取权限', $publicController);
        $this->assertStringContainsString('X-Website-Appid', $authenticator);
        $this->assertStringContainsString('hash_equals', $authenticator);
        $this->assertStringContainsString('开放接口签名头缺失', $authenticator);
        $this->assertStringContainsString('开放接口随机串已使用', $authenticator);
        $this->assertStringContainsString('开放接口调用过于频繁', $authenticator);
        $this->assertStringContainsString('开放接口权限不足', $authenticator);
        $this->assertStringContainsString('当前 IP 不允许调用开放接口', $authenticator);
        $this->assertStringContainsString('website_app', $appMigration);
    }
}
