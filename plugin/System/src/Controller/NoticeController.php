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
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use System\Service\NoticeService;

#[Auth(name: '系统公告中心')]
#[Controller(prefix: 'system/notice')]
final class NoticeController extends CoreController
{
    /**
     * @param NoticeService $service 公告业务服务
     */
    public function __construct(
        protected NoticeService $service
    ) {}

    /**
     * 获取公告分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '公告管理', type: Auth::CHECK, menu: true)]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取公告回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '公告回收站', type: Auth::CHECK, menu: false, code: 'system.notice.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取公告详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '公告详情', type: Auth::CHECK, menu: false, code: 'system.notice.index')]
    public function info(int $id): array
    {
        $this->success('获取成功', $this->service->getNoticeDetail($id));
    }

    /**
     * 创建公告。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '新增公告', type: Auth::CHECK, menu: false)]
    #[Logger(name: '新增公告')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新公告。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑公告', type: Auth::CHECK, menu: false)]
    #[Logger(name: '编辑公告')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->getNoticeDetail($id));
    }

    /**
     * 删除公告（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除公告', type: Auth::CHECK, menu: false)]
    #[Logger(name: '删除公告')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 恢复公告。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复公告', type: Auth::CHECK, menu: false)]
    #[Logger(name: '恢复公告')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 彻底删除公告。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除公告', type: Auth::CHECK, menu: false)]
    #[Logger(name: '彻底删除公告')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 发布公告。
     */
    #[PutMapping(path: 'publish/{id}')]
    #[Auth(name: '发布公告', type: Auth::CHECK, menu: false)]
    #[Logger(name: '发布公告')]
    public function publish(int $id): array
    {
        $this->success('发布成功', $this->service->publish($id));
    }

    /**
     * 更新公告状态。
     */
    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新公告状态', type: Auth::CHECK, menu: false)]
    #[Logger(name: '更新公告状态')]
    public function status(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeStatus($id, $request->input('status', 1)));
    }

    /**
     * 获取当前用户公告收件箱。
     */
    #[GetMapping(path: 'inbox')]
    #[Auth(name: '我的公告收件箱', type: Auth::LOGIN)]
    public function inbox(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getInbox($request->all(), (int)(user()?->getId() ?? 0)));
    }

    /**
     * 获取当前用户未读公告数。
     */
    #[GetMapping(path: 'unread-count')]
    #[Auth(name: '公告未读数', type: Auth::LOGIN)]
    public function unreadCount(): array
    {
        $this->success('获取成功', $this->service->getUnreadCount((int)(user()?->getId() ?? 0)));
    }

    /**
     * 标记指定公告为已读。
     */
    #[PutMapping(path: 'read/{ids}')]
    #[Auth(name: '标记公告已读', type: Auth::LOGIN)]
    #[Logger(name: '标记公告已读')]
    public function read(string $ids): array
    {
        $this->success('操作成功', $this->service->read($this->idsOrFail($ids), (int)(user()?->getId() ?? 0)));
    }

    /**
     * 标记全部公告为已读。
     */
    #[PutMapping(path: 'read-all')]
    #[Auth(name: '全部公告已读', type: Auth::LOGIN)]
    #[Logger(name: '全部公告已读')]
    public function readAll(): array
    {
        $this->success('操作成功', $this->service->readAll((int)(user()?->getId() ?? 0)));
    }

    /**
     * 归档指定公告。
     */
    #[PutMapping(path: 'archive/{ids}')]
    #[Auth(name: '归档公告', type: Auth::LOGIN)]
    #[Logger(name: '归档公告')]
    public function archive(string $ids): array
    {
        $this->success('操作成功', $this->service->archive($this->idsOrFail($ids), (int)(user()?->getId() ?? 0)));
    }

    /**
     * 归档全部公告。
     */
    #[PutMapping(path: 'archive-all')]
    #[Auth(name: '归档全部公告', type: Auth::LOGIN)]
    #[Logger(name: '归档全部公告')]
    public function archiveAll(): array
    {
        $this->success('操作成功', $this->service->archiveAll((int)(user()?->getId() ?? 0)));
    }
}
