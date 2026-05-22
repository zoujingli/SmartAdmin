<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use System\Service\FileService;

#[Controller(prefix: 'system/file')]
final class FileDownloadController extends CoreController
{
    /**
     * @param FileService $service 文件业务服务
     */
    public function __construct(
        protected FileService $service
    ) {}

    /**
     * 公开签名下载入口。
     *
     * 不挂 Auth 注解，避免复制出的短期签名链接仍被登录态拦截；访问权限完全由签名和过期时间校验承担。
     */
    #[GetMapping(path: 'download-signed/{id}')]
    public function signedDownload(int $id, RequestInterface $request): PsrResponseInterface
    {
        $target = $this->service->getSignedDownloadTarget($id, $request->all());
        if ($target['type'] === 'local') {
            return $this->response->download($target['path'], $target['name']);
        }

        return $this->response->redirect($target['url']);
    }
}
