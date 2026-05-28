<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Service;

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\Exception\ErrorResponseException;
use Library\Exception\NotAllowResponseException;
use Plugin\Website\Mapper\WebsiteBlockMapper;
use Plugin\Website\Mapper\WebsiteChannelMapper;
use Plugin\Website\Mapper\WebsiteContentMapper;
use Plugin\Website\Mapper\WebsiteNavMapper;
use Plugin\Website\Model\WebsiteChannel;
use Plugin\Website\Model\WebsiteContent;
use Plugin\Website\Model\WebsiteSite;
use Plugin\Website\Support\WebsiteData;
use Plugin\Website\Support\WebsiteOpenApiContext;

/**
 * 官网公开读取服务。
 *
 * 公开接口必须先解析到单个启用站点，再以 site_id + tenant_id 作为全部后续查询边界；
 * 任何无法解析站点的请求都直接失败，不能退化为全量内容列表。
 */
final class WebsitePublicService
{
    public function __construct(
        private WebsiteChannelMapper $channels,
        private WebsiteNavMapper $navs,
        private WebsiteContentMapper $contents,
        private WebsiteBlockMapper $blocks,
        private WebsiteLeadService $leads
    ) {}

    public function bootstrap(array $params, WebsiteOpenApiContext $context): array
    {
        $site = $this->resolveSite($params, $context);

        return [
            'site' => $this->sitePayload($site),
            'navs' => [
                'top' => $this->navs->publicTree($site, 'top'),
                'bottom' => $this->navs->publicTree($site, 'bottom'),
            ],
            'channels' => $this->channels->publicTree($site),
            'blocks' => $this->publicBlocks($site, 'home'),
        ];
    }

    public function site(array $params, WebsiteOpenApiContext $context): array
    {
        return $this->sitePayload($this->resolveSite($params, $context));
    }

    public function navTree(array $params, WebsiteOpenApiContext $context): array
    {
        $site = $this->resolveSite($params, $context);

        return $this->navs->publicTree($site, (string)($params['position'] ?? 'top'));
    }

    public function channelTree(array $params, WebsiteOpenApiContext $context): array
    {
        return $this->channels->publicTree($this->resolveSite($params, $context));
    }

    public function page(array $params, WebsiteOpenApiContext $context): array
    {
        $site = $this->resolveSite($params, $context);
        $route = WebsiteData::route((string)($params['route'] ?? '/'));
        $channel = WebsiteChannel::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('tenant_id', (int)$site->tenant_id)
            ->where('site_id', (int)$site->id)
            ->where('status', Status::ENABLED)
            ->where('route', $route)
            ->first();
        $content = $this->contents->publicQuery($site)->where('route', $route)->first();
        $pageCode = $route === '/' ? 'home' : trim($route, '/');
        if ($channel instanceof WebsiteChannel && (string)$channel->code !== '') {
            $pageCode = (string)$channel->code;
        } elseif ($content instanceof WebsiteContent && (string)$content->slug !== '') {
            $pageCode = (string)$content->slug;
        }

        return [
            'site' => $this->sitePayload($site),
            'route' => $route,
            'page_code' => $pageCode,
            'channel' => $channel instanceof WebsiteChannel ? $this->publicModelData($channel->toArray()) : null,
            'content' => $content instanceof WebsiteContent ? $this->publicModelData($content->toArray()) : null,
            'blocks' => $this->publicBlocks($site, $pageCode),
        ];
    }

