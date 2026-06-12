import { getAuthEntryMenuProvider } from '#/plugins/menu-provider';

import { getAuthEntry, getAuthEntryMenus } from './auth';

export const coreMenuApiService = {
  async getUserMenus() {
    const entry = getAuthEntry();
    const provider = getAuthEntryMenuProvider(entry);
    if (provider) {
      return provider();
    }

    // 未注册后端菜单 provider 的认证入口使用插件 auth-entry.ts 中声明的静态菜单。
    return Promise.resolve(getAuthEntryMenus(entry));
  },
};
