import type { ReferenceProvider } from './types';

const providers = new Map<string, ReferenceProvider>();

export function registerReferenceProvider(key: string, provider: ReferenceProvider) {
  if (!key) return;
  providers.set(key, provider);
}

export function unregisterReferenceProvider(key: string) {
  providers.delete(key);
}

export function getReferenceProvider(key = 'default') {
  return providers.get(key);
}
