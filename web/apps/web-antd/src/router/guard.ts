import type { RouteLocationNormalized, Router } from 'vue-router';

import backendPluginHomes from 'virtual:xadmin-plugin-backend-homes';

import { LOGIN_PATH } from '@vben/constants';
import { preferences } from '@vben/preferences';
import { useAccessStore, useUserStore } from '@vben/stores';
import { generateMenus, startProgress, stopProgress } from '@vben/utils';

import {
  activateAuthEntry,
  coreAuthApiService,
  getAuthEntry,
  getAuthEntryByRoutePath,
  getAuthEntryConfig,
  getAuthEntryByUserInfo,
  getAuthHomePath,
  getAuthLoginPath,
  getLoginEntryByPath,
  isAuthLoginPath,
  isPluginAuthEntry,
  isSystemAuthEntry,
  isUserInfoForAuthEntry,
  routeBelongsToAuthEntry,
  SYSTEM_ENTRY,
} from '#/api';
import { accessRoutes, coreRouteNames } from '#/router/routes';
import { createAccountProfileRoute } from '#/router/routes/static-account';
import { systemNoticeRoute } from '#/router/routes/static-system';
import { useAuthStore } from '#/store';

import { generateAccess } from './access';

/**
 * 通用守卫配置
 * @param router
 */
function setupCommonGuard(router: Router) {
  // 记录已经加载的页面
  const loadedPaths = new Set<string>();

  router.beforeEach((to) => {
    to.meta.loaded = loadedPaths.has(to.path);

    // 页面加载进度条
    if (!to.meta.loaded && preferences.transition.progress) {
      startProgress();
    }
    return true;
  });

  router.afterEach((to) => {
    // 记录页面是否加载,如果已经加载，后续的页面切换动画等效果不在重复执行

    loadedPaths.add(to.path);

    // 关闭页面加载进度条
    if (preferences.transition.progress) {
      stopProgress();
    }
  });
}

/**
 * 权限访问守卫配置
 * @param router
 */
