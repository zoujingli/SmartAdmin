import type { RouteRecordRaw } from 'vue-router';

import { describe, expect, it } from 'vitest';

import { generateMenus, generateRoutesByBackend } from '@vben/utils';

import { filterAuthEntryMenus } from '../auth-entry-menus';

const portalComponent = () => Promise.resolve({ default: {} });
const taskComponent = () => Promise.resolve({ default: {} });
const permissionComponent = () => Promise.resolve({ default: {} });
const forbiddenComponent = () => Promise.resolve({ default: {} });

function menu(name: string, path: string, code: string, component: string) {
  return {
    name,
    path,
    route: path,
    component,
    code,
    permission: code,
    meta: {
      title: name,
      typeCode: 'M',
    },
  };
}

const systemEntry = {
  authBase: '/system/auth',
  entry: 'system',
  homePath: '/dashboard',
  loginPath: '/auth/login',
  name: '系统后台',
  permissionPrefixes: ['system.'],
  profilePath: '/account/profile',
  routePrefixes: ['/system', '/dashboard'],
  userModel: 'System\\Model\\SystemUser',
  userModelIncludes: ['SystemUser'],
  menus: [
    {
      name: 'system_manage',
      path: '/system',
      route: '/system',
      component: '',
      code: '',
      permission: '',
      redirect: '/system/user',
      meta: {
        title: '系统管理',
        typeCode: 'D',
      },
      children: [
        menu(
          'system_user',
          '/system/user',
          'system.user.index',
          '@plugin/System/views/user/index.vue',
        ),
        menu(
          'system_role',
          '/system/role',
          'system.role.index',
          '@plugin/System/views/role/index.vue',
        ),
      ],
    },
    {
      name: 'system_ops',
      path: '/system/ops',
      route: '/system/ops',
      component: '',
      code: '',
      permission: '',
      redirect: '/system/ops/notice',
      meta: {
        title: '运维管理',
        typeCode: 'D',
      },
      children: [
        menu(
          'system_notice',
          '/system/ops/notice',
          'system.notice.index',
          '@plugin/System/views/notice/index.vue',
        ),
      ],
    },
  ],
};

function findRoute(rows: any[], path: string): any {
  for (const row of rows) {
    if (row.path === path || row.route === path) {
      return row;
    }
    if (Array.isArray(row.children)) {
      const matched = findRoute(row.children, path);
      if (matched) {
        return matched;
      }
    }
  }

  return undefined;
}

describe('filterAuthEntryMenus', () => {
  it('keeps System allowed nodes visible and declared denied nodes as hidden 403 routes', async () => {
    const menus = filterAuthEntryMenus(systemEntry.menus, ['system.user.index']) as any[];

    const manage = findRoute(menus, '/system');
    const user = findRoute(menus, '/system/user');
    const role = findRoute(menus, '/system/role');
    const ops = findRoute(menus, '/system/ops');
    const notice = findRoute(menus, '/system/ops/notice');

    expect(manage?.meta?.hideInMenu).not.toBe(true);
    expect(user?.meta?.hideInMenu).not.toBe(true);
    expect(user?.meta?.menuVisibleWithForbidden).toBeUndefined();

    expect(role?.meta).toMatchObject({
      hideInMenu: true,
      menuVisibleWithForbidden: true,
    });
    expect(notice?.meta).toMatchObject({
      hideInMenu: true,
      menuVisibleWithForbidden: true,
    });
    expect(ops?.meta?.hideInMenu).toBe(true);
    expect(findRoute(menus, '/system/not-exists')).toBeUndefined();
    expect(findRoute(systemEntry.menus, '/system/role')?.meta?.menuVisibleWithForbidden)
      .toBeUndefined();

    const routes = await generateRoutesByBackend({
      fetchMenuListAsync: async () => menus,
      forbiddenComponent,
      layoutMap: {},
      pageMap: {
        '@plugin/System/views/notice/index.vue': permissionComponent,
        '@plugin/System/views/role/index.vue': taskComponent,
        '@plugin/System/views/user/index.vue': portalComponent,
      },
    } as any);

    expect(findRoute(routes as RouteRecordRaw[], '/system/user')?.component)
      .toBe(portalComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/system/role')?.component)
      .toBe(forbiddenComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/system/ops/notice')?.component)
      .toBe(forbiddenComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/system/not-exists')).toBeUndefined();

    const sideMenus = generateMenus(routes as RouteRecordRaw[], { getRoutes: () => [] } as any);

    expect(findRoute(sideMenus as any[], '/system')).toBeTruthy();
    expect(findRoute(sideMenus as any[], '/system/user')).toBeTruthy();
    expect(findRoute(sideMenus as any[], '/system/role')).toBeUndefined();
    expect(findRoute(sideMenus as any[], '/system/ops/notice')).toBeUndefined();
  });

  it('keeps wildcard System permissions as normal visible routes', () => {
    const menus = filterAuthEntryMenus(systemEntry.menus, ['*']) as any[];

    expect(findRoute(menus, '/system/role')?.meta?.menuVisibleWithForbidden).toBeUndefined();
    expect(findRoute(menus, '/system/role')?.meta?.hideInMenu).not.toBe(true);
    expect(findRoute(menus, '/system/ops')?.meta?.hideInMenu).not.toBe(true);
  });
});
