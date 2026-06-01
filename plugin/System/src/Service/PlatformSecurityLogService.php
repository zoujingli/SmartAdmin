<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Hyperf\Logger\LoggerFactory;
use Library\CoreService;
use Psr\Log\LoggerInterface;

/**
 * 平台安全日志服务。
 *
 * 未解析到可信租户的登录前失败、异常链路和开放入口安全事件不能写入任一租户操作日志。
 */
final class PlatformSecurityLogService extends CoreService
{
    private LoggerInterface $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('log');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function write(array $data): void
    {
        unset($data['tenant_id']);
        unset($data['change_payload']);
        $this->logger->warning('platform-security', $data);
    }
}
