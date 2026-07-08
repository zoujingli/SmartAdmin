import type { ModuleGuideEntry } from '#/plugins/module-guide-provider';

export function normalizeModuleGuidePath(value: string) {
  const path = `/${String(value || '').trim().replace(/^\/+/, '')}`;

  return path === '/' ? '/' : path.replace(/\/+$/, '');
}

export function moduleGuideHomeTarget(entry: ModuleGuideEntry, fallbackPath: string) {
  return normalizeModuleGuidePath(entry.login_path || entry.home_path || fallbackPath);
}
