export interface ModuleGuideEntry {
  code: string;
  description: string;
  enabled: boolean;
  home_path: string;
  icon: string;
  login_path: string;
  name: string;
  plugin: string;
  sort: number;
}

export interface ModuleGuide {
  app?: {
    description: string;
    name: string;
  };
  enabled: boolean;
  entries: ModuleGuideEntry[];
}

type ModuleGuideProvider = () => Promise<ModuleGuide>;

let moduleGuideProvider: null | ModuleGuideProvider = null;

export function configureModuleGuideProvider(provider: ModuleGuideProvider) {
  moduleGuideProvider = provider;
}

export function getModuleGuideProvider() {
  return moduleGuideProvider;
}
