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

const projectEntry = {
  authBase: '/project/account/auth',
  entry: 'project',
  homePath: '/project/portal',
  loginPath: '/project/login',
  name: '项目管理',
  permissionPrefixes: ['project.'],
  profilePath: '/project/profile',
  routePrefixes: ['/project'],
  userModel: 'Plugin\\Project\\Model\\ProjectAccount',
  userModelIncludes: ['ProjectAccount'],
  menus: [
    {
      name: 'project_work',
      path: '/project/work',
      route: '/project/work',
      component: '',
      code: '',
      permission: '',
      redirect: '/project/portal',
      meta: {
        title: '工作协同',
        typeCode: 'D',
      },
      children: [
        menu(
          'project_portal',
          '/project/portal',
          'project.portal.index',
          '@plugin/Project/views/portal/index.vue',
        ),
        menu(
          'project_task',
          '/project/task',
          'project.task.index',
          '@plugin/Project/views/task/index.vue',
        ),
      ],
    },
    {
      name: 'project_parameter',
      path: '/project/parameter',
      route: '/project/parameter',
      component: '',
      code: '',
      permission: '',
      redirect: '/project/parameter/permission',
      meta: {
        title: '参数配置',
        typeCode: 'D',
      },
      children: [
        menu(
          'project_permission',
          '/project/parameter/permission',
          'project.account.role-permission',
          '@plugin/Project/views/permission/index.vue',
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
  it('keeps Project allowed nodes visible and declared denied nodes as hidden 403 routes', async () => {
    const menus = filterAuthEntryMenus(projectEntry.menus, ['project.portal.index']) as any[];

    const work = findRoute(menus, '/project/work');
    const portal = findRoute(menus, '/project/portal');
    const task = findRoute(menus, '/project/task');
    const parameter = findRoute(menus, '/project/parameter');
    const permission = findRoute(menus, '/project/parameter/permission');

    expect(work?.meta?.hideInMenu).not.toBe(true);
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
    expect(parameter?.meta?.hideInMenu).toBe(true);
    expect(findRoute(menus, '/project/not-exists')).toBeUndefined();
    expect(findRoute(projectEntry.menus, '/project/task')?.meta?.menuVisibleWithForbidden)
      .toBeUndefined();

    const routes = await generateRoutesByBackend({
      fetchMenuListAsync: async () => menus,
      forbiddenComponent,
      layoutMap: {},
      pageMap: {
        '@plugin/Project/views/portal/index.vue': portalComponent,
        '@plugin/Project/views/task/index.vue': taskComponent,
        '@plugin/Project/views/permission/index.vue': permissionComponent,
      },
    } as any);

    expect(findRoute(routes as RouteRecordRaw[], '/project/portal')?.component)
      .toBe(portalComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/project/task')?.component)
      .toBe(forbiddenComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/project/parameter/permission')?.component)
      .toBe(forbiddenComponent);
    expect(findRoute(routes as RouteRecordRaw[], '/project/not-exists')).toBeUndefined();

    const sideMenus = generateMenus(routes as RouteRecordRaw[], { getRoutes: () => [] } as any);

    expect(findRoute(sideMenus as any[], '/project/work')).toBeTruthy();
    expect(findRoute(sideMenus as any[], '/project/portal')).toBeTruthy();
    expect(findRoute(sideMenus as any[], '/project/task')).toBeUndefined();
    expect(findRoute(sideMenus as any[], '/project/parameter/permission')).toBeUndefined();
  });

  it('keeps wildcard Project permissions as normal visible routes', () => {
    const menus = filterAuthEntryMenus(projectEntry.menus, ['*']) as any[];

    expect(findRoute(menus, '/project/task')?.meta?.menuVisibleWithForbidden).toBeUndefined();
    expect(findRoute(menus, '/project/task')?.meta?.hideInMenu).not.toBe(true);
    expect(findRoute(menus, '/project/parameter')?.meta?.hideInMenu).not.toBe(true);
  });
});
