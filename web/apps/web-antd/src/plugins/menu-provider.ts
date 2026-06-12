import type { RouteRecordStringComponent } from '@vben/types';

type MenuProvider = () => Promise<RouteRecordStringComponent[]>;

const providers = new Map<string, MenuProvider>();

export function configureAuthEntryMenuProvider(entry: string, provider: MenuProvider) {
  const normalized = String(entry || '').trim();
  if (normalized) {
    providers.set(normalized, provider);
  }
}

export function getAuthEntryMenuProvider(entry: string) {
  return providers.get(String(entry || '').trim());
}
