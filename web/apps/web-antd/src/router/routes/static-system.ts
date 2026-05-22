import type { RouteRecordRaw } from 'vue-router';

/**
 * 通知中心：任意已登录用户都可进入自己的通知页。
 */
export const systemNoticeRoute: RouteRecordRaw = {
  name: 'SystemNoticeInbox',
  path: '/system/notice',
  component: () => import('@plugin/System/stc/view/notice/index.vue'),
  meta: {
    hideInMenu: true,
    title: '通知中心',
  },
};
