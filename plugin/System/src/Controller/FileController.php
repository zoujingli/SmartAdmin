<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://doc.hyperf.thinkadmin.top
 */

namespace System\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use System\Service\FileService;
use System\Service\FileUploadService;
use System\Service\UploadConfigService;

#[Auth(name: '系统文件管理')]
#[Controller(prefix: 'system/file')]
final class FileController extends CoreController
{
    /**
     * @param FileService $service 文件业务服务
     * @param UploadConfigService $uploadConfig 上传配置服务
     * @param FileUploadService $uploads 上传会话服务
     */
    public function __construct(
        protected FileService $service,
        protected UploadConfigService $uploadConfig,
        protected FileUploadService $uploads,
    ) {}

    /**
     * 获取文件分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统文件列表', type: Auth::CHECK, menu: true)]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getFileList($request->all()));
    }

    /**
     * 获取文件回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '系统文件管理', type: Auth::CHECK, menu: false, code: 'system.file.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取文件详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '系统文件管理', type: Auth::CHECK, menu: false, code: 'system.file.index')]
    public function info(int $id): array
    {
        $this->success('获取成功', $this->service->getFileDetail($id));
    }

    /**
     * 下载文件（本地文件直传，远程文件重定向）。
     */
    #[GetMapping(path: 'download/{id}')]
    #[Auth(name: '下载系统文件', type: Auth::CHECK, menu: false, code: 'system.file.index')]
    public function download(int $id, RequestInterface $request): PsrResponseInterface
    {
        $target = $this->service->getDownloadTarget($id, (string)$request->input('attname', ''));
        if ($target['type'] === 'local') {
            return $this->response->download($target['path'], $target['name']);
        }

        return $this->response->redirect($target['url']);
    }

    /**
     * 更新文件信息。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑系统文件', type: Auth::CHECK, menu: false)]
    #[Logger(name: '编辑系统文件')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->updateFile($id, $request->all()));
    }

    /**
     * 删除文件（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除系统文件', type: Auth::CHECK, menu: false)]
    #[Logger(name: '删除系统文件')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除文件。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除系统文件', type: Auth::CHECK, menu: false)]
    #[Logger(name: '彻底删除系统文件')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复文件。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复系统文件', type: Auth::CHECK, menu: false)]
    #[Logger(name: '恢复系统文件')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 获取文件统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '系统文件管理', type: Auth::CHECK, menu: false, code: 'system.file.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

    /**
     * 获取上传通道配置。
     */
    #[GetMapping(path: 'upload-config')]
    #[Auth(name: '维护上传通道配置', type: Auth::CHECK, menu: false, code: 'system.file.upload-config')]
    public function getUploadConfig(): array
    {
        $this->success('获取成功', $this->uploadConfig->getConfigForDisplay());
    }

    /**
     * 更新上传通道配置。
     */
    #[PutMapping(path: 'upload-config')]
    #[Auth(name: '维护上传通道配置', type: Auth::CHECK, menu: false, code: 'system.file.upload-config')]
    #[Logger(
        name: '保存上传通道配置',
        excludeFields: [
            'drivers.oss.access_id',
            'drivers.oss.access_secret',
            'drivers.qiniu.access_key',
            'drivers.qiniu.secret_key',
            'drivers.cos.secret_id',
            'drivers.cos.secret_key',
            'drivers.alist.password',
        ]
    )]
    /**
     * 持久化上传通道配置并返回脱敏后的最新配置。
     */
    public function updateUploadConfig(RequestInterface $request): array
    {
        $this->success('更新成功', $this->uploadConfig->updateConfig($request->all()));
    }

    /**
     * 获取上传运行时配置。
     */
    #[GetMapping(path: 'upload/runtime')]
    #[Auth(name: '上传文件', type: Auth::LOGIN, menu: false)]
    public function uploadRuntime(): array
    {
        $this->success('获取成功', $this->uploadConfig->getRuntimeConfig());
    }

    /**
     * 准备上传会话。
     */
    #[PostMapping(path: 'upload/prepare')]
    #[Auth(name: '上传文件', type: Auth::LOGIN, menu: false)]
    #[Logger(name: '准备上传文件')]
    public function uploadPrepare(RequestInterface $request): array
    {
        $this->success('获取成功', $this->uploads->prepare($request->all()));
    }

    /**
     * 中转上传（单文件）。
     */
    #[PostMapping(path: 'upload/relay')]
    #[Auth(name: '上传文件', type: Auth::LOGIN, menu: false)]
    #[Logger(name: '中转上传文件')]
    public function uploadRelay(RequestInterface $request): array
    {
        $file = $request->file('file');
        if (!$file) {
            $this->error('缺少上传文件');
        }

        $sessionId = (string)$request->input('upload_session_id', '');
        $this->success('上传成功', $this->uploads->handleRelayUpload($sessionId, $file));
    }

    /**
     * 中转上传分块。
     */
    #[PostMapping(path: 'upload/relay-chunk')]
    #[Auth(name: '上传文件', type: Auth::LOGIN, menu: false)]
    #[Logger(name: '中转分块上传文件')]
    public function uploadRelayChunk(RequestInterface $request): array
    {
        $file = $request->file('file');
        if (!$file) {
            $this->error('缺少上传分块');
        }

        $this->success('上传成功', $this->uploads->handleRelayChunkUpload($request->all(), $file));
    }

    /**
     * 获取分片上传签名。
     */
    #[PostMapping(path: 'upload/part-sign')]
    #[Auth(name: '上传文件', type: Auth::LOGIN, menu: false)]
    public function uploadPartSign(RequestInterface $request): array
    {
        $sessionId = (string)$request->input('upload_session_id', '');
        $partNumber = (int)$request->input('part_number', 0);
        $this->success('获取成功', $this->uploads->signPart($sessionId, $partNumber));
    }

    /**
     * 完成上传会话。
     */
    #[PostMapping(path: 'upload/complete')]
    #[Auth(name: '上传文件', type: Auth::LOGIN, menu: false)]
    #[Logger(name: '完成上传文件')]
    public function uploadComplete(RequestInterface $request): array
    {
        $this->success('上传成功', $this->uploads->complete($request->all()));
    }

    /**
     * 中止上传会话。
     */
    #[PostMapping(path: 'upload/abort')]
    #[Auth(name: '上传文件', type: Auth::LOGIN, menu: false)]
    #[Logger(name: '终止上传文件')]
    public function uploadAbort(RequestInterface $request): array
    {
        $sessionId = (string)$request->input('upload_session_id', '');
        $this->success('操作成功', $this->uploads->abort($sessionId));
    }

    /**
     * 文件去重（支持批量与单哈希两种模式）。
     */
    #[PostMapping(path: 'dedupe')]
    #[Auth(name: '文件去重', type: Auth::CHECK, menu: false, code: 'system.file.delete')]
    #[Logger(name: '文件去重')]
    public function dedupe(RequestInterface $request): array
    {
        $items = $request->input('items', []);
        if (is_array($items) && $items !== []) {
            $this->success('去重成功', $this->service->dedupeBatch($items));
        }

        $hash = trim((string)$request->input('hash', ''));
        $driver = trim((string)$request->input('driver', ''));
        $this->success('去重成功', $this->service->dedupeByHash($hash, $driver !== '' ? $driver : null));
    }
}
