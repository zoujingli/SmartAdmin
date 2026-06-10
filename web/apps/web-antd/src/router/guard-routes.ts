import type { RouteLocationNormalized, RouteRecordName } from 'vue-router';

interface ShouldRebuildAccessRoutesOptions {
  coreRouteNames: RouteRecordName[];
  isEntryPath: (path: string) => boolean;
}

interface RouteReentrySource {
  hash: string;
  path: string;
  query: RouteLocationNormalized['query'];
}

const MODULE_GUIDE_PATH = '/entry';

function normalizeClientPath(path?: string): string {
  const raw = String(path || '').split(/[?#]/)[0] || '';

  return `/${raw.replace(/^\/+/, '')}`.replace(/\/+$/, '') || '/';
}

function shouldPreserveModuleGuideHistory(from: Pick<RouteLocationNormalized, 'path'>): boolean {
  return normalizeClientPath(from.path) === MODULE_GUIDE_PATH;
}

/**
 * 后台插件菜单可能在登录后同步或热更新；当首跳已经落到全局 fallback，
 * 但路径仍属于当前入口时，需要重建动态路由并重新进入原地址。
 */
function shouldRebuildAccessRoutes(
  to: Pick<RouteLocationNormalized, 'matched' | 'meta' | 'name' | 'path'>,
  options: ShouldRebuildAccessRoutesOptions,
): boolean {
  if (to.meta.ignoreAccess || options.coreRouteNames.includes(to.name)) {
    return false;
  }

  const isFallback = to.name === 'FallbackNotFound'
    || to.matched.some((route) => route.name === 'FallbackNotFound');
  if (!isFallback) {
    return false;
  }

  return options.isEntryPath(to.path);
}

function routeReentry(to: RouteReentrySource, options: { replace?: boolean } = {}) {
  return {
    hash: to.hash,
    path: to.path,
    query: to.query,
    replace: options.replace ?? true,
  };
}

export { routeReentry, shouldPreserveModuleGuideHistory, shouldRebuildAccessRoutes };
