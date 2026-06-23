import type { RouteRecordNormalized } from 'vue-router';

import { useRouter } from 'vue-router';

import { isHttpUrl, openRouteInNewWindow, openWindow } from '@vben/utils';

function useNavigation() {
  const router = useRouter();
  const routeMetaMap = new Map<string, RouteRecordNormalized>();

  // 初始化路由映射
  const initRouteMetaMap = () => {
    const routes = router.getRoutes();
    routeMetaMap.clear();
    routes.forEach((route) => {
      routeMetaMap.set(route.path, route);
    });
  };

  initRouteMetaMap();

  // 监听路由变化
  router.afterEach(() => {
    initRouteMetaMap();
  });

  // 检查是否应该在新窗口打开
  const shouldOpenInNewWindow = (path: string): boolean => {
    if (isHttpUrl(path)) {
      return true;
    }
    const route = resolveStableRoute(path);
    // 如果有外链或者设置了在新窗口打开，返回 true
    return !!(route?.meta?.link || route?.meta?.openInNewWindow);
  };

  const resolveHref = (path: string): string => {
    return router.resolve(path).href;
  };

  function isFallbackRoute(path: string) {
    const resolved = router.resolve(path);

    return resolved.name === 'FallbackNotFound'
      || resolved.matched.some((route) => route.name === 'FallbackNotFound');
  }

  function resolveStableRoute(path: string) {
    // 插件菜单可能先于后端动态路由注册完成；此时 router.resolve 会命中全局 404。
    // 不能把 fallback 的 meta 当作真实页面配置，否则菜单首跳会打开 tab 但 RouterView 留空。
    return isFallbackRoute(path) ? undefined : routeMetaMap.get(path);
  }

  const navigation = async (path: string) => {
    try {
      if (isHttpUrl(path)) {
        openWindow(path, { target: '_blank' });
        return;
      }

      const route = resolveStableRoute(path);
      const { openInNewWindow = false, query = {}, link } = route?.meta ?? {};

      // 检查是否有外链
      if (link && typeof link === 'string') {
        openWindow(link, { target: '_blank' });
        return;
      }

      if (openInNewWindow) {
        openRouteInNewWindow(resolveHref(path));
      } else {
        await router.push({
          path,
          query,
        });
      }
    } catch (error) {
      console.error('Navigation failed:', error);
      throw error;
    }
  };

  const willOpenedByWindow = (path: string) => {
    return shouldOpenInNewWindow(path);
  };

  return { navigation, willOpenedByWindow };
}

export { useNavigation };
