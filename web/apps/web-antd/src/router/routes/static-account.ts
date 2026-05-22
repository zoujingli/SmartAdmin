import type { RouteRecordRaw } from 'vue-router';

import type { AuthEntryConfig } from '#/api';

/**
 * 登录用户个人资料页不进入后台菜单动态加载，由守卫按当前认证入口挂载。
 * System 与插件入口共用同一页面组件，展示文案和接口前缀由认证入口配置决定。
 */
export function createAccountProfileRoute(entry: AuthEntryConfig): RouteRecordRaw {
  return {
    name: entry.entry === 'system' ? 'AccountProfile' : `AuthProfile:${entry.entry}`,
    path: entry.profilePath || '/account/profile',
    component: () => import('#/views/account/profile.vue'),
    meta: {
      hideInMenu: true,
      title: entry.profile?.title || '个人资料',
    },
  };
}
