import { describe, expect, it, vi } from 'vitest';

import { generateRoutesByBackend } from '../generate-routes-backend';

function findRoute(rows: any[], path: string): any {
  for (const row of rows) {
    if (row.path === path) {
      return row;
    }

    const matched = findRoute(row.children || [], path);
    if (matched) {
      return matched;
    }
  }

  return undefined;
}

describe('generateRoutesByBackend', () => {
  it('resolves plugin page aliases without falling back to not found', async () => {
    const dashboardComponent = vi.fn();
    const notFoundComponent = vi.fn();

    const routes = await generateRoutesByBackend({
      fetchMenuListAsync: async () => [
        {
          name: 'demo_dashboard',
          path: '/demo/dashboard',
          component: '@plugin/Demo/views/dashboard/index.vue',
          meta: { title: '插件看板' },
        },
      ],
      pageMap: {
        '../views/_core/fallback/not-found.vue': notFoundComponent,
        '@plugin/Demo/views/dashboard/index.vue': dashboardComponent,
      },
    } as any);

    expect(findRoute(routes, '/demo/dashboard')?.component).toBe(dashboardComponent);
    expect(findRoute(routes, '/demo/dashboard')?.component).not.toBe(notFoundComponent);
  });
});
