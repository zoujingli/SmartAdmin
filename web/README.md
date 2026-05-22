# SmartAdmin 前端

基于 [Vue Vben Admin](https://github.com/vbenjs/vue-vben-admin) `v5.6.0` 的单前端工作区，当前保留 `@vben/web-antd` 主应用。业务插件页面由 `plugin/*/plugin.json` 的 `plugin.view_root` 显式声明后，在构建期扫描并编入主应用产物。

## 目录

```text
web/
├── apps/
│   └── web-antd/      # 管理端主应用、通用壳和插件扫描入口
├── packages/          # 共享包与组件
├── internal/          # 内部构建、lint、配置
└── dist/              # 本地/CI 构建产物，不提交 Git，供 composer build 打包复用
```

## 环境要求

- Node.js `>= 20.19.0`
- pnpm `>= 10.0.0`

## 快速开始

```bash
pnpm install
pnpm dev
```

默认会启动 `@vben/web-antd`，开发端口来自 `apps/web-antd/.env.development` 的 `VITE_PORT`，默认 `5666`。

## 常用命令

```bash
# 开发
pnpm dev
pnpm dev:web:proxy
pnpm dev:web:direct

# 构建
pnpm build
pnpm build:analyze

# 预览
pnpm preview

# 校验
pnpm typecheck
pnpm test:unit
pnpm lint
pnpm format
pnpm check
```

根目录发布检查建议使用 `composer web:build`，它会先执行 `@vben/web-antd` 类型检查，再执行生产构建。

## 环境变量

开发环境变量位于 `apps/web-antd/.env.development`，API 地址由 `vite.config.mts` 根据 `VITE_DEV_USE_PROXY` 和 `VITE_PROXY_TARGET` 注入：

```env
VITE_PORT=5666
VITE_APP_TITLE=SmartAdmin
VITE_BASE=/
VITE_DEV_USE_PROXY=false
VITE_PROXY_TARGET=http://127.0.0.1:9501
```

- `VITE_DEV_USE_PROXY=false`：浏览器直连 `VITE_PROXY_TARGET`，需要后端 CORS 允许当前前端 Origin。
- `VITE_DEV_USE_PROXY=true`：Vite 会注入 `VITE_GLOB_API_URL=/api/`，浏览器统一请求 `/api/<真实接口前缀>`，再由 Vite 转发为后端 `/<真实接口前缀>`；插件私有前缀不要硬编码进 Web 壳。
- 不要在开发 `.env` 中手写 `VITE_GLOB_API_URL`；该值由 Vite 配置按代理模式统一注入。

## 构建产物

生产构建输出到 `web/dist/`。该目录是本地/CI 构建产物，不提交 Git；发布打包时由根目录 `composer build` 校验并压缩进 Phar 的 `storage/extra/web-dist.zip`。

```bash
pnpm build
# 或在仓库根目录执行
composer web:build
```

## 技术栈

- Vue 3
- TypeScript
- Vite
- Ant Design Vue 4
- Pinia
- Vue Router 4
- Tailwind CSS
- ECharts
