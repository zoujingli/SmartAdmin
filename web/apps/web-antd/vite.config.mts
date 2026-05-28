import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { defineConfig } from '@vben/vite-config';
import type { DefineApplicationOptions } from '../../internal/vite-config/src/typing';
import type { ConfigEnv, Plugin, ProxyOptions } from 'vite';
import { loadEnv } from 'vite';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
/** 构建产物输出到 web/dist/，由 SiteMiddleware 提供静态访问 */
const websiteOutDir = path.resolve(__dirname, '../../dist');
/** 项目根目录用于允许 Vite 读取 plugin/<Module>/<plugin.view_root> 前端资源 */
const projectRoot = path.resolve(__dirname, '../../..');
const pluginRoot = path.resolve(projectRoot, 'plugin');
const appSrcRoot = path.resolve(__dirname, 'src');
const appNodeModules = path.resolve(__dirname, 'node_modules');
const accessEntry = path.resolve(
  __dirname,
  '../../packages/effects/access/src/index.ts',
);
const commonUiEntry = path.resolve(
  __dirname,
  '../../packages/effects/common-ui/src/index.ts',
);
const storesEntry = path.resolve(
  __dirname,
  '../../packages/stores/src/index.ts',
);
const pluginEchartsEntry = path.resolve(
  __dirname,
  '../../packages/effects/plugins/src/echarts/index.ts',
);
const iconsEntry = path.resolve(__dirname, '../../packages/icons/src/index.ts');
const antdEntry = path.resolve(appNodeModules, 'ant-design-vue/es/index.js');
const dayjsEntry = path.resolve(appNodeModules, 'dayjs/esm/index.js');
const dayjsLocaleRoot = path.resolve(appNodeModules, 'dayjs/esm/locale');
const dayjsPluginRoot = path.resolve(appNodeModules, 'dayjs/esm/plugin');
const wangEditorEntry = path.resolve(
  appNodeModules,
  '@wangeditor/editor/dist/index.esm.js',
);
const wangEditorStyle = path.resolve(
  appNodeModules,
  '@wangeditor/editor/dist/css/style.css',
);
const wangEditorVueEntry = path.resolve(
  appNodeModules,
  '@wangeditor/editor-for-vue/dist/index.esm.js',
);
const vueRouterEntry = path.resolve(appNodeModules, 'vue-router');
const vueEntry = path.resolve(
  appNodeModules,
  'vue/dist/vue.runtime.esm-bundler.js',
);
const pluginPagesModuleId = 'virtual:xadmin-plugin-pages';
const resolvedPluginPagesModuleId = `\0${pluginPagesModuleId}`;
const pluginRoutesModuleId = 'virtual:xadmin-plugin-routes';
const resolvedPluginRoutesModuleId = `\0${pluginRoutesModuleId}`;
const pluginAuthEntriesModuleId = 'virtual:xadmin-plugin-auth-entries';
const resolvedPluginAuthEntriesModuleId = `\0${pluginAuthEntriesModuleId}`;
const pluginBackendHomesModuleId = 'virtual:xadmin-plugin-backend-homes';
const resolvedPluginBackendHomesModuleId = `\0${pluginBackendHomesModuleId}`;

interface PluginManifest {
  apps?: unknown;
  plugin?: {
    code?: unknown;
    view_root?: unknown;
  };
  view_root?: unknown;
}

function normalizeBackendUrl(raw: string): string {
  const t = raw.trim() || 'http://127.0.0.1:9501';
  return t.replace(/\/$/, '');
}

function isTruthyEnv(v: string | undefined): boolean {
  return ['true', '1', 'yes', 'on'].includes(String(v ?? '').toLowerCase());
}

function normalizePluginResourcePath(value: unknown): string {
  if (typeof value !== 'string') {
    return '';
  }

  const normalized = value
    .trim()
    .replaceAll('\\', '/')
    .split('/')
    .filter((part) => part && part !== '.')
    .join('/');

  if (!normalized || normalized.includes('..') || normalized.startsWith('/')) {
    return '';
  }

  return normalized;
}

