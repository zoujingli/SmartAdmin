import type { NotificationItem } from '@vben/layouts';

import { configureUploadEndpoints } from '@vben/common-ui';

import { requestClient } from '#/api/request';
import { configureCacheClearProvider } from '#/plugins/cache-provider';
import { configureAuthEntryMenuProvider } from '#/plugins/menu-provider';
import { configureModuleGuideProvider } from '#/plugins/module-guide-provider';
import { configureNotificationProvider } from '#/plugins/notification-provider';
import { configureTaskProgressProvider } from '#/plugins/task-progress-provider';

import { dataApiService, noticeApiService } from './api';

function formatNoticeDate(value?: null | string) {
  return value || '刚刚';
}

function toNotificationItem(item: any): NotificationItem {
  return {
    id: item.id,
    date: formatNoticeDate(item.published_at || item.created_at),
    isRead: !!item.is_read,
    level: item.level,
    link: item.link || '/system/notice',
    message: item.content || '系统公告',
    query: item.link ? undefined : { tab: 'inbox' },
    title: item.title,
  };
}

export default function setupPlugin() {
  configureAuthEntryMenuProvider('system', () => requestClient.get('/system/menu/user'));
  configureCacheClearProvider(() => dataApiService.clearCache());
  configureModuleGuideProvider(() => dataApiService.getModuleGuide());
  configureTaskProgressProvider((taskId, limit = 50) => requestClient.get('/system/task/status', {
    params: { task_id: taskId, limit },
  }));
  configureNotificationProvider({
    archive: (ids) => noticeApiService.archive(ids),
    archiveAll: () => noticeApiService.archiveAll(),
    async getInbox() {
      const inbox = await noticeApiService.getInbox({ page: 1, pageSize: 10 });
      return (inbox.items || []).map(toNotificationItem);
    },
    async getUnreadCount() {
      const unread = await noticeApiService.getUnreadCount();
      return unread.count || 0;
    },
    read: (ids) => noticeApiService.read(ids),
    readAll: () => noticeApiService.readAll(),
    viewAllPath: '/system/notice',
    viewAllQuery: { tab: 'inbox' },
  });
  configureUploadEndpoints({
    abort: '/system/file/upload/abort',
    complete: '/system/file/upload/complete',
    list: '/system/file/index',
    partSign: '/system/file/upload/part-sign',
    prepare: '/system/file/upload/prepare',
    relay: '/system/file/upload/relay',
    relayChunk: '/system/file/upload/relay-chunk',
    runtime: '/system/file/upload/runtime',
  });
}
