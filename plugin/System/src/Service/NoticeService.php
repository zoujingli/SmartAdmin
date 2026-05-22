<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Service;

use Hyperf\Database\Model\Model;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Exception\NotAllowResponseException;
use Library\Support\ModelChangeLog;
use System\Mapper\NoticeMapper;
use System\Mapper\UserMapper;
use System\Model\SystemNotice;

final class NoticeService extends CoreService
{
    private const LEVELS = ['info', 'success', 'warning', 'error'];

    /**
     * @param NoticeMapper $mapper 公告数据访问层
     * @param UserMapper $users 用户数据访问层
     */
    public function __construct(
        protected NoticeMapper $mapper,
        protected UserMapper $users
    ) {}

    /**
     * 创建公告并同步接收人。
     */
    public function create(array $data): ?Model
    {
        [$payload, $recipientIds] = $this->extractPayload($data);
        $recipientIds = $this->ensureScopedRecipientIds($recipientIds ?? []);

        /** @var SystemNotice|null $notice */
        $notice = parent::create($payload);
        if (!$notice) {
            return null;
        }

        $oldNames = $this->recipientNames((int)$notice->id);
        $this->mapper->syncRecipients((int)$notice->id, $recipientIds);
        $this->recordRecipientChange((int)$notice->id, $oldNames, $this->recipientNames((int)$notice->id));

        return $this->mapper->getNoticeWithRecipients((int)$notice->id);
    }

    /**
     * 更新公告并按需同步接收人。
     */
    public function update(mixed $id, array $data): bool
    {
        [$payload, $recipientIds] = $this->extractPayload($data);
        if ($recipientIds !== null) {
            $recipientIds = $this->ensureScopedRecipientIds($recipientIds);
        }

        $result = parent::update($id, $payload);
        if (!$result) {
            return false;
        }

        if ($recipientIds !== null) {
            $oldNames = $this->recipientNames((int)$id);
            $this->mapper->syncRecipients((int)$id, $recipientIds);
            $this->recordRecipientChange((int)$id, $oldNames, $this->recipientNames((int)$id));
        }

        return $result;
    }

    /**
     * 获取公告详情。
     */
    public function getNoticeDetail(int $id): array
    {
        $notice = $this->mapper->getNoticeWithRecipients($id);
        if (!$notice) {
            throw new ErrorResponseException('公告不存在');
        }

        $data = $notice->toArray();
        $data['recipient_count'] = (int)($data['recipients_count'] ?? count($data['recipients'] ?? []));
        $data['recipient_ids'] = array_values(array_map(
            static fn (array $recipient): int => (int)($recipient['user_id'] ?? 0),
            array_filter($data['recipients'] ?? [], static fn (mixed $recipient): bool => is_array($recipient))
        ));

        return $data;
    }

    /**
     * 发布公告并刷新接收人快照。
     */
    public function publish(int $id): array
    {
        /** @var null|SystemNotice $notice */
        $notice = $this->mapper->read($id);
        if (!$notice) {
            throw new ErrorResponseException('公告不存在');
        }

        if (!Status::isEnabled((int)$notice->status)) {
            throw new ErrorResponseException('停用公告不能发布');
        }

        $recipientIds = $this->mapper->getRecipientIds($id);
        $recipientIds = $this->ensureScopedRecipientIds($recipientIds);

        $oldNames = $this->recipientNames($id);
        $this->mapper->syncRecipients($id, $recipientIds);
        $this->recordRecipientChange($id, $oldNames, $this->recipientNames($id));
        $this->mapper->update($notice, ['published_at' => date('Y-m-d H:i:s')]);

        return $this->getNoticeDetail($id);
    }

    /**
     * 修改公告启停状态。
     */
    public function changeStatus(int $id, mixed $status): array
    {
        $status = (int)_vali([
            'status.value' => $status,
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ])['status'];

        if (!parent::update($id, ['status' => $status])) {
            throw new ErrorResponseException('公告不存在');
        }

        return $this->getNoticeDetail($id);
    }

    /**
     * 获取用户收件箱。
     */
    public function getInbox(array $params, int $userId): array
    {
        return $this->mapper->getInbox($params, $userId);
    }

