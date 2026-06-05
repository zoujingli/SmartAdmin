import type { RouteLocationNormalized } from 'vue-router';

import { describe, expect, it } from 'vitest';

import { routeReentry, shouldRebuildAccessRoutes } from '../guard-routes';

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
      path: '/system/material/region',
    }), {
      coreRouteNames,
      isEntryPath: (path) => path === '/system/material/region',
    });

    expect(result).toBe(true);
  });

  it('rebuilds routes when fallback is found in matched records', () => {
    const result = shouldRebuildAccessRoutes(route({
      matchedNames: ['Root', 'FallbackNotFound'],
      name: 'Root',
      path: '/system/material/region',
    }), {
      coreRouteNames: [],
      isEntryPath: (path) => path.startsWith('/system/material'),
    });

    expect(result).toBe(true);
  });

  it('does not rebuild core or ignored routes', () => {
    expect(shouldRebuildAccessRoutes(route({
      name: 'Login',
      path: '/auth/login',
    }), {
      coreRouteNames,
      isEntryPath: () => true,
    })).toBe(false);

    expect(shouldRebuildAccessRoutes(route({
      ignoreAccess: true,
      name: 'FallbackNotFound',
      path: '/system/material/region',
    }), {
      coreRouteNames,
      isEntryPath: () => true,
    })).toBe(false);
  });

  it('does not rebuild true unknown paths outside the current entry', () => {
    const result = shouldRebuildAccessRoutes(route({
      name: 'FallbackNotFound',
      path: '/unknown/not-exists',
    }), {
      coreRouteNames,
      isEntryPath: (path) => path.startsWith('/system/'),
    });

    expect(result).toBe(false);
  });

  it('re-enters fallback paths without carrying stale matched or meta snapshots', () => {
    const result = routeReentry({
      hash: '#section',
      path: '/system/material/region',
      query: { tab: 'data' },
    });

    expect(result).toEqual({
      hash: '#section',
      path: '/system/material/region',
      query: { tab: 'data' },
      replace: true,
    });
    expect(result).not.toHaveProperty('matched');
    expect(result).not.toHaveProperty('meta');
  });
});
