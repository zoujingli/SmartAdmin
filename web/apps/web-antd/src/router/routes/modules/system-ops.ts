import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    meta: {
      icon: 'lucide:blocks',
      order: 2000,
      title: '平台运维',
    },
    name: 'SystemOps',
    path: '/system/ops',
    children: [
      {
        name: 'TenantManage',
        path: '/system/tenant',
        component: () => import('@plugin/System/stc/view/tenant/index.vue'),
        meta: {
          icon: 'lucide:building-2',
          title: '租户管理',
        },
      },
      {
        name: 'SystemNotice',
        path: '/system/notice',
        component: () => import('@plugin/System/stc/view/notice/index.vue'),
        meta: {
          icon: 'lucide:bell-ring',
          title: '通知中心',
        },
      },
      {
        name: 'SystemLogsAction',
        path: '/system/logs/action',
        component: () => import('@plugin/System/stc/view/logs/action/index.vue'),
        meta: {
          icon: 'lucide:file-text',
          title: '操作日志',
        },
      },
      {
        name: 'SystemLogsChange',
        path: '/system/logs/change',
        component: () => import('@plugin/System/stc/view/logs/change/index.vue'),
        meta: {
          icon: 'lucide:file-diff',
          title: '变更日志',
        },
      },
      {
        name: 'SystemFile',
        path: '/system/file',
        component: () => import('@plugin/System/stc/view/file/index.vue'),
        meta: {
          icon: 'lucide:folder-open',
          title: '文件管理',
        },
      },
      {
        name: 'SystemSetting',
        path: '/system/setting',
        component: () => import('@plugin/System/stc/view/setting/index.vue'),
        meta: {
          icon: 'lucide:sliders-horizontal',
          title: '系统参数',
        },
      },
      {
        name: 'SystemData',
        path: '/system/data',
        component: () => import('@plugin/System/stc/view/data/index.vue'),
        meta: {
          icon: 'lucide:database',
          title: '系统数据',
        },
      },
    ],
  },
];

export default routes;
