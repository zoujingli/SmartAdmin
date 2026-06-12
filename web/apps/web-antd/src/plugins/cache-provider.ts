export interface CacheClearResult {
  items: string[];
  message: string;
  scope: 'global' | 'self';
}

type CacheClearProvider = () => Promise<CacheClearResult>;

let cacheClearProvider: null | CacheClearProvider = null;

export function configureCacheClearProvider(provider: CacheClearProvider) {
  cacheClearProvider = provider;
}

export function getCacheClearProvider() {
  return cacheClearProvider;
}
