<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Mapper;

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\Constants\System;
use Library\Constants\Status;
use Library\CoreMapper;
use System\Model\SystemNotice;
use System\Model\SystemNoticeUser;
use System\Model\SystemUser;

final class NoticeMapper extends CoreMapper
{
    /**
     * @param string $model 通知模型类
     */
    public function __construct(
        protected string $model = SystemNotice::class
    ) {}

    /**
     * 获取通知详情及接收人关联。
     */
    public function getNoticeWithRecipients(int $id): ?SystemNotice
    {
        $query = $this->model::with([
            // eager load 约束闭包接收 Relation 实例；不能强制声明 Builder，否则 HasMany 会触发类型错误。
            'recipients' => fn ($query) => $query->orderBy('id', 'desc'),
            'recipients.user' => fn ($query) => $query->select(['id', 'username', 'nickname', 'avatar', 'status']),
        ])->withCount('recipients');

        return $this->applyDataScope($query, 'created_by')->find($id);
    }

    /**
     * 获取通知接收人 ID 列表。
     *
     * @return array<int, int>
     */
    public function getRecipientIds(int $noticeId): array
    {
        return SystemNoticeUser::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('notice_id', $noticeId)
            ->orderBy('user_id', 'asc')
            ->pluck('user_id')
            ->map(static fn ($id): int => (int)$id)
            ->toArray();
    }

    /**
     * 同步通知接收人。
     *
     * @param array<int, int> $userIds
     */
    public function syncRecipients(int $noticeId, array $userIds): void
    {
        $notice = $this->model::query()->find($noticeId);
        $noticeTenantId = $notice instanceof SystemNotice ? (int)$notice->tenant_id : System::getTenantId();

        // 接收人归属必须跟随公告本身；删除时绕过当前上下文，清理历史错误租户归属的收件人快照。
        SystemNoticeUser::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('notice_id', $noticeId)
            ->delete();

        if ($userIds === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'tenant_id' => $noticeTenantId,
                'notice_id' => $noticeId,
                'user_id' => $userId,
                'is_read' => 0,
                'read_at' => null,
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        SystemNoticeUser::query()->insert($rows);
    }

    /**
     * 获取用户未读通知数。
     */
    public function getUnreadCount(int $userId): int
    {
        return (int)$this->makeInboxQuery($userId, [
            'only_unread' => true,
            'only_visible' => true,
        ])->count();
    }

    /**
     * 获取用户通知收件箱分页数据。
     */
    public function getInbox(array $params, int $userId): array
    {
        $pageSize = max(1, min(100, (int)($params['pageSize'] ?? $params['page_size'] ?? 10)));
        $currentPage = max(1, (int)($params['page'] ?? 1));
        $query = $this->makeInboxQuery($userId, $params);
        $total = (int)$query->clone()->count();
        $items = $query->forPage($currentPage, $pageSize)->get()->map(function (SystemNoticeUser $recipient): array {
            $notice = $recipient->notice;
            if (!$notice) {
                return [];
            }

            return [
                'id' => (int)$notice->id,
                'title' => (string)$notice->title,
                'content' => (string)$notice->content,
                'level' => (string)$notice->level,
                'status' => (int)$notice->status,
                'link' => (string)$notice->link,
                'published_at' => $this->formatDateTime($notice->published_at),
                'expired_at' => $this->formatDateTime($notice->expired_at),
                'is_read' => (bool)$recipient->is_read,
                'read_at' => $this->formatDateTime($recipient->read_at),
                'archived_at' => $this->formatDateTime($recipient->archived_at),
                'created_at' => $this->formatDateTime($notice->created_at),
                'updated_at' => $this->formatDateTime($notice->updated_at),
            ];
        })->filter(static fn (array $item): bool => $item !== [])->values()->toArray();

        return [
            'items' => $items,
            'pageInfo' => [
                'total' => $total,
                'totalPage' => (int)ceil($total / $pageSize),
                'currentPage' => $currentPage,
            ],
        ];
    }

    /**
     * 批量标记通知为已读。
     *
     * @param array<int, int> $noticeIds
     */
    public function markRead(int $userId, array $noticeIds): int
    {
        if ($noticeIds === []) {
            return 0;
        }

        return SystemNoticeUser::query()
            ->where('user_id', $userId)
            ->whereIn('notice_id', $noticeIds)
            ->whereNull('archived_at')
            ->update([
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 标记当前用户全部通知为已读。
     */
    public function markAllRead(int $userId): int
    {
        return SystemNoticeUser::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 批量归档通知。
     *
     * @param array<int, int> $noticeIds
     */
    public function archive(int $userId, array $noticeIds): int
    {
        if ($noticeIds === []) {
            return 0;
        }

        return SystemNoticeUser::query()
            ->where('user_id', $userId)
            ->whereIn('notice_id', $noticeIds)
            ->update([
                'archived_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 归档当前用户全部通知。
     */
    public function archiveAll(int $userId): int
    {
        return SystemNoticeUser::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->update([
                'archived_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 通知列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('title,content')
            ->equal('status,level')
            ->dateBetween('created_at')
            ->getQuery()
            ->withCount('recipients');
    }

    /**
     * 通知列表项格式化。
     */
    protected function handleListItems(array $items, array $params = []): array
    {
        return array_map(static function (mixed $item): array {
            $row = $item instanceof SystemNotice ? $item->toArray() : (array)$item;
            $row['recipient_count'] = (int)($row['recipients_count'] ?? $row['recipient_count'] ?? 0);
            unset($row['recipients_count']);

            return $row;
        }, $items);
    }

    /**
     * 构建用户收件箱查询。
     */
    private function makeInboxQuery(int $userId, array $params): Builder
    {
        $query = SystemNoticeUser::query()
            ->with(['notice'])
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->whereHas('notice', function (Builder $builder) use ($params) {
                $builder->whereNull('deleted_at')
                    ->where('status', Status::ENABLED)
                    ->whereNotNull('published_at');

                if (($params['only_visible'] ?? true) === true) {
                    $builder->where(function (Builder $subQuery) {
                        $subQuery->whereNull('expired_at')
                            ->orWhere('expired_at', '>', date('Y-m-d H:i:s'));
                    });
                }

                $keyword = trim((string)($params['keyword'] ?? ''));
                if ($keyword !== '') {
                    $like = "%{$keyword}%";
                    $builder->where(function (Builder $subQuery) use ($like) {
                        $subQuery->where('title', 'like', $like)
                            ->orWhere('content', 'like', $like);
                    });
                }

                $level = trim((string)($params['level'] ?? ''));
                if ($level !== '') {
                    $builder->where('level', $level);
                }
            })
            ->orderBy('id', 'desc');

        if (($params['only_unread'] ?? false) === true) {
            $query->where('is_read', 0);
        }

        return $query;
    }

    /**
     * 标准化时间字段输出格式。
     */
    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, string>
     */
    public function getRecipientNames(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        return SystemUser::query()
            ->whereIn('id', $userIds)
            ->orderBy('id', 'asc')
            ->pluck('username')
            ->map(static fn (mixed $username): string => (string)$username)
            ->toArray();
    }
}
