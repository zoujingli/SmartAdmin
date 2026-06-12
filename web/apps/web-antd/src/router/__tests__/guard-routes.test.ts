import type { RouteLocationNormalized } from 'vue-router';

import { describe, expect, it } from 'vitest';

import { routeReentry, shouldPreserveModuleGuideHistory, shouldRebuildAccessRoutes } from '../guard-routes';

function route(value: {
  ignoreAccess?: boolean;
  matchedNames?: string[];
  name?: string;
  path: string;
}) {
  return {
    matched: (value.matchedNames || []).map((name) => ({ name })),
    meta: { ignoreAccess: value.ignoreAccess },
    name: value.name,
    path: value.path,
  } as Pick<RouteLocationNormalized, 'matched' | 'meta' | 'name' | 'path'>;
}

describe('shouldRebuildAccessRoutes', () => {
  const coreRouteNames = ['Login', 'Root'];

  it('rebuilds backend plugin fallback routes that still belong to the current entry', () => {
    const result = shouldRebuildAccessRoutes(route({
      name: 'FallbackNotFound',
      path: '/admin/material/region',
    }), {
      coreRouteNames,
      isEntryPath: (path) => path === '/admin/material/region',
      // 后台插件入口来自 plugin.json apps，首跳可能早于后端菜单动态路由生成。
      isKnownAccessPath: (path) => path === '/admin/material/region',
    });

    expect(result).toBe(true);
  });

  it('rebuilds routes when fallback is found in matched records', () => {
    const result = shouldRebuildAccessRoutes(route({
      matchedNames: ['Root', 'FallbackNotFound'],
      name: 'Root',
      path: '/admin/material/region',
    }), {
      coreRouteNames: [],
      isEntryPath: (path) => path.startsWith('/admin/material'),
      isKnownAccessPath: (path) => path === '/admin/material/region',
    });

    expect(result).toBe(true);
  });

  it('does not rebuild core or ignored routes', () => {
    expect(shouldRebuildAccessRoutes(route({
      name: 'Login',
      path: '/demo/login',
    }), {
      coreRouteNames,
      isEntryPath: () => true,
      isKnownAccessPath: () => true,
    })).toBe(false);

    expect(shouldRebuildAccessRoutes(route({
      ignoreAccess: true,
      name: 'FallbackNotFound',
      path: '/admin/material/region',
    }), {
      coreRouteNames,
      isEntryPath: () => true,
      isKnownAccessPath: () => true,
    })).toBe(false);
  });

  it('does not rebuild true unknown paths outside the current entry', () => {
    const result = shouldRebuildAccessRoutes(route({
      name: 'FallbackNotFound',
      path: '/unknown/not-exists',
    }), {
      coreRouteNames,
      isEntryPath: (path) => path.startsWith('/admin/'),
      isKnownAccessPath: () => false,
    });

    expect(result).toBe(false);
  });

  it('does not rebuild deprecated paths that only match an auth-entry prefix', () => {
    const result = shouldRebuildAccessRoutes(route({
      name: 'FallbackNotFound',
      path: '/admin/demo/deprecated',
    }), {
      coreRouteNames,
      isEntryPath: (path) => path.startsWith('/admin/demo'),
      isKnownAccessPath: (path) => path === '/admin/demo/workspace',
    });

    expect(result).toBe(false);
  });

  it('re-enters fallback paths without carrying stale matched or meta snapshots', () => {
    const result = routeReentry({
      hash: '#section',
      path: '/admin/material/region',
      query: { tab: 'data' },
    });

    expect(result).toEqual({
      hash: '#section',
      path: '/admin/material/region',
      query: { tab: 'data' },
      replace: true,
    });
    expect(result).not.toHaveProperty('matched');
    expect(result).not.toHaveProperty('meta');
  });

  it('preserves module guide history when entering from /entry', () => {
    expect(shouldPreserveModuleGuideHistory({ path: '/entry' } as RouteLocationNormalized)).toBe(true);
    expect(shouldPreserveModuleGuideHistory({ path: '/entry?from=card' } as RouteLocationNormalized)).toBe(true);
    expect(shouldPreserveModuleGuideHistory({ path: '/project/portal' } as RouteLocationNormalized)).toBe(false);

    expect(routeReentry({
      hash: '',
      path: '/project/portal',
      query: {},
    }, { replace: false })).toEqual({
      hash: '',
      path: '/project/portal',
      query: {},
      replace: false,
    });
  });
});
