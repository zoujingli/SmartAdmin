import { SystemApiService } from '../base';
import { createPageParams, createSearchParams } from '../utils';

export namespace NoticeApi {
  export type NoticeLevel = 'error' | 'info' | 'success' | 'warning';

  export interface NoticeInfo {
    id: number;
    title: string;
    content: string;
    level: NoticeLevel;
    status: number;
    published_at: null | string;
    expired_at: null | string;
    link: string;
    recipient_count?: number;
    recipient_ids?: number[];
    recipients?: Array<{
      id: number;
      user_id: number;
      is_read: number;
      read_at: null | string;
      archived_at: null | string;
      user?: {
        id: number;
        username: string;
        nickname: string;
        avatar: string;
        status: number;
      };
    }>;
    created_at: string;
    updated_at: string;
    deleted_at?: null | string;
  }

  export interface InboxItem {
    id: number;
    title: string;
    content: string;
    level: NoticeLevel;
    status: number;
    link: string;
    published_at: null | string;
    expired_at: null | string;
    is_read: boolean;
    read_at: null | string;
    archived_at: null | string;
    created_at: null | string;
    updated_at: null | string;
  }

  export interface NoticeFormData {
    title: string;
    content: string;
    level: NoticeLevel;
    status: number;
    expired_at?: null | string;
    link?: string;
    recipient_ids: number[];
  }

  export interface NoticeListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    level?: NoticeLevel;
    status?: number;
  }
}

class NoticeApiService extends SystemApiService {
  async getNoticeList(params: NoticeApi.NoticeListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);
    return this.getList<NoticeApi.NoticeInfo>('system/notice/index', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getRecycleList(params: NoticeApi.NoticeListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);
    return this.getList<NoticeApi.NoticeInfo>('system/notice/recycle', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getNoticeDetail(id: number) {
    return this.getDetail<NoticeApi.NoticeInfo>('system/notice/info', id);
  }

  async createNotice(data: NoticeApi.NoticeFormData) {
    return this.create<NoticeApi.NoticeInfo>('system/notice/create', data);
  }

  async updateNotice(id: number, data: NoticeApi.NoticeFormData) {
    return this.update<NoticeApi.NoticeInfo>('system/notice/update', id, data);
  }

  async deleteNotice(id: number) {
    return this.remove('system/notice/delete', id);
  }

  async batchDeleteNotices(ids: number[]) {
    return this.remove('system/notice/delete', ids);
  }

  async recoveryNotices(ids: number[]) {
    return this.put(`system/notice/recovery/${ids.join(',')}`);
  }

  async realDeleteNotices(ids: number[]) {
    return this.delete(`system/notice/real-delete/${ids.join(',')}`);
  }

  async publishNotice(id: number) {
    return this.put<NoticeApi.NoticeInfo>(`system/notice/publish/${id}`);
  }

  async updateNoticeStatus(id: number, status: number) {
    return this.updateStatus('system/notice/status', id, status);
  }

  async getInbox(params: NoticeApi.NoticeListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);
    return this.getList<NoticeApi.InboxItem>('system/notice/inbox', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getUnreadCount() {
    return this.get<{ count: number }>('system/notice/unread-count');
  }

  async read(ids: number[]) {
    return this.put(`system/notice/read/${ids.join(',')}`);
  }

  async readAll() {
    return this.put('system/notice/read-all');
  }

  async archive(ids: number[]) {
    return this.put(`system/notice/archive/${ids.join(',')}`);
  }

  async archiveAll() {
    return this.put('system/notice/archive-all');
  }
}

export const noticeApiService = new NoticeApiService();
