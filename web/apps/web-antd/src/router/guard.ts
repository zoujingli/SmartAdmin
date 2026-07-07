import type { RouteLocationNormalized, RouteLocationRaw, Router } from 'vue-router';

import backendPluginHomes from 'virtual:xadmin-plugin-backend-homes';

import { preferences } from '@vben/preferences';
import { useAccessStore, useUserStore } from '@vben/stores';
import { generateMenus, startProgress, stopProgress } from '@vben/utils';

import {
  activateAuthEntry,
  coreAuthApiService,
  getAuthEntry,
  getAuthEntryConfigs,
  getAuthEntryByRoutePath,
  getAuthEntryConfig,
  getAuthEntryByUserInfo,
  getAuthHomePath,
  getAuthLoginPath,
  getDefaultAuthEntry,
  getLoginEntryByPath,
  isAuthLoginPath,
  isSecondaryAuthEntry,
  isUserInfoForAuthEntry,
  routeBelongsToAuthEntry,
} from '#/api';
import { accessRoutes, coreRouteNames } from '#/router/routes';
import { createAccountProfileRoute } from '#/router/routes/static-account';
import { useAuthStore } from '#/store';

import { generateAccess } from './access';
import {
  isProjectFlowProgressDemoRoute,
  routeReentry,
  shouldPreserveModuleGuideHistory,
  shouldRebuildAccessRoutes as shouldRebuildAccessRoutesForEntry,
} from './guard-routes';

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

  function isSecondaryAuthEntryPath(path?: string): boolean {
    const normalized = normalizeGuardPath(path);

    // 独立插件入口由各插件 auth-entry.ts 声明；它们没有后台 plugin.json apps 菜单入口时，
    // 首跳 fallback 也要允许触发一次动态路由重建，覆盖未来新增插件入口。
    return getAuthEntryConfigs()
      .filter((entry) => isSecondaryAuthEntry(entry.entry))
      .some((entry) => entry.routePrefixes.some((prefix) => (
        pathMatchesPrefix(normalized, normalizeGuardPath(prefix))
      )));
  }

  function pathMatchesEntry(path: string, entry: string): boolean {
    const normalized = normalizeGuardPath(path);
    const config = getAuthEntryConfig(entry);

    if (normalized === '/' || config.loginPath === normalized || config.profilePath === normalized) {
      return true;
    }

    return config.routePrefixes.some((prefix) => pathMatchesPrefix(normalized, normalizeGuardPath(prefix)))
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
    return pathMatchesEntry(path, entry);
  }

  function treeHasPath(nodes: any[], path: string): boolean {
    const normalized = normalizeGuardPath(path);

    return nodes.some((node) => {
      const routePath = normalizeGuardPath(node?.path || node?.route);
      const redirectPath = normalizeGuardPath(node?.redirect);
      const matched = routePath === normalized || redirectPath === normalized;

      return matched || (Array.isArray(node?.children) && treeHasPath(node.children, normalized));
    });
  }

  function isKnownAccessPath(path: string): boolean {
    const accessStore = useAccessStore();

    return treeHasPath(accessStore.accessMenus as any, path)
      || treeHasPath(accessStore.accessRoutes as any, path)
      || treeHasPath(accessRoutes as any, path)
      || isBackendPluginPath(path)
      || isSecondaryAuthEntryPath(path);
  }

  function shouldRebuildAccessRoutes(to: RouteLocationNormalized): boolean {
    const entry = getAuthEntry();
    return shouldRebuildAccessRoutesForEntry(to, {
      coreRouteNames,
      isEntryPath: (path) => pathBelongsToEntry(path, entry),
      isKnownAccessPath,
    });
  }

  function resetDynamicRoutesForRebuild() {
    const accessStore = useAccessStore();
    // 插件后台菜单可能在登录后同步或热更新；遇到已声明后台路径却命中 fallback 时，
    // 只清理动态路由与菜单生成状态，让守卫重建一次，避免菜单点击落到空白页。
    accessStore.setAccessMenus([]);
    accessStore.setAccessRoutes([]);
    accessStore.setIsAccessChecked(false);
  }

  async function refreshAccessCodes(force = false) {
    const accessStore = useAccessStore();
    const userInfo = useUserStore().userInfo;
    if (!accessStore.accessToken || (!force && accessStore.accessCodes.length > 0)) {
      return;
    }
    if (!userInfo || !isUserInfoForAuthEntry(userInfo)) {
      return;
    }

    try {
      // 新增前台菜单权限后，旧页面会话可能还持有过期 accessCodes；强制刷新用于从隐藏 403 路由恢复。
      const accessCodes = await coreAuthApiService.getAccessCodes();
      accessStore.setAccessCodes(Array.isArray(accessCodes) ? accessCodes : []);
    } catch (error) {
      console.warn('刷新权限码失败:', error);
      accessStore.setAccessCodes([]);
    }
  }

  function replaceByResolvedPath(target: string, replace = true): RouteLocationRaw {
    const resolved = router.resolve(target);

    // 重新进入当前地址时只返回原始路由字段，不能把 router.resolve() 的 matched/meta 快照带回守卫；
    // 动态路由刚重建时携带旧匹配结果会让 RouterView 继续渲染 fallback，出现首次点击空白、刷新才正常。
    return routeReentry(resolved, { replace });
  }

  function isSafeRedirectPath(path: string, entry: string): boolean {
    if (!path.startsWith('/') || path.startsWith('//')) {
      return false;
    }
    if (isAuthLoginPath(path)) {
      return false;
    }
    if (['/403', '/404', '/500'].includes(path) || path.startsWith('/_core/fallback')) {
      return false;
    }
    const resolved = router.resolve(path);
    if (['Fallback403', 'Fallback404', 'Fallback500', 'FallbackNotFound'].includes(String(resolved.name || ''))) {
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
        // 避免切换账号时混入其他认证入口的菜单与路由。
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
    await refreshAccessCodes(false);
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
    // 未命中具体入口的路径回到默认认证入口，避免上一个前台入口污染公共 404 或后台插件深链。
    const nextEntry = routeEntry || getDefaultAuthEntry().entry;

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

    if (isProjectFlowProgressDemoRoute(to)) {
      activateAuthEntry(getDefaultAuthEntry().entry);

      return true;
    }

    activateEntryForPath(to.path, userStore.userInfo);

    // 基本路由，这些路由不需要进入权限拦截
    if (coreRouteNames.includes(to.name as string)) {
      if (to.meta.requireAuth && !accessStore.accessToken) {
        const loginPath = getAuthLoginPath();
        return {
          path: loginPath,
          query: { redirect: encodeURIComponent(to.fullPath) },
          replace: true,
        };
      }
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
          // 从模块入口页点击进入未登录业务时，保留 /entry 历史，允许浏览器返回重新选择入口。
          replace: !shouldPreserveModuleGuideHistory(from),
        };
      }
      return to;
    }

    if (accessStore.isAccessChecked && !userStore.userInfo) {
      // 权限已生成但用户资料为空属于入口切换后的不完整状态，继续沿用旧权限会错误调用另一套入口的 codes/profile。
      resetAccessRuntimeState();
    }

    // 是否已经生成过动态路由
    let shouldForceRebuildAccess = false;
    if (accessStore.isAccessChecked) {
      await refreshAccessCodesIfNeeded();
      const entry = getAuthEntry();
      if (isSecondaryAuthEntry(entry)) {
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
        accessStore.setAccessMenus(isSecondaryAuthEntry(entry) ? filterEntryTree(menus as any, entry) as any : menus);
      }
      if (shouldRebuildAccessRoutes(to)) {
        if (!accessRouteRebuildPaths.has(to.fullPath)) {
          accessRouteRebuildPaths.add(to.fullPath);
          // 当前跳转已经命中 fallback 时，立即在本轮守卫内重建动态路由；
          // 只返回同一路径让下一轮再生成，部分菜单首跳会停留在空白 fallback，刷新后才正常。
          resetDynamicRoutesForRebuild();
          shouldForceRebuildAccess = true;
        } else {
          accessRouteRebuildPaths.delete(to.fullPath);
          return true;
        }
      }
      if (to.meta.menuVisibleWithForbidden === true && !accessRouteRebuildPaths.has(to.fullPath)) {
        accessRouteRebuildPaths.add(to.fullPath);
        await refreshAccessCodes(true);
        resetDynamicRoutesForRebuild();
        shouldForceRebuildAccess = true;
      }
      if (!shouldForceRebuildAccess) {
        accessRouteRebuildPaths.delete(to.fullPath);
        return true;
      }
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
    if (isSecondaryAuthEntry(entry) && !pathBelongsToEntry(to.path, entry)) {
      return { path: getAuthHomePath(entry), replace: true };
    }

    // 生成菜单和路由
    const { accessibleMenus, accessibleRoutes } = await generateAccess({
      roles: userRoles,
      router,
      routes: accessRoutes,
    });
    const finalMenus = isSecondaryAuthEntry(entry)
      ? filterEntryTree(accessibleMenus as any, entry) as typeof accessibleMenus
      : accessibleMenus;
    const finalRoutes = isSecondaryAuthEntry(entry)
      ? filterEntryTree(accessibleRoutes as any, entry) as typeof accessibleRoutes
      : accessibleRoutes;

    ensureProfileRoute(router, entry);

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

    return replaceByResolvedPath(redirectPath, !shouldPreserveModuleGuideHistory(from));
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
