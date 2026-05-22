import type { RouteRecordStringComponent } from '@vben/types';

import { requestClient } from '#/api/request';

import { getAuthEntryMenus, isSystemAuthEntry } from './auth';

export const coreMenuApiService = {
  getUserMenus() {
    if (!isSystemAuthEntry()) {
      // 插件前台菜单由插件 view/auth-entry.ts 声明，web 壳只按当前入口权限码裁剪。
      return Promise.resolve(getAuthEntryMenus());
    }

    return requestClient.get<RouteRecordStringComponent[]>('/system/menu/user');
  },
};
