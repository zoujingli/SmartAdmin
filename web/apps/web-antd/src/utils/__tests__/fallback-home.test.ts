import { describe, expect, it } from 'vitest';

import { resolveFallbackHomePath } from '../fallback-home';

const backendHomes = [
  { routePrefix: '/admin/smart', homePath: '/admin/smart/config' },
  { routePrefix: '/admin/asset', homePath: '/admin/asset/dashboard' },
  { routePrefix: '/admin/project', homePath: '/admin/project/portal' },
  { routePrefix: '/wechat/client', homePath: '/wechat/client/account' },
];

const authEntries = [
  { entry: 'project', routePrefixes: ['/project'], homePath: '/project/portal' },
  { entry: 'points', routePrefixes: ['/points'], homePath: '/points/portal' },
  { entry: 'asset', routePrefixes: ['/asset'], homePath: '/asset/self' },
  { entry: 'default', routePrefixes: ['/admin', '/dashboard'], homePath: '/dashboard' },
];

function resolve(path: string, extraBackendHomes = backendHomes) {
  return resolveFallbackHomePath(path, {
    authEntries,
    backendHomes: extraBackendHomes,
    defaultHomePath: '/dashboard',
  });
}

describe('resolveFallbackHomePath', () => {
  it('routes Smart backend fallback pages to Smart plugin home', () => {
    expect(resolve('/admin/smart/not-exists')).toBe('/admin/smart/config');
  });

  it('routes Asset backend fallback pages to Asset plugin home', () => {
    expect(resolve('/admin/asset/not-exists')).toBe('/admin/asset/dashboard');
  });

  it('routes Project backend fallback pages to Project plugin home', () => {
    expect(resolve('/admin/project/not-exists')).toBe('/admin/project/portal');
  });

  it('routes backend plugin pages outside the default backend prefix to their plugin home', () => {
    expect(resolve('/wechat/client/not-exists')).toBe('/wechat/client/account');
  });

  it('routes Project frontend fallback pages to Project frontend home', () => {
    expect(resolve('/project/not-exists')).toBe('/project/portal');
  });

  it('falls back to platform default home when no plugin context matches', () => {
    expect(resolve('/unknown/not-exists')).toBe('/dashboard');
  });

  it('uses longest matched prefix and avoids similar-prefix false positives', () => {
    expect(resolve('/admin/project/report/not-exists', [
      ...backendHomes,
      { routePrefix: '/admin/project/report', homePath: '/admin/project/report' },
    ])).toBe('/admin/project/report');
    expect(resolve('/admin/smartx/not-exists')).toBe('/dashboard');
  });

  it('normalizes route query, hash and trailing slash before matching', () => {
    expect(resolve('/admin/smart/not-exists?from=test#hash')).toBe('/admin/smart/config');
    expect(resolve('/project/not-exists/')).toBe('/project/portal');
  });
});