function setupAccessGuard(router: Router) {
  const accessRouteRebuildPaths = new Set<string>();

  function normalizeGuardPath(path?: string): string {
    const raw = String(path || '').split(/[?#]/)[0] || '';

    return `/${raw.replace(/^\/+/, '')}`.replace(/\/+$/, '') || '/';
  }

  function pathMatchesPrefix(path: string, prefix: string): boolean {
    return path === prefix || path.startsWith(`${prefix}/`);
  }

  function isBackendPluginPath(path?: string): boolean {
    const normalized = normalizeGuardPath(path);

    return backendPluginHomes.some((entry) => pathMatchesPrefix(normalized, normalizeGuardPath(entry.routePrefix)));
  }

  function isSystemPath(path?: string): boolean {
    const normalized = normalizeGuardPath(path);

    // System 登录体系不仅包含平台内置 /system/**，也包含由 plugin.json 声明的后台插件入口。
    return normalized === '/'
      || normalized === '/dashboard'
      || normalized.startsWith('/dashboard/')
      || normalized === '/system'
      || normalized.startsWith('/system/')
      || normalized === '/account/profile'
      || isBackendPluginPath(normalized);
  }

  function resetAccessRuntimeState() {
    const accessStore = useAccessStore();
    accessStore.setAccessCodes([]);
    accessStore.setAccessMenus([]);
    accessStore.setAccessRoutes([]);
    accessStore.setIsAccessChecked(false);
  }

  function decodeRedirectValue(value: unknown): string {
    const raw = Array.isArray(value) ? value[0] : value;
    let path = String(raw || '').trim();
    for (let i = 0; i < 2; i += 1) {
      try {
        const decoded = decodeURIComponent(path);
        if (decoded === path) break;
        path = decoded;
      } catch {
        break;
      }
    }

    return path;
  }

  function pathBelongsToEntry(path: string, entry: string): boolean {
    if (entry === SYSTEM_ENTRY) {
      return isSystemPath(path);
    }

    return getAuthEntryByRoutePath(path) === entry;
  }

  function isFallbackRoute(to: RouteLocationNormalized): boolean {
    return to.name === 'FallbackNotFound'
      || to.matched.some((route) => route.name === 'FallbackNotFound');
  }

  function shouldRebuildAccessRoutes(to: RouteLocationNormalized): boolean {
    if (to.meta.ignoreAccess || coreRouteNames.includes(to.name as string)) {
      return false;
    }
    if (!isFallbackRoute(to)) {
      return false;
    }

    const entry = getAuthEntry();
    return isPluginAuthEntry(entry)
      ? pathBelongsToEntry(to.path, entry)
      : isSystemPath(to.path);
  }

  function resetDynamicRoutesForRebuild() {
    const accessStore = useAccessStore();
    // 插件后台菜单可能在登录后同步或热更新；遇到已声明后台路径却命中 fallback 时，
    // 只清理动态路由与菜单生成状态，让守卫重建一次，避免菜单点击落到空白页。
    accessStore.setAccessMenus([]);
    accessStore.setAccessRoutes([]);
    accessStore.setIsAccessChecked(false);
  }

  function isSafeRedirectPath(path: string, entry: string): boolean {
    if (!path.startsWith('/') || path.startsWith('//')) {
      return false;
    }
    if (path === LOGIN_PATH || isAuthLoginPath(path) || path.startsWith('/auth/')) {
      return false;
    }

    // 登录完成后的 redirect 必须留在当前认证入口范围内；插件入口由 auth-entry.ts 声明路径边界。
    return pathBelongsToEntry(path, entry);
  }

  function resolvePostLoginPath(value: unknown, entry: string, fallback: string): string {
    const redirect = decodeRedirectValue(value);
    return isSafeRedirectPath(redirect, entry) ? redirect : fallback;
  }

  function filterEntryTree<T extends Record<string, any>>(nodes: T[], entry: string): T[] {
    return nodes
      .map((node) => {
        const rawChildren = Array.isArray(node.children)
          ? (node.children as T[])
          : [];
        const children = rawChildren.length > 0
          ? filterEntryTree(rawChildren, entry)
          : [];
        const selfInEntry = routeBelongsToAuthEntry(node, entry);

        // 插件用户端菜单由插件入口配置声明；动态生成后再次按入口边界过滤，
        // 避免切换账号时混入 System 或其他插件的菜单与路由。
        if (children.length === 0 && !(selfInEntry && rawChildren.length === 0)) {
          return null;
        }

        const { children: _originChildren, ...rest } = node;
        return {
          ...rest,
          ...(children.length > 0 ? { children } : {}),
        } as T;
      })
      .filter(Boolean) as T[];
  }

  async function refreshAccessCodesIfNeeded() {
    const accessStore = useAccessStore();
    const userInfo = useUserStore().userInfo;
    if (!accessStore.accessToken || accessStore.accessCodes.length > 0) {
      return;
    }
    if (!userInfo || !isUserInfoForAuthEntry(userInfo)) {
      return;
    }

    try {
      // 多认证入口共用一套路由守卫，但权限码接口按当前入口配置切换。
      const accessCodes = await coreAuthApiService.getAccessCodes();
      accessStore.setAccessCodes(Array.isArray(accessCodes) ? accessCodes : []);
    } catch (error) {
      console.warn('刷新权限码失败:', error);
      accessStore.setAccessCodes([]);
    }
  }

  function hasStableMenuOrder(menus: any[]): boolean {
    return menus.every((menu) => {
      const currentOk = typeof menu?.order === 'number';
      const childrenOk = Array.isArray(menu?.children)
        ? hasStableMenuOrder(menu.children)
        : true;
      return currentOk && childrenOk;
    });
  }

  function activateEntryForPath(path: string, userInfo: any) {
    const loginEntry = getLoginEntryByPath(path);
    const routeEntry = loginEntry || getAuthEntryByRoutePath(path);
    // 未命中插件前台入口的路径统一归到 System 入口，避免上一个 Project/Points/Asset 前台入口污染平台 404 或后台插件深链。
    const nextEntry = routeEntry || SYSTEM_ENTRY;

    activateAuthEntry(nextEntry);
    if (userInfo && !isUserInfoForAuthEntry(userInfo, nextEntry)) {
      useUserStore().setUserInfo(null);
      resetAccessRuntimeState();
    }
  }

  function ensureProfileRoute(router: Router, entry: string) {
    const route = createAccountProfileRoute(getAuthEntryConfig(entry));
    if (route.name && !router.hasRoute(route.name)) {
      router.addRoute('Root', route);
    }
  }

  router.beforeEach(async (to, from) => {
    const accessStore = useAccessStore();
    const userStore = useUserStore();
    const authStore = useAuthStore();
    activateEntryForPath(to.path, userStore.userInfo);

    // 基本路由，这些路由不需要进入权限拦截
    if (coreRouteNames.includes(to.name as string)) {
      const loginEntry = getLoginEntryByPath(to.path);
      const userEntry = getAuthEntryByUserInfo(userStore.userInfo);
      if (loginEntry && accessStore.accessToken && userEntry === loginEntry) {
        return resolvePostLoginPath(
          to.query?.redirect,
          loginEntry,
          userStore.userInfo?.homePath || getAuthHomePath(loginEntry),
        );
      }
      return true;
    }

    // accessToken 检查
    if (!accessStore.accessToken) {
      // 明确声明忽略权限访问权限，则可以访问
      if (to.meta.ignoreAccess) {
        return true;
      }

      const loginPath = getAuthLoginPath();
      if (to.fullPath !== loginPath) {
        return {
          path: loginPath,
          query:
            to.fullPath === preferences.app.defaultHomePath
              ? {}
              : { redirect: encodeURIComponent(to.fullPath) },
          replace: true,
        };
      }
      return to;
    }

    if (accessStore.isAccessChecked && !userStore.userInfo) {
      // 权限已生成但用户资料为空属于入口切换后的不完整状态，继续沿用旧权限会错误调用另一套入口的 codes/profile。
      resetAccessRuntimeState();
    }

    // 是否已经生成过动态路由
    if (accessStore.isAccessChecked) {
      await refreshAccessCodesIfNeeded();
      const entry = getAuthEntry();
      if (isPluginAuthEntry(entry)) {
        if (!pathBelongsToEntry(to.path, entry)) {
          return { path: getAuthHomePath(entry), replace: true };
        }
        accessStore.setAccessMenus(filterEntryTree(accessStore.accessMenus as any, entry) as any);
        accessStore.setAccessRoutes(filterEntryTree(accessStore.accessRoutes as any, entry) as any);
      }
      if (
        preferences.app.accessMode === 'backend'
        && accessStore.accessRoutes.length > 0
        && !hasStableMenuOrder(accessStore.accessMenus)
      ) {
        const menus = generateMenus(accessStore.accessRoutes, router);
        accessStore.setAccessMenus(isPluginAuthEntry(entry) ? filterEntryTree(menus as any, entry) as any : menus);
      }
      if (shouldRebuildAccessRoutes(to)) {
        if (!accessRouteRebuildPaths.has(to.fullPath)) {
          accessRouteRebuildPaths.add(to.fullPath);
          resetDynamicRoutesForRebuild();

          return {
            hash: to.hash,
            path: to.path,
            query: to.query,
            replace: true,
          };
        }

        accessRouteRebuildPaths.delete(to.fullPath);
        return true;
      }
      accessRouteRebuildPaths.delete(to.fullPath);
      return true;
    }

    // 生成路由表
    // 当前登录用户拥有的角色标识列表
    let userInfo = userStore.userInfo;
    if (!userInfo) {
      try {
        userInfo = await authStore.fetchUserInfo();
      } catch (error) {
        // 如果获取用户信息失败（如token过期），清理token并跳转到登录页
        accessStore.setAccessToken(null);
        return {
          path: getAuthLoginPath(),
          query: { redirect: encodeURIComponent(to.fullPath) },
          replace: true,
        };
      }
    }

    await refreshAccessCodesIfNeeded();
    const entry = getAuthEntryByUserInfo(userInfo) || getAuthEntry();
    const userRoles = userInfo?.roles ?? [];
    if (isPluginAuthEntry(entry) && !pathBelongsToEntry(to.path, entry)) {
      return { path: getAuthHomePath(entry), replace: true };
    }

    // 生成菜单和路由
    const { accessibleMenus, accessibleRoutes } = await generateAccess({
      roles: userRoles,
      router,
      routes: accessRoutes,
    });
    const finalMenus = isPluginAuthEntry(entry)
      ? filterEntryTree(accessibleMenus as any, entry) as typeof accessibleMenus
      : accessibleMenus;
    const finalRoutes = isPluginAuthEntry(entry)
      ? filterEntryTree(accessibleRoutes as any, entry) as typeof accessibleRoutes
      : accessibleRoutes;

    ensureProfileRoute(router, entry);
    if (isSystemAuthEntry(entry) && !router.getRoutes().some((route) => route.path === '/system/notice')) {
      router.addRoute('Root', systemNoticeRoute);
    }

    // 保存菜单信息和路由信息
    accessStore.setAccessMenus(finalMenus);
    accessStore.setAccessRoutes(finalRoutes);
    accessStore.setIsAccessChecked(true);
    const redirectedFromRoot = to.redirectedFrom?.path === '/';
    const fallbackPath = redirectedFromRoot && to.path === preferences.app.defaultHomePath
      ? userInfo.homePath || getAuthHomePath(entry)
      : to.fullPath;
    const redirectPath = resolvePostLoginPath(
      from.query.redirect,
      entry,
      fallbackPath,
    );

    return {
      ...router.resolve(redirectPath),
      replace: true,
    };
  });
}

/**
 * 项目守卫配置
 * @param router
 */
function createRouterGuard(router: Router) {
  /** 通用 */
  setupCommonGuard(router);
  /** 权限访问 */
  setupAccessGuard(router);
}

export { createRouterGuard };
