declare module 'virtual:xadmin-plugin-routes' {
  import type { RouteRecordRaw } from 'vue-router';

  const routes: RouteRecordRaw[];

  export default routes;
}

declare module 'virtual:xadmin-plugin-auth-entries' {
  import type { RouteRecordStringComponent } from '@vben/types';

  interface PluginAuthEntryProfileConfig {
    description?: string;
    nicknameLabel?: string;
    signedLabel?: string;
    title?: string;
  }

  interface PluginAuthEntryConfig {
    authBase: string;
    default?: boolean;
    entry: string;
    homePath: string;
    loginPath: string;
    menus?: RouteRecordStringComponent[];
    name: string;
    passwordPurposes?: Partial<Record<string, string>>;
    permissionPrefixes?: string[];
    profile?: PluginAuthEntryProfileConfig;
    profilePath?: string;
    routePrefixes?: string[];
    userModel?: string;
    userModelIncludes?: string[];
  }

  const authEntries: PluginAuthEntryConfig[];

  export default authEntries;
}

declare module 'virtual:xadmin-plugin-setups' {
  import type { App } from 'vue';
  import type { Router } from 'vue-router';

  interface PluginSetupContext {
    app?: App;
    namespace?: string;
    router?: Router;
    [key: string]: unknown;
  }

  export function setupPlugins(appContext?: PluginSetupContext): Promise<void>;
}

declare module 'virtual:xadmin-plugin-backend-homes' {
  interface PluginBackendHomeEntry {
    homePath: string;
    routePrefix: string;
  }

  const backendHomes: PluginBackendHomeEntry[];

  export default backendHomes;
}
