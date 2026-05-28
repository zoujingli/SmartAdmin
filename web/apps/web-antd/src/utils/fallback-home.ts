export interface FallbackBackendHomeEntry {
  homePath: string;
  routePrefix: string;
}

export interface FallbackAuthHomeEntry {
  entry?: string;
  homePath?: string;
  routePrefixes?: string[];
}

export interface ResolveFallbackHomeOptions {
  authEntries?: FallbackAuthHomeEntry[];
  backendHomes?: FallbackBackendHomeEntry[];
  defaultHomePath: string;
}

interface HomeCandidate {
  homePath: string;
  routePrefix: string;
}

function normalizePath(value: unknown): string {
  const raw = String(value || '').trim().split(/[?#]/)[0] || '';
  const path = `/${raw.replace(/^\/+/, '')}`.replace(/\/+$/, '');

  return path || '/';
}

function isMatchedPrefix(path: string, prefix: string): boolean {
  return path === prefix || path.startsWith(`${prefix}/`);
}

function pushCandidate(candidates: HomeCandidate[], routePrefix: unknown, homePath: unknown): void {
  const prefix = normalizePath(routePrefix);
  const target = normalizePath(homePath);
  if (prefix === '/' || target === '/') {
    return;
  }

  candidates.push({ homePath: target, routePrefix: prefix });
}

/**
 * 解析异常页“返回首页”的目标。
 *
 * 业务插件的后台入口来自 plugin.json，前台独立入口来自 auth-entry.ts；
 * 同一路径命中多个前缀时取最长前缀，避免 /system/project/report 被 /system/project 提前截断。
 */
export function resolveFallbackHomePath(path: unknown, options: ResolveFallbackHomeOptions): string {
  const currentPath = normalizePath(path);
  const defaultHomePath = normalizePath(options.defaultHomePath);
  const candidates: HomeCandidate[] = [];

  for (const entry of options.backendHomes || []) {
    pushCandidate(candidates, entry.routePrefix, entry.homePath);
  }

  for (const entry of options.authEntries || []) {
    if (entry.entry === 'system') {
      continue;
    }

    for (const prefix of entry.routePrefixes || []) {
      pushCandidate(candidates, prefix, entry.homePath);
    }
  }

  return candidates
    .filter((candidate) => isMatchedPrefix(currentPath, candidate.routePrefix))
    .sort((a, b) => b.routePrefix.length - a.routePrefix.length)[0]?.homePath || defaultHomePath;
}
