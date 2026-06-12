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

const demoEntry = {
  authBase: '/demo/auth',
  entry: 'demo',
  homePath: '/demo/portal',
  loginPath: '/demo/login',
  name: '演示模块',
  permissionPrefixes: ['demo.'],
  profilePath: '/demo/profile',
  routePrefixes: ['/demo'],
  userModel: 'Plugin\\Demo\\Model\\DemoUser',
  userModelIncludes: ['DemoUser'],
  menus: [
    {
      name: 'demo_manage',
      path: '/demo/manage',
      route: '/demo/manage',
      component: '',
      code: '',
      permission: '',
      redirect: '/demo/portal',
      meta: {
        title: '模块管理',
        typeCode: 'D',
      },
      children: [
        menu(
          'demo_portal',
          '/demo/portal',
          'demo.portal.index',
          '@plugin/Demo/views/portal/index.vue',
        ),
        menu(
          'demo_task',
          '/demo/task',
          'demo.task.index',
          '@plugin/Demo/views/task/index.vue',
        ),
      ],
    },
    {
      name: 'demo_tools',
      path: '/demo/tools',
      route: '/demo/tools',
      component: '',
      code: '',
      permission: '',
      redirect: '/demo/tools/permission',
      meta: {
        title: '模块工具',
        typeCode: 'D',
      },
      children: [
        menu(
          'demo_permission',
          '/demo/tools/permission',
          'demo.permission.index',
          '@plugin/Demo/views/permission/index.vue',
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
  it('keeps allowed nodes visible and declared denied nodes as hidden 403 routes', async () => {
    const menus = filterAuthEntryMenus(demoEntry.menus, ['demo.portal.index']) as any[];

    const manage = findRoute(menus, '/demo/manage');
    const portal = findRoute(menus, '/demo/portal');
    const task = findRoute(menus, '/demo/task');
    const tools = findRoute(menus, '/demo/tools');
    const permission = findRoute(menus, '/demo/tools/permission');

    expect(manage?.meta?.hideInMenu).not.toBe(true);
    expect(portal?.meta?.hideInMenu).not.toBe(true);
    expect(portal?.meta?.menuVisibleWithForbidden).toBeUndefined();

    expect(task?.meta).toMatchObject({
      hideInMenu: true,
      menuVisibleWithForbidden: true,
    });
    expect(permission?.meta).toMatchObject({
      hideInMenu: true,
      menuVisibleWithForbidden: true,
    });
    expect(tools?.meta?.hideInMenu).toBe(true);
    expect(findRoute(menus, '/demo/not-exists')).toBeUndefined();
    expect(findRoute(demoEntry.menus, '/demo/task')?.meta?.menuVisibleWithForbidden)
      .toBeUndefined();

    const routes = await generateRoutesByBackend({
      fetchMenuListAsync: async () => menus,
      forbiddenComponent,
      layoutMap: {},
      pageMap: {
        '@plugin/Demo/views/permission/index.vue': permissionComponent,
        '@plugin/Demo/views/portal/index.vue': portalComponent,
        '@plugin/Demo/views/task/index.vue': taskComponent,
      },
    } as any);

    expect(findRoute(routes as RouteRecordRaw[], '/demo/portal')?.component)
      .toBe(portalComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/demo/task')?.component)
      .toBe(forbiddenComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/demo/tools/permission')?.component)
      .toBe(forbiddenComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/demo/not-exists')).toBeUndefined();

    const sideMenus = generateMenus(routes as RouteRecordRaw[], { getRoutes: () => [] } as any);

    expect(findRoute(sideMenus as any[], '/demo/manage')).toBeTruthy();
    expect(findRoute(sideMenus as any[], '/demo/portal')).toBeTruthy();
    expect(findRoute(sideMenus as any[], '/demo/task')).toBeUndefined();
    expect(findRoute(sideMenus as any[], '/demo/tools/permission')).toBeUndefined();
  });

  it('keeps wildcard permissions as normal visible routes', () => {
    const menus = filterAuthEntryMenus(demoEntry.menus, ['*']) as any[];

    expect(findRoute(menus, '/demo/task')?.meta?.menuVisibleWithForbidden).toBeUndefined();
    expect(findRoute(menus, '/demo/task')?.meta?.hideInMenu).not.toBe(true);
    expect(findRoute(menus, '/demo/tools')?.meta?.hideInMenu).not.toBe(true);
  });
});
