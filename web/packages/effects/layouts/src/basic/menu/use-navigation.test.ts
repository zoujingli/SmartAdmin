import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useNavigation } from './use-navigation';

const push = vi.fn();
const afterEach = vi.fn();
const getRoutes = vi.fn();
const resolve = vi.fn();
const openRouteInNewWindow = vi.fn();
const openWindow = vi.fn();

vi.mock('vue-router', () => ({
  useRouter: () => ({
    afterEach,
    getRoutes,
    push,
    resolve,
  }),
}));

vi.mock('@vben/utils', () => ({
  isHttpUrl: (path: string) => /^https?:\/\//.test(path),
  openRouteInNewWindow: (...args: unknown[]) => openRouteInNewWindow(...args),
  openWindow: (...args: unknown[]) => openWindow(...args),
}));

function fallbackResolved(path: string) {
  return {
    href: path,
    matched: [{ name: 'FallbackNotFound' }],
    name: 'FallbackNotFound',
  };
}

function routeResolved(path: string) {
  return {
    href: `/#${path}`,
    matched: [{ name: 'Root' }, { name: path.replaceAll('/', '_') }],
    name: path.replaceAll('/', '_'),
  };
}

describe('useNavigation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getRoutes.mockReturnValue([]);
    resolve.mockImplementation((path: string) => routeResolved(path));
  });

  it('pushes dynamic plugin paths when current router resolve still points to fallback', async () => {
    getRoutes.mockReturnValue([
      {
        meta: {
          link: 'https://stale.example.test',
          openInNewWindow: true,
          query: { stale: '1' },
        },
        path: '/system/material/sync-log',
      },
    ]);
    resolve.mockImplementation((path: string) => fallbackResolved(path));

    const { navigation, willOpenedByWindow } = useNavigation();

    expect(willOpenedByWindow('/system/material/sync-log')).toBe(false);
    await navigation('/system/material/sync-log');

    expect(push).toHaveBeenCalledWith({
      path: '/system/material/sync-log',
      query: {},
    });
    expect(openWindow).not.toHaveBeenCalled();
    expect(openRouteInNewWindow).not.toHaveBeenCalled();
  });

  it('keeps resolved route query and new-window metadata when route is stable', async () => {
    getRoutes.mockReturnValue([
      {
        meta: {
          query: { tab: 'logs' },
        },
        path: '/system/logs/action',
      },
      {
        meta: {
          openInNewWindow: true,
        },
        path: '/system/report',
      },
    ]);

    const { navigation, willOpenedByWindow } = useNavigation();

    await navigation('/system/logs/action');
    expect(push).toHaveBeenCalledWith({
      path: '/system/logs/action',
      query: { tab: 'logs' },
    });

    expect(willOpenedByWindow('/system/report')).toBe(true);
    await navigation('/system/report');
    expect(openRouteInNewWindow).toHaveBeenCalledWith('/#/system/report');
  });

  it('clears stale route metadata after router updates', async () => {
    let afterEachHandler: undefined | (() => void);
    afterEach.mockImplementation((handler: () => void) => {
      afterEachHandler = handler;
    });
    getRoutes.mockReturnValueOnce([
      {
        meta: {
          link: 'https://old.example.test',
        },
        path: '/system/plugin/page',
      },
    ]);

    const { navigation } = useNavigation();
    getRoutes.mockReturnValue([]);
    afterEachHandler?.();

    await navigation('/system/plugin/page');

    expect(push).toHaveBeenCalledWith({
      path: '/system/plugin/page',
      query: {},
    });
    expect(openWindow).not.toHaveBeenCalled();
  });

  it('keeps external link behavior', async () => {
    const { navigation, willOpenedByWindow } = useNavigation();

    expect(willOpenedByWindow('https://example.test/docs')).toBe(true);
    await navigation('https://example.test/docs');

    expect(openWindow).toHaveBeenCalledWith('https://example.test/docs', { target: '_blank' });
    expect(push).not.toHaveBeenCalled();
  });
});
