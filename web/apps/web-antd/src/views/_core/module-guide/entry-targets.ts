import type { ModuleGuideEntry } from '#/plugins/module-guide-provider';

export function normalizeModuleGuidePath(value: string) {
  const path = `/${String(value || '').trim().replace(/^\/+/, '')}`;

  return path === '/' ? '/' : path.replace(/\/+$/, '');
}

export function moduleGuideHomeTarget(entry: ModuleGuideEntry, fallbackPath: string) {
  return normalizeModuleGuidePath(entry.login_path || entry.home_path || fallbackPath);
}

/**
 * System 是管理兜底入口，不参与常规子系统数量判断；只有多个常规入口时才保留选择页。
 */
export function moduleGuideAutomaticTarget(entries: ModuleGuideEntry[], fallbackPath: string) {
  const regularEntries = entries.filter((entry) => String(entry.code || '').trim().toLowerCase() !== 'system');
  if (regularEntries.length === 0) {
    return normalizeModuleGuidePath(fallbackPath);
  }

  return regularEntries.length === 1
    ? moduleGuideHomeTarget(regularEntries[0]!, fallbackPath)
    : '';
}
