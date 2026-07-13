import type { ModuleGuideEntry } from '#/plugins/module-guide-provider';

import { describe, expect, it } from 'vitest';

import {
  moduleGuideAutomaticTarget,
  moduleGuideHomeTarget,
  normalizeModuleGuidePath,
} from '../entry-targets';

function entry(value: Partial<ModuleGuideEntry>): ModuleGuideEntry {
  return {
    code: 'demo',
    description: '演示入口',
    enabled: true,
    home_path: '',
    icon: 'lucide:blocks',
    login_path: '',
    name: '演示',
    plugin: 'Demo',
    sort: 1,
    ...value,
  };
}

describe('module guide entry targets', () => {
  it('uses the independent plugin login as the only guide target when present', () => {
    const item = entry({
      home_path: '/partner/dashboard',
      login_path: '/partner/login',
    });

    expect(moduleGuideHomeTarget(item, '/fallback/login')).toBe('/partner/login');
  });

  it('falls back to the plugin home path when no independent login is declared', () => {
    const item = entry({
      home_path: '/project/portal/',
    });

    expect(moduleGuideHomeTarget(item, '/fallback/login')).toBe('/project/portal');
  });

  it('falls back to the current auth login path when an entry has no target path', () => {
    const item = entry({});

    expect(moduleGuideHomeTarget(item, '/fallback/login')).toBe('/fallback/login');
  });

  it('normalizes client routes without accepting empty relative display values', () => {
    expect(normalizeModuleGuidePath(' partner/login/ ')).toBe('/partner/login');
    expect(normalizeModuleGuidePath('/')).toBe('/');
    expect(normalizeModuleGuidePath('')).toBe('/');
  });

  it('falls back to the System login when no regular entry is visible', () => {
    expect(moduleGuideAutomaticTarget([
      entry({ code: 'system', login_path: '/default/login' }),
    ], '/default/login')).toBe('/default/login');
  });

  it('opens the only regular entry login and ignores System in the count', () => {
    expect(moduleGuideAutomaticTarget([
      entry({ code: 'project', home_path: '/project/portal', login_path: '/project/login' }),
      entry({ code: 'system', login_path: '/default/login' }),
    ], '/default/login')).toBe('/project/login');
  });

  it('falls back to the only regular entry home when it has no login path', () => {
    expect(moduleGuideAutomaticTarget([
      entry({ code: 'project', home_path: '/project/portal' }),
    ], '/default/login')).toBe('/project/portal');
  });

  it('keeps the guide page when multiple regular entries are visible', () => {
    expect(moduleGuideAutomaticTarget([
      entry({ code: 'project', login_path: '/project/login' }),
      entry({ code: 'points', login_path: '/points/login' }),
      entry({ code: 'system', login_path: '/default/login' }),
    ], '/default/login')).toBe('');
  });
});
