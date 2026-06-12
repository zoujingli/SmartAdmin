import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'SystemDeprecatedDashboardAnalytics',
    path: '/dashboard/analytics',
    redirect: '/dashboard/workspace',
    meta: {
      hideInMenu: true,
      hideInTab: true,
      title: '工作台',
    },
  },
  {
    name: 'SystemAuthentication',
    path: '/system/login',
    component: () => import('#/layouts/auth.vue'),
    meta: {
      hideInTab: true,
      title: '系统登录',
    },
    children: [
      {
        name: 'SystemLogin',
        path: '',
        component: () => import('#/views/_core/authentication/login.vue'),
        meta: {
          title: '系统登录',
        },
      },
    ],
  },
  {
    name: 'SystemNoticeInbox',
    path: '/system/notice',
    component: () => import('./notice/index.vue'),
    meta: {
      hideInMenu: true,
      requireAuth: true,
      title: '通知中心',
    },
  },
];

export default routes;
