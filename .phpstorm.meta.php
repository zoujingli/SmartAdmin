<?php

declare(strict_types=1);
/**
 * This file is part of ThinkAdmin for Hyperf.
 *
 * @document  https://hyperf.thinkadmin.top
 * @contact  Anyon <zoujingli@qq.com>
 * @license  https://github.com/zoujingli/ThinkAdmin/blob/v6/license
 */

namespace PHPSTORM_META {
    use Hyperf\Context\Context;
    use Psr\Container\ContainerInterface;

    // Reflect
    override(Context::get(0), map(['' => '@']));
    override(ContainerInterface::get(0), map(['' => '@']));
    override(\di(0), map(['' => '@']));
    override(\make(0), map(['' => '@']));
    override(\_once(0), map(['' => '@']));
    override(\Hyperf\Support\make(0), map(['' => '@']));
    override(\Hyperf\Support\optional(0), type(0));
    override(\Hyperf\Tappable\tap(0), type(0));
}

namespace Hyperf\Database\Model {
    class Builder
    {
        /**
         * @return Builder
         */
        public function userDataScope(?int $userid = null)
        {
        }
    }
}
