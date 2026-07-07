import type { RouteLocationNormalized, RouteRecordName } from 'vue-router';

interface ShouldRebuildAccessRoutesOptions {
  coreRouteNames: RouteRecordName[];
  isEntryPath: (path: string) => boolean;
  isKnownAccessPath: (path: string) => boolean;
}

interface RouteReentrySource {
  hash: string;
  path: string;
  query: RouteLocationNormalized['query'];
}

type ProjectFlowProgressDemoRouteSource = Pick<RouteLocationNormalized, 'path' | 'query'>
  & Partial<Pick<RouteLocationNormalized, 'fullPath'>>;

const MODULE_GUIDE_PATH = '/entry';
const PROJECT_FLOW_PROGRESS_DEMO_PATHS = ['/project/flow-progress', '/project/gantt'];

function normalizeClientPath(path?: string): string {
  const raw = String(path || '').split(/[?#]/)[0] || '';

  return `/${raw.replace(/^\/+/, '')}`.replace(/\/+$/, '') || '/';
}

function firstQueryValue(value: unknown): string {
  const raw = Array.isArray(value) ? value[0] : value;

  return String(raw ?? '').trim();
}

function queryValueFromFullPath(fullPath: unknown, key: string): string {
  const source = String(fullPath || '');
  const query = source.includes('?') ? source.split('?')[1]?.split('#')[0] || '' : '';
  if (!query) {
    return '';
  }

  return new URLSearchParams(query).get(key)?.trim() || '';
}

function currentBrowserFullPath(): string {
  if (typeof window === 'undefined') {
    return '';
  }

  return `${window.location.pathname}${window.location.search}${window.location.hash}`;
}

function isDemoPath(value: unknown): boolean {
  const source = String(value || '');
  if (!source) {
    return false;
  }

  const hashRoute = source.includes('#/')
    ? source.slice(source.indexOf('#') + 1)
    : source;

  return PROJECT_FLOW_PROGRESS_DEMO_PATHS.includes(normalizeClientPath(hashRoute));
}

function shouldPreserveModuleGuideHistory(from: Pick<RouteLocationNormalized, 'path'>): boolean {
  return normalizeClientPath(from.path) === MODULE_GUIDE_PATH;
}

/**
 * 流程进度甘特图浏览器验收需要在未登录时渲染真实页面。
 * 该入口只在 Vite 开发环境、固定页面、固定查询参数下放行，避免扩大 Project 其它业务页访问面。
 */
function isProjectFlowProgressDemoRoute(
  to: ProjectFlowProgressDemoRouteSource,
  isDev = import.meta.env.DEV,
): boolean {
  const demoQuery = firstQueryValue(to.query.flow_progress_demo)
    || queryValueFromFullPath(to.fullPath, 'flow_progress_demo')
    || queryValueFromFullPath(currentBrowserFullPath(), 'flow_progress_demo');
  const demoPath = isDemoPath(to.path)
    || isDemoPath(to.fullPath)
    || isDemoPath(currentBrowserFullPath());

  return isDev
    && demoPath
    && demoQuery === '1';
}

/**
 * 后台插件菜单可能在登录后同步或热更新；当已知菜单路径首跳落到全局 fallback，
 * 需要重建动态路由并重新进入原地址。真实未知路径不能只因命中入口前缀就重建，
 * 否则已废弃页面会反复触发菜单加载和 404 提示。
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

  return options.isEntryPath(to.path) && options.isKnownAccessPath(to.path);
}

function routeReentry(to: RouteReentrySource, options: { replace?: boolean } = {}) {
  return {
    hash: to.hash,
    path: to.path,
    query: to.query,
    replace: options.replace ?? true,
  };
}

export {
  isProjectFlowProgressDemoRoute,
  routeReentry,
  shouldPreserveModuleGuideHistory,
  shouldRebuildAccessRoutes,
};
