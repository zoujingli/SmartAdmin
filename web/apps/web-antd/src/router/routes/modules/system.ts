import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    meta: {
      icon: 'lucide:settings',
      order: 1000,
      title: '系统管理',
    },
    name: 'System',
    path: '/system',
    children: [
      {
        name: 'SystemUser',
        path: '/system/user',
        component: () => import('@plugin/System/stc/view/user/index.vue'),
        meta: {
          icon: 'lucide:users',
          title: '用户管理',
        },
      },
      {
        name: 'SystemRole',
        path: '/system/role',
        component: () => import('@plugin/System/stc/view/role/index.vue'),
        meta: {
          icon: 'lucide:shield',
          title: '角色管理',
        },
      },
      {
        name: 'SystemMenu',
        path: '/system/menu',
        component: () => import('@plugin/System/stc/view/menu/index.vue'),
        meta: {
          icon: 'lucide:menu',
          title: '菜单管理',
        },
      },
      {
        name: 'SystemDept',
        path: '/system/dept',
        component: () => import('@plugin/System/stc/view/dept/index.vue'),
        meta: {
          icon: 'lucide:building',
          title: '部门管理',
        },
      },
      {
        name: 'SystemPost',
        path: '/system/post',
        component: () => import('@plugin/System/stc/view/post/index.vue'),
        meta: {
          icon: 'lucide:briefcase',
          title: '岗位管理',
        },
      },
      {
        name: 'SystemDict',
        path: '/system/dict',
        component: () => import('@plugin/System/stc/view/dict/index.vue'),
        meta: {
          icon: 'lucide:book-open-text',
          title: '数据字典',
        },
      },
    ],
  },
];

export default routes;
