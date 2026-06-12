import { computed } from 'vue';
import { useRoute } from 'vue-router';

import backendPluginHomes from 'virtual:xadmin-plugin-backend-homes';

import { preferences } from '@vben/preferences';

import { getAuthEntryConfigs } from '#/api';
import { resolveFallbackHomePath } from '#/utils/fallback-home';

/**
 * Fallback 页面使用当前路由推导上下文首页。
 *
 * 默认后台保留平台默认首页；业务插件后台与插件前台独立入口返回各自插件首页。
 */
export function useFallbackHomePath() {
  const route = useRoute();

  return computed(() => resolveFallbackHomePath(route.path || route.fullPath, {
    authEntries: getAuthEntryConfigs(),
    backendHomes: backendPluginHomes,
    defaultHomePath: preferences.app.defaultHomePath,
  }));
}