    public function contentList(array $params, WebsiteOpenApiContext $context): array
    {
        $site = $this->resolveSite($params, $context);
        $pageSize = max(1, min((int)($params['pageSize'] ?? $params['page_size'] ?? 15), 100));
        $currentPage = max(1, (int)($params['page'] ?? 1));
        $query = $this->contents->publicQuery($site);
        if (trim((string)($params['type'] ?? '')) !== '') {
            $query->where('type', trim((string)$params['type']));
        }
        $channel = trim((string)($params['channel'] ?? ''));
        if ($channel !== '') {
            $ids = $this->channels->publicChannelIds($site, $channel);
            if ($ids === []) {
                $query->where('id', 0);
            } else {
                $query->whereIn('channel_id', $ids);
            }
        }
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(static function (Builder $builder) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $builder->where('title', 'like', $like)->orWhere('summary', 'like', $like);
            });
        }
        $total = (int)$query->clone()->count();
        $items = $query->orderBy('is_top', 'desc')
            ->orderBy('sort', 'desc')
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc')
            ->forPage($currentPage, $pageSize)
            ->get()
            ->map(fn (WebsiteContent $content): array => $this->publicModelData($content->toArray()))
            ->all();

        return [
            'items' => $items,
            'pageInfo' => [
                'total' => $total,
                'totalPage' => (int)ceil($total / $pageSize),
                'currentPage' => $currentPage,
            ],
        ];
    }

    public function contentInfo(int $id, array $params, WebsiteOpenApiContext $context): array
    {
        $site = $this->resolveSite($params, $context);
        $content = $this->contents->publicQuery($site)->where('id', $id)->first();
        if (!$content instanceof WebsiteContent) {
            throw new ErrorResponseException('内容不存在或未发布');
        }

        return $this->publicModelData($content->toArray());
    }

    public function contentBySlug(string $slug, array $params, WebsiteOpenApiContext $context): array
    {
        $site = $this->resolveSite($params, $context);
        $content = $this->contents->publicQuery($site)->where('slug', strtolower(trim($slug)))->first();
        if (!$content instanceof WebsiteContent) {
            throw new ErrorResponseException('内容不存在或未发布');
        }

        return $this->publicModelData($content->toArray());
    }

    public function blockList(array $params, WebsiteOpenApiContext $context): array
    {
        $site = $this->resolveSite($params, $context);
        $pageCode = trim((string)($params['page_code'] ?? 'home')) ?: 'home';
        $groupCode = trim((string)($params['group_code'] ?? ''));

        return $this->publicBlocks($site, $pageCode, $groupCode);
    }

    public function createLead(array $params, WebsiteOpenApiContext $context, string $ip, string $userAgent): array
    {
        $site = $this->resolveSite($params, $context);
        $lead = $this->leads->createPublic($site, $params, $ip, $userAgent);

        return [
            'id' => (int)$lead->id,
            'status' => (string)$lead->status,
            'created_at' => (string)$lead->created_at,
        ];
    }

    public function resolveSite(array $params, WebsiteOpenApiContext $context): WebsiteSite
    {
        $site = $context->site;
        $value = strtolower(trim((string)($params['site'] ?? '')));
        if ($value === '') {
            return $site;
        }

        // 开放接口站点只能来自 app_id 绑定关系；site 参数仅允许作为前端显式确认值，不能触发跨站解析。
        $allowed = array_merge([(string)$site->code, (string)$site->domain], WebsiteData::stringList($site->aliases));
        $allowed = array_values(array_unique(array_filter(array_map(static fn (string $item): string => strtolower(trim($item)), $allowed))));
        if (!in_array($value, $allowed, true)) {
            throw new NotAllowResponseException('开放接口应用无权访问该站点');
        }

        return $site;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publicBlocks(WebsiteSite $site, string $pageCode, string $groupCode = ''): array
    {
        $query = $this->blocks->publicQuery($site)->where('page_code', strtolower(trim($pageCode) ?: 'home'));
        if ($groupCode !== '') {
            $query->where('group_code', strtolower($groupCode));
        }

        return $query->orderBy('sort', 'desc')
            ->orderBy('id')
            ->get()
            ->map(fn ($block): array => $this->publicModelData($block->toArray()))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(WebsiteSite $site): array
    {
        return $this->publicModelData($site->toArray());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function publicModelData(array $data): array
    {
        foreach (['tenant_id', 'created_by', 'updated_by', 'deleted_at'] as $field) {
            unset($data[$field]);
        }

        return $data;
    }
}
