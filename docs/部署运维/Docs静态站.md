# Docs 静态站

本项目文档基于 docsify，源码位于 `docs/`。生产环境按独立静态站部署，不接入 Hyperf 后端 `/docs` 路由。

## 定位

docs 是开源项目的第一入口，承担以下职责：

- 快速启动和本地开发引导。
- 系统功能、接口、架构和部署说明。
- 贡献流程、安全披露和版本路线图。
- 作为 GitHub Pages、Nginx、对象存储静态站等平台的静态内容源。

docs 不依赖后端服务、不读取数据库、不需要 Node 构建。它只需要静态托管 `docs/` 目录。

## 本地预览

```bash
composer docs:serve
```

默认访问：

```text
http://127.0.0.1:18100
```

该命令使用 PHP 内置静态服务器，以 `docs/` 为站点根目录。只适合本地预览，不用于公网生产。

## 静态检查

```bash
composer docs:check
```

检查内容包括：

- docsify 关键入口存在。
- 顶部模块导航脚本已加载，并由 `_sidebar.md` 一级分组生成入口。
- 默认路由直接进入 `快速开始/README.md` 正文，不启用 coverpage。
- docsify-pagination 已加载，章节页底部展示上一章节/下一章节入口。
- Mermaid 图表脚本已加载，` ```mermaid ` 代码块可渲染 `flowchart LR`。
- 侧边栏使用 `/章节/页面` docsify 根路径。
- 8 个主章节入口存在。
- 关键 Markdown 内部链接目标存在。

建议在每次修改文档后运行 `composer docs:check`。如果 PR 只改文档，至少需要该检查通过。

## Nginx 部署

将 `docs/` 目录发布为站点根目录：

```nginx
server {
    listen 80;
    server_name docs.example.com;
    root /path/to/SmartAdmin/docs;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

访问：

```text
https://docs.example.com/
https://docs.example.com/#/快速开始/README
```

如果部署在子路径，例如 `https://example.com/smartadmin-docs/`，需要确保静态平台能正确回退到 `index.html`，并且入口访问使用 hash 路由：

```text
https://example.com/smartadmin-docs/#/快速开始/README
```

不要把 docs 请求转发到 Hyperf 后端 `/docs`。这会让文档部署和后端服务生命周期耦合，也不利于开源托管和 CDN 缓存。

## GitHub Pages 部署

可以将 `docs/` 作为 Pages 源目录，或在 CI 中把 `docs/` 发布到 Pages 分支。关键点：

1. 发布内容必须以 `docs/index.html` 为入口。
2. 保留 `_sidebar.md`、`_404.md` 和 `assets/` 下的文档站脚本样式。
3. 使用 hash 路由，避免 Pages 对中文路径直接刷新 404。
4. 发布前运行 `composer docs:check`。

## 对象存储部署

使用 OSS/COS/S3/Qiniu 等对象存储静态站时：

| 配置 | 建议 |
|------|------|
| 默认入口文件 | `index.html` |
| 错误页 | `index.html` 或 `_404.md` 由 docsify 处理 |
| 缓存 | HTML 短缓存，Markdown 和静态资源可较长缓存 |
| 编码 | 确认中文路径和 Markdown 文件以 UTF-8 上传 |
| CDN | 开启 gzip/br 压缩，刷新 `_sidebar.md`、Markdown 和 `assets/` 缓存 |

## 版本化文档

开源项目发布后，如果需要维护多版本文档，可以采用：

| 方式 | 说明 |
|------|------|
| 分支版本 | 每个维护分支保留自己的 `docs/` |
| 目录版本 | `docs/v1`、`docs/v2`，顶部模块导航或侧边栏增加版本入口 |
| 标签归档 | 发布时把 docs 静态产物归档到对象存储 |

第一版建议先保持单版本，减少维护成本。等 API 和部署流程稳定后再引入多版本。

## 链接规则

- 顶部模块导航不手写入口，由 `devapi-module-nav.js` 从 `_sidebar.md` 一级分组生成。
- `_sidebar.md` 使用 docsify 根路径：`/章节/页面`。
- 正文 Markdown 页面内部优先使用相对路径，例如 `../接口参考/用户接口.md`。

## 内容规则

- 文档语言统一中文。
- 每个功能页尽量包含用途、前置条件、操作步骤、实现关联、常见问题。
- 接口文档从 Controller 注解和前端 API 整理，不虚构未实现接口。
- 部署文档区分本地预览、生产静态站和后端服务部署。
- 文档中不要写入真实密钥、Token、账号密码、生产域名内部路径。

## 排查

| 现象 | 处理 |
|------|------|
| 点击链接无反应 | 检查链接是否符合 navbar/sidebar 规则 |
| 刷新后 404 | 静态站需要回退到 `index.html`，并使用 hash 路由 |
| 侧边栏不更新 | 清理浏览器缓存或 CDN 缓存 |
| 中文路径异常 | 确认文件名、链接和上传编码均为 UTF-8 |
| 搜索不到新内容 | 刷新 docsify 缓存，确认 Markdown 文件已发布 |

最后更新：2026-04-27
