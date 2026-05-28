<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\Website\Service\WebsiteLeadService;

#[Auth(name: '官网线索管理')]
#[Controller(prefix: 'system/website/lead')]
final class SystemLeadController extends CoreController
{
    public function __construct(
        protected WebsiteLeadService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '官网线索列表', type: Auth::CHECK, menu: true, code: 'website.lead.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '官网线索详情', type: Auth::CHECK, menu: false, code: 'website.lead.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    #[PutMapping(path: 'handle/{id}')]
    #[Auth(name: '处理官网线索', type: Auth::CHECK, menu: false, code: 'website.lead.handle')]
    #[Logger(name: '处理官网线索', excludeFields: ['mobile', 'email'])]
    public function handle(int $id, RequestInterface $request): array
    {
        $this->success('处理成功', $this->service->handle($id, $request->all()));
    }

    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新官网线索状态', type: Auth::CHECK, menu: false, code: 'website.lead.status')]
    #[Logger(name: '更新官网线索状态', excludeFields: ['mobile', 'email'])]
    public function status(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeLeadStatus($id, (string)$request->input('status', 'pending')));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除官网线索', type: Auth::CHECK, menu: false, code: 'website.lead.delete')]
    #[Logger(name: '删除官网线索')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray): bool => $this->service->delete($idArray));
    }
}
