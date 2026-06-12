import type { NotificationItem } from '@vben/layouts';

export interface NotificationProvider {
  archive: (ids: number[]) => Promise<unknown>;
  archiveAll: () => Promise<unknown>;
  getInbox: () => Promise<NotificationItem[]>;
  getUnreadCount: () => Promise<number>;
  read: (ids: number[]) => Promise<unknown>;
  readAll: () => Promise<unknown>;
  viewAllPath?: string;
  viewAllQuery?: Record<string, string>;
}

let notificationProvider: null | NotificationProvider = null;

export function configureNotificationProvider(provider: NotificationProvider) {
  notificationProvider = provider;
}

export function getNotificationProvider() {
  return notificationProvider;
}
