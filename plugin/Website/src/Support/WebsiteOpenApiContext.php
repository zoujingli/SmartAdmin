<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Support;

use Plugin\Website\Model\WebsiteApp;
use Plugin\Website\Model\WebsiteSite;

/**
 * 单次开放接口验签后的不可变上下文。
 *
 * 控制器把它传入公开服务后，服务层只能使用应用绑定的站点，不能再按 Host 或请求参数重新解析站点。
 */
final class WebsiteOpenApiContext
{
    public function __construct(
        public readonly WebsiteApp $app,
        public readonly WebsiteSite $site,
        public readonly string $scope,
        public readonly string $ip,
    ) {}
}