function normalizeRoutePath(value: unknown): string {
  const raw = String(value || '').trim().split(/[?#]/)[0] || '';
  const normalized = `/${raw.replace(/^\/+/, '')}`.replace(/\/+$/, '');

  return normalized || '/';
}

function readPluginManifest(file: string): PluginManifest | undefined {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8')) as PluginManifest;
  } catch {
    return undefined;
  }
}

function walkVueFiles(root: string): string[] {
  if (!fs.existsSync(root) || !fs.statSync(root).isDirectory()) {
    return [];
  }

  const files: string[] = [];
  for (const entry of fs.readdirSync(root, { withFileTypes: true })) {
    const current = path.join(root, entry.name);
    if (entry.isDirectory()) {
      files.push(...walkVueFiles(current));
      continue;
    }

    if (entry.isFile() && current.endsWith('.vue')) {
      files.push(current);
    }
  }

  return files.sort();
}

function fsImportPath(file: string): string {
  return `/@fs/${file.replaceAll('\\', '/')}`;
}

function getPluginManifestFiles(): string[] {
  if (!fs.existsSync(pluginRoot)) {
    return [];
  }

  const files: string[] = [];
  for (const plugin of fs.readdirSync(pluginRoot, { withFileTypes: true })) {
    if (!plugin.isDirectory()) {
      continue;
    }

    const file = path.join(pluginRoot, plugin.name, 'plugin.json');
    if (fs.existsSync(file) && fs.statSync(file).isFile()) {
      files.push(file);
    }
  }

  return files.sort();
}

function createPluginPagesModule(): string {
  const entries: string[] = [];
  if (!fs.existsSync(pluginRoot)) {
    return 'export default {};';
  }

  for (const plugin of fs.readdirSync(pluginRoot, { withFileTypes: true })) {
    if (!plugin.isDirectory()) {
      continue;
    }

    const pluginName = plugin.name;
    const pluginDir = path.join(pluginRoot, pluginName);
    const manifest = readPluginManifest(path.join(pluginDir, 'plugin.json'));
    const viewRoot = normalizePluginResourcePath(
      manifest?.plugin?.view_root ?? manifest?.view_root,
    );
    if (!viewRoot) {
      continue;
    }

    const viewDir = path.join(pluginDir, viewRoot);
    for (const file of walkVueFiles(viewDir)) {
      const view = path.relative(viewDir, file).replaceAll('\\', '/');
      const importPath = fsImportPath(file);
      const aliases = [
        `@plugin/${pluginName}/views/${view}`,
        `@plugin/${pluginName}/view/${view}`,
        `plugin/${pluginName}/${viewRoot}/${view}`,
      ];

      if (pluginName === 'System' && viewRoot === 'stc/view') {
        const systemView = view.replace(/\.vue$/, '');
        aliases.push(
          `/system/${systemView}`,
          `/system/${view}`,
          `system/${systemView}`,
          `system/${view}`,
          `#/views/system/${systemView}`,
          `#/views/system/${view}`,
        );
      }

      for (const alias of aliases) {
        entries.push(`  ${JSON.stringify(alias)}: () => import(${JSON.stringify(importPath)}),`);
      }
    }
  }

  return `const pageMap = {\n${entries.join('\n')}\n};\nexport default pageMap;\n`;
}


function getPluginRouteFiles(): string[] {
  if (!fs.existsSync(pluginRoot)) {
    return [];
  }

  const files: string[] = [];
  for (const plugin of fs.readdirSync(pluginRoot, { withFileTypes: true })) {
    if (!plugin.isDirectory()) {
      continue;
    }

    const pluginDir = path.join(pluginRoot, plugin.name);
    const manifest = readPluginManifest(path.join(pluginDir, 'plugin.json'));
    const viewRoot = normalizePluginResourcePath(
      manifest?.plugin?.view_root ?? manifest?.view_root,
    );
    if (!viewRoot) {
      continue;
    }

    for (const routeFile of ['routes.ts', 'routes.mts']) {
      const file = path.join(pluginDir, viewRoot, routeFile);
      if (fs.existsSync(file) && fs.statSync(file).isFile()) {
        files.push(file);
      }
    }
  }

  return files.sort();
}

function getPluginAuthEntryFiles(): string[] {
  if (!fs.existsSync(pluginRoot)) {
    return [];
  }

  const files: string[] = [];
  for (const plugin of fs.readdirSync(pluginRoot, { withFileTypes: true })) {
    if (!plugin.isDirectory()) {
      continue;
    }

    const pluginDir = path.join(pluginRoot, plugin.name);
    const manifest = readPluginManifest(path.join(pluginDir, 'plugin.json'));
    const viewRoot = normalizePluginResourcePath(
      manifest?.plugin?.view_root ?? manifest?.view_root,
    );
    if (!viewRoot) {
      continue;
    }

    for (const configFile of ['auth-entry.ts', 'auth-entry.mts']) {
      const file = path.join(pluginDir, viewRoot, configFile);
      if (fs.existsSync(file) && fs.statSync(file).isFile()) {
        files.push(file);
      }
    }
  }

  return files.sort();
}

function createPluginRoutesModule(): string {
  const files = getPluginRouteFiles();
  if (files.length === 0) {
    return 'const routes = [];\nexport default routes;\n';
  }

  const imports = files.map((file, index) => (
    `import routeModule${index} from ${JSON.stringify(fsImportPath(file))};`
  ));
  const routes = files.map((_, index) => `...normalizeRoutes(routeModule${index})`);

  return `${imports.join('\n')}\n`
    + 'function normalizeRoutes(module) { return Array.isArray(module) ? module : []; }\n'
    + `const routes = [${routes.join(', ')}];\n`
    + 'export default routes;\n';
}

function createPluginAuthEntriesModule(): string {
  const files = getPluginAuthEntryFiles();
  if (files.length === 0) {
    return 'const authEntries = [];\nexport default authEntries;\n';
  }

  const imports = files.map((file, index) => (
    `import authEntry${index} from ${JSON.stringify(fsImportPath(file))};`
  ));
  const entries = files.map((_, index) => `authEntry${index}`);

  return `${imports.join('\n')}\n`
    + `const authEntries = [${entries.join(', ')}].filter(Boolean);\n`
    + 'export default authEntries;\n';
}

function createPluginBackendHomesModule(): string {
  const entries: Array<{ homePath: string; routePrefix: string }> = [];

  for (const file of getPluginManifestFiles()) {
    const manifest = readPluginManifest(file);
    const pluginCode = String(manifest?.plugin?.code || '').toLowerCase();
    if (pluginCode === 'system') {
      continue;
    }

    for (const app of Array.isArray(manifest?.apps) ? manifest.apps : []) {
      const item = app as Record<string, unknown>;
      const routePrefix = normalizeRoutePath(item.route);
      if (routePrefix === '/') {
        continue;
      }

      entries.push({
        homePath: normalizeRoutePath(item.redirect || routePrefix),
        routePrefix,
      });
    }
  }

  entries.sort((a, b) => b.routePrefix.length - a.routePrefix.length);

  // 这里只输出插件后台入口与首页映射，避免异常页引入完整 plugin.json 菜单树。
  return `const backendHomes = ${JSON.stringify(entries, null, 2)};\nexport default backendHomes;\n`;
}

function isPluginRouteFile(file: string): boolean {
  const normalized = file.replaceAll('\\', '/');

  return getPluginRouteFiles().some((routeFile) => routeFile.replaceAll('\\', '/') === normalized);
}

function isPluginManifestFile(file: string): boolean {
  const normalized = file.replaceAll('\\', '/');

  return getPluginManifestFiles().some((manifestFile) => manifestFile.replaceAll('\\', '/') === normalized);
}

function isPluginAuthEntryFile(file: string): boolean {
  const normalized = file.replaceAll('\\', '/');

  return getPluginAuthEntryFiles().some((configFile) => configFile.replaceAll('\\', '/') === normalized);
}

function pluginPagesVirtualModule(): Plugin {
  return {
    name: 'xadmin-plugin-pages',
    configureServer(server) {
      const viewRoots = getPluginViewRoots();
      const routeFiles = getPluginRouteFiles();
      const authEntryFiles = getPluginAuthEntryFiles();
      const manifestFiles = getPluginManifestFiles();
      if (viewRoots.length > 0) {
        server.watcher.add(viewRoots);
      }
      if (routeFiles.length > 0) {
        server.watcher.add(routeFiles);
      }
      if (authEntryFiles.length > 0) {
        server.watcher.add(authEntryFiles);
      }
      if (manifestFiles.length > 0) {
        server.watcher.add(manifestFiles);
      }
    },
    handleHotUpdate(ctx) {
      const isViewFile = isPluginViewFile(ctx.file);
      const isRouteFile = isPluginRouteFile(ctx.file);
      const isAuthEntryFile = isPluginAuthEntryFile(ctx.file);
      const isManifestFile = isPluginManifestFile(ctx.file);
      if (!isViewFile && !isRouteFile && !isAuthEntryFile && !isManifestFile) {
        return;
      }

      const pagesModule = ctx.server.moduleGraph.getModuleById(
        resolvedPluginPagesModuleId,
      );
      if (pagesModule) {
        ctx.server.moduleGraph.invalidateModule(pagesModule);
      }

      const routesModule = ctx.server.moduleGraph.getModuleById(
        resolvedPluginRoutesModuleId,
      );
      if (routesModule) {
        ctx.server.moduleGraph.invalidateModule(routesModule);
      }

      const authEntriesModule = ctx.server.moduleGraph.getModuleById(
        resolvedPluginAuthEntriesModuleId,
      );
      if (authEntriesModule) {
        ctx.server.moduleGraph.invalidateModule(authEntriesModule);
      }

      const backendHomesModule = ctx.server.moduleGraph.getModuleById(
        resolvedPluginBackendHomesModuleId,
      );
      if (backendHomesModule) {
        ctx.server.moduleGraph.invalidateModule(backendHomesModule);
      }

      // 已加载的插件 .vue 文件继续交给 @vitejs/plugin-vue 做组件级 HMR；
      // routes.ts / auth-entry.ts / plugin.json 仅让虚拟模块下次读取时重新生成。
      return;
    },
    resolveId(id) {
      if (id === pluginPagesModuleId) {
        return resolvedPluginPagesModuleId;
      }
      if (id === pluginRoutesModuleId) {
        return resolvedPluginRoutesModuleId;
      }
      if (id === pluginAuthEntriesModuleId) {
        return resolvedPluginAuthEntriesModuleId;
      }
      if (id === pluginBackendHomesModuleId) {
        return resolvedPluginBackendHomesModuleId;
      }
      return undefined;
    },
    load(id) {
      if (id === resolvedPluginPagesModuleId) {
        return createPluginPagesModule();
      }
      if (id === resolvedPluginRoutesModuleId) {
        return createPluginRoutesModule();
      }
      if (id === resolvedPluginAuthEntriesModuleId) {
        return createPluginAuthEntriesModule();
      }
      if (id === resolvedPluginBackendHomesModuleId) {
        return createPluginBackendHomesModule();
      }
      return undefined;
    },
  };
}

function getPluginViewRoots(): string[] {
  if (!fs.existsSync(pluginRoot)) {
    return [];
  }

  const roots: string[] = [];
  for (const plugin of fs.readdirSync(pluginRoot, { withFileTypes: true })) {
    if (!plugin.isDirectory()) {
      continue;
    }

    const pluginDir = path.join(pluginRoot, plugin.name);
    const manifest = readPluginManifest(path.join(pluginDir, 'plugin.json'));
    const viewRoot = normalizePluginResourcePath(
      manifest?.plugin?.view_root ?? manifest?.view_root,
    );
    if (!viewRoot) {
      continue;
    }

    const viewDir = path.join(pluginDir, viewRoot);
    if (fs.existsSync(viewDir) && fs.statSync(viewDir).isDirectory()) {
      roots.push(viewDir);
    }
  }

  return roots;
}

function isPluginViewFile(file: string): boolean {
  const normalized = file.replaceAll('\\', '/');

  return getPluginViewRoots().some((root) => {
    const normalizedRoot = root.replaceAll('\\', '/');

    return normalized.startsWith(`${normalizedRoot}/`) && normalized.endsWith('.vue');
  });
}

const configure: DefineApplicationOptions = async (config?: ConfigEnv) => {
  const mode = config?.mode ?? 'development';
  const env = loadEnv(mode, process.cwd(), '');
  const proxyTarget = normalizeBackendUrl(
    env.VITE_PROXY_TARGET || 'http://127.0.0.1:9501',
  );
  const isDev = mode === 'development';
  const useProxy = isDev && isTruthyEnv(env.VITE_DEV_USE_PROXY);

  /** 开发环境下由本文件注入，避免 .env 里 GLOB/BACKEND 与代理开关不同步 */
  const devDefine: Record<string, string> = {};
  if (isDev) {
    const apiBase = useProxy ? '/api/' : `${proxyTarget}/`;
    devDefine['import.meta.env.VITE_GLOB_API_URL'] = JSON.stringify(apiBase);
    devDefine['import.meta.env.VITE_BACKEND_URL'] = JSON.stringify(`${proxyTarget}/`);
  }

  const devProxy: Record<string, string | ProxyOptions> =
    isDev && useProxy
      ? {
          // Web 壳只保留通用 /api 代理入口；业务插件私有前缀不在这里硬编码。
          // 开发代理会把 /api/<真实接口前缀> 转发为后端的 /<真实接口前缀>，
          // 避免插件路由和主应用开发代理耦合。
          '/api': {
            changeOrigin: true,
            rewrite: (p: string) => p.replace(/^\/api/, ''),
            target: proxyTarget,
            ws: true,
          },
        }
      : {};

  return {
    application: {},
    vite: {
      ...(Object.keys(devDefine).length > 0 ? { define: devDefine } : {}),
      plugins: [pluginPagesVirtualModule()],
      build: {
        outDir: websiteOutDir,
        emptyOutDir: true,
      },
      resolve: {
        alias: [
          // 插件前端文件位于项目根 plugin/*/<plugin.view_root>，不在当前 app 包目录下。
          // 生产构建从插件文件解析裸导入时不会自动套用 tsconfig paths，
          // 这里显式镜像关键别名，确保插件页面可以直接复用主应用 API 与 UI 依赖。
          { find: /^#\/(.*)$/, replacement: `${appSrcRoot}/$1` },
          { find: /^@plugin\/(.*)$/, replacement: `${pluginRoot}/$1` },
          { find: /^@vben\/access$/, replacement: accessEntry },
          { find: /^@vben\/common-ui$/, replacement: commonUiEntry },
          { find: /^@vben\/stores$/, replacement: storesEntry },
          { find: /^@vben\/plugins\/echarts$/, replacement: pluginEchartsEntry },
          { find: /^@vben\/icons$/, replacement: iconsEntry },
          { find: /^ant-design-vue$/, replacement: antdEntry },
          { find: /^dayjs$/, replacement: dayjsEntry },
          // 富文本公共组件使用 wangEditor；workspace 源码裸导入统一回指主应用依赖，避免插件目录解析漂移。
          { find: /^@wangeditor\/editor$/, replacement: wangEditorEntry },
          {
            find: /^@wangeditor\/editor\/dist\/css\/style\.css$/,
            replacement: wangEditorStyle,
          },
          { find: /^@wangeditor\/editor-for-vue$/, replacement: wangEditorVueEntry },
          {
            find: /^dayjs\/locale\/(.*)$/,
            replacement: `${dayjsLocaleRoot}/$1.js`,
          },
          {
            find: /^dayjs\/plugin\/(.*)$/,
            replacement: `${dayjsPluginRoot}/$1/index.js`,
          },
          // 插件页面可能位于 web 包外，裸导入需要显式指向主应用依赖；
          // 这里保留包入口解析，避免命中 vue-router 已废弃的 esm-bundler 直连文件。
          { find: /^vue-router$/, replacement: vueRouterEntry },
          { find: /^vue$/, replacement: vueEntry },
        ],
        dedupe: ['vue'],
      },
      server: {
        fs: {
          allow: [projectRoot, pluginRoot],
        },
        proxy: Object.keys(devProxy).length > 0 ? devProxy : undefined,
      },
    },
  };
};

export default defineConfig(configure);
