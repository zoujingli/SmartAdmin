import { describe, expect, it } from 'vitest';

import { resolveFallbackHomePath } from '../fallback-home';

const backendHomes = [
  { routePrefix: '/system/smart', homePath: '/system/smart/config' },
  { routePrefix: '/system/asset', homePath: '/system/asset/dashboard' },
  { routePrefix: '/system/project', homePath: '/system/project/portal' },
  { routePrefix: '/wechat/client', homePath: '/wechat/client/account' },
];

const authEntries = [
  { entry: 'project', routePrefixes: ['/project'], homePath: '/project/portal' },
  { entry: 'points', routePrefixes: ['/points'], homePath: '/points/portal' },
  { entry: 'asset', routePrefixes: ['/asset'], homePath: '/asset/self' },
  { entry: 'system', routePrefixes: ['/system', '/dashboard'], homePath: '/dashboard' },
];

function resolve(path: string, extraBackendHomes = backendHomes) {
  return resolveFallbackHomePath(path, {
    authEntries,
    backendHomes: extraBackendHomes,
    defaultHomePath: '/dashboard/workspace',
  });
}

describe('resolveFallbackHomePath', () => {
  it('routes Smart backend fallback pages to Smart plugin home', () => {
    expect(resolve('/system/smart/not-exists')).toBe('/system/smart/config');
  });

  it('routes Asset backend fallback pages to Asset plugin home', () => {
    expect(resolve('/system/asset/not-exists')).toBe('/system/asset/dashboard');
  });

  it('routes Project backend fallback pages to Project plugin home', () => {
    expect(resolve('/system/project/not-exists')).toBe('/system/project/portal');
  });

  it('routes backend plugin pages outside /system to their plugin home', () => {
    expect(resolve('/wechat/client/not-exists')).toBe('/wechat/client/account');
  });

  it('routes Project frontend fallback pages to Project frontend home', () => {
    expect(resolve('/project/not-exists')).toBe('/project/portal');
  });

  it('falls back to platform default home when no plugin context matches', () => {
    expect(resolve('/unknown/not-exists')).toBe('/dashboard/workspace');
  });

  it('uses longest matched prefix and avoids similar-prefix false positives', () => {
    expect(resolve('/system/project/report/not-exists', [
      ...backendHomes,
      { routePrefix: '/system/project/report', homePath: '/system/project/report' },
    ])).toBe('/system/project/report');
    expect(resolve('/system/smartx/not-exists')).toBe('/dashboard/workspace');
  });

  it('normalizes route query, hash and trailing slash before matching', () => {
    expect(resolve('/system/smart/not-exists?from=test#hash')).toBe('/system/smart/config');
    expect(resolve('/project/not-exists/')).toBe('/project/portal');
  });
});
