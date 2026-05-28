import type { RouteRecordStringComponent } from '@vben/types';

interface FilteredMenuRow {
  row: RouteRecordStringComponent;
  visible: boolean;
}

/**
 * 插件前台认证入口的菜单与路由裁剪。
 *
 * 已授权节点正常显示；未授权但已在 auth-entry.ts 声明的节点保留为隐藏 403 路由，
 * 避免用户直接访问真实业务地址时落入全局 404，同时不把无权限节点展示到侧边栏。
 */
export function filterAuthEntryMenus(
  rows: RouteRecordStringComponent[],
  accessCodes: unknown[],
): RouteRecordStringComponent[] {
  const codes = accessCodes.map(String);
  if (codes.includes('*')) {
    return cloneMenus(rows);
  }

  return cloneMenus(filterMenus(rows, new Set(codes)));
}

function filterMenus(
  rows: RouteRecordStringComponent[],
  allowed: Set<string>,
): RouteRecordStringComponent[] {
  return filterMenuRows(rows, allowed).map(item => item.row);
}

function filterMenuRows(
  rows: RouteRecordStringComponent[],
  allowed: Set<string>,
): FilteredMenuRow[] {
  return rows
    .map((row: any) => {
      const children = Array.isArray(row.children) ? filterMenuRows(row.children, allowed) : [];
      const code = String(row.code || row.permission || '').trim();
      const keepSelf = !!code && allowed.has(code);
      const forbiddenSelf = !!code && !keepSelf;
      if (children.length === 0 && !keepSelf && !forbiddenSelf) {
        return null;
      }

      const meta = row.meta ? { ...row.meta } : {};
      const hasVisibleChildren = children.some(child => child.visible);
      const visibleSelf = keepSelf && meta.hideInMenu !== true;
      if (forbiddenSelf) {
        // 无权限节点仍生成隐藏路由，后续由 forbiddenComponent 替换为 403 页面。
        meta.hideInMenu = true;
        meta.menuVisibleWithForbidden = true;
      }
      if (!visibleSelf && !hasVisibleChildren) {
        // 目录只包含隐藏 403 子路由时不应展示为空菜单分组。
        meta.hideInMenu = true;
      }

      const filteredRow = {
        ...row,
        meta,
        ...(children.length > 0 ? { children: children.map(child => child.row) } : {}),
      } as RouteRecordStringComponent;

      return {
        row: filteredRow,
        visible: meta.hideInMenu !== true && (visibleSelf || hasVisibleChildren),
      };
    })
    .filter(Boolean) as FilteredMenuRow[];
}

function cloneMenus(rows: RouteRecordStringComponent[]): RouteRecordStringComponent[] {
  // 动态路由生成阶段会把 component 字符串替换成真实组件；每次返回新对象，避免污染插件配置模板。
  return rows.map((row: any) => {
    const children = Array.isArray(row.children) && row.children.length > 0
      ? cloneMenus(row.children)
      : undefined;

    return {
      ...row,
      meta: row.meta ? { ...row.meta } : row.meta,
      ...(children ? { children } : {}),
    };
  });
}