    /**
     * 获取用户未读数量。
     */
    public function getUnreadCount(int $userId): array
    {
        return ['count' => $this->mapper->getUnreadCount($userId)];
    }

    /**
     * @param array<int, int> $noticeIds
     */
    public function read(array $noticeIds, int $userId): array
    {
        return ['affected' => $this->mapper->markRead($userId, $noticeIds)];
    }

    /**
     * 标记当前用户全部公告为已读。
     */
    public function readAll(int $userId): array
    {
        return ['affected' => $this->mapper->markAllRead($userId)];
    }

    /**
     * @param array<int, int> $noticeIds
     */
    public function archive(array $noticeIds, int $userId): array
    {
        return ['affected' => $this->mapper->archive($userId, $noticeIds)];
    }

    /**
     * 归档当前用户全部公告。
     */
    public function archiveAll(int $userId): array
    {
        return ['affected' => $this->mapper->archiveAll($userId)];
    }

    /**
     * 公告写入前数据校验与归一化。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['title', 'content', 'link', 'level'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['published_at', 'expired_at'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = trim((string)($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'title.filled' => '公告标题不能为空',
            'title.max:120' => '公告标题最多 120 位',
            'content.max:20000' => '公告内容最多 20000 位',
            'level.in:' . implode(',', self::LEVELS) => '公告级别错误',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'published_at.nullable' => '发布时间格式错误',
            'published_at.date' => '发布时间格式错误',
            'expired_at.nullable' => '过期时间格式错误',
            'expired_at.date' => '过期时间格式错误',
            'link.max:255' => '跳转链接最多 255 位',
        ];
        if ($exists === []) {
            $rules['title.required'] = '公告标题不能为空';
            $rules['level.default'] = 'info';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['tenant_id', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    /**
     * @return array{0: array<string, mixed>, 1: null|array<int, int>}
     */
    private function extractPayload(array $data): array
    {
        $recipientIds = null;
        if (array_key_exists('recipient_ids', $data)) {
            $values = is_array($data['recipient_ids']) ? $data['recipient_ids'] : [$data['recipient_ids']];
            $recipientIds = array_values(array_unique(array_filter(array_map(static fn (mixed $id): int => (int)$id, $values))));
            unset($data['recipient_ids']);
        }

        return [$data, $recipientIds];
    }

    /**
     * 公告的收件人属于业务授权边界：创建、更新、发布都会校验所有用户 ID 必须在当前操作者的数据范围内。
     * 返回值保持原始请求顺序去重后的 ID 列表，任何越权或空列表都直接拒绝，避免猜 ID 给范围外用户发公告。
     *
     * @param array<int, int> $recipientIds
     * @return array<int, int>
     */
    private function ensureScopedRecipientIds(array $recipientIds): array
    {
        $recipientIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int)$id, $recipientIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($recipientIds === []) {
            throw new ErrorResponseException('请至少选择一个接收用户');
        }

        $visibleIds = $this->users->filterScopedUserIds($recipientIds);
        $missingIds = array_values(array_diff($recipientIds, $visibleIds));
        if ($missingIds !== []) {
            throw new NotAllowResponseException('存在无权限接收用户', ['invalid_user_ids' => $missingIds]);
        }

        return $recipientIds;
    }

    /**
     * 公告接收人是关系快照，批量删除/插入不会触发公告模型事件，需要 Service 显式记录。
     *
     * @param array<int, string> $oldNames
     * @param array<int, string> $newNames
     */
    private function recordRecipientChange(int $noticeId, array $oldNames, array $newNames): void
    {
        $notice = $this->mapper->read($noticeId);
        if (!$notice instanceof SystemNotice) {
            return;
        }

        ModelChangeLog::recordFields($notice, 'updated', [[
            'field' => 'recipients',
            'label' => '接收人',
            'old' => $oldNames,
            'new' => $newNames,
        ]]);
    }

    /**
     * @return array<int, string>
     */
    private function recipientNames(int $noticeId): array
    {
        return $this->mapper->getRecipientNames($this->mapper->getRecipientIds($noticeId));
    }
}
