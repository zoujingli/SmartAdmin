# 📘 SmartAdmin

> 面向开源使用、二次开发、接口联调、插件扩展和私有化部署的文档工作台。先从这里确认阅读路径，再进入对应章节查看字段、示例、命令和风险边界。🚀

最后更新：2026-05-18

## 🧭 文档边界

<!--
内部维护规则：docs 是 SmartAdmin 开源交付、二次开发和接口合同的当前文档源头；代码注释、历史讨论、临时任务记录和外部说明仅作背景参考。该规则不在发布内容中展示。
-->

> 小提示：接口字段、请求示例、响应示例、权限码和错误边界以 [接口参考](接口参考/README.md) 与当前源码为准；本页用于快速找入口和理解维护边界。

阅读建议：

- 🧪 初次体验先完成 [本地启动](快速开始/本地启动.md)，再进入用户教程验证后台主流程。
- 🔐 做接口联调先读 [接口参考](接口参考/README.md) 和 [在线测试](接口参考/在线测试.md)，确认 Token、权限码和标准响应壳。
- 📦 准备生产发布先读 [生产部署](部署运维/生产部署.md) 与 [发布升级](部署运维/发布升级.md)，再执行 release dry-run。
- 📌 文档、接口或菜单变更后必须运行 `composer docs:check`，接口数量和路由覆盖以检查结果为准。

## ⚡ 快速入口

| 入口 | 适合对象 | 当前口径 |
|---|---|---|
| [快速开始](快速开始/README.md) | 新用户、体验者 | 环境准备、本地启动、默认入口和常见问题 |
| [用户教程](用户教程/README.md) | 管理员、产品、测试 | 登录、工作台、组织权限、文件公告日志、租户运维 |
| [系统功能](系统功能/README.md) | 实施、架构、开发 | 认证权限、系统管理、上传、审计、租户和发布能力边界 |
| [开发指南](开发指南/README.md) | 后端、前端、插件开发 | 分层规则、接口规范、插件开发、前端接入和编码约束 |
| [接口参考](接口参考/README.md) | 前后端联调、测试 | 当前 `384` 个 HTTP 接口的字段、示例、权限和在线调试 |
| [部署运维](部署运维/README.md) | 运维、私有化交付 | 开发/生产部署、Docs 静态站、发布升级、缓存和日志 |
| [开源协作](开源协作/README.md) | 贡献者、维护者 | 协议、贡献、赞助、发布流程、安全披露和路线图 |

## 🧷 最新标准入口

| 标准事项 | 当前标准 |
|---|---|
| 插件资源 | 业务插件通过本地 Composer path 包、Provider、`plugin.json` 和 Web 编译期扫描接入；源码/CI 模式可使用 Library 内置 `xadmin:plugin:*` 打包、安装、移除和备份恢复，backup 默认只备份代码，`--with-data` 才包含插件自有表，发布二进制内不出现这些命令，也不支持运行时远程安装、更新或删除插件。 |
| 文件上传 | 统一使用 `upload/runtime`、`upload/prepare`、`upload/relay`、`upload/relay-chunk`、`upload/part-sign`、`upload/complete`、`upload/abort`。 |
| 微信支付回调 | 只保留订单与退款标准回调：`/wechat-client/api/payment/notify/order/{merchantId}`、`/wechat-client/api/payment/notify/refund/{merchantId}`。 |
| 微信开放平台回调 | 授权账号消息与事件必须使用 `/wechat-service/api/callback/notify/{appid}`，由标准 URL 中的 AppID 确认消息归属。 |
| 前端菜单 | 后台动态路由读取 `/system/menu/user`，按钮权限读取 `/system/menu/permissions`；不再使用额外兼容菜单入口。 |
| 发布升级 | 生产升级使用 release 快照机制，`xadmin:release:upgrade --dry-run` 先输出 SQL、替换表和备份路径。 |

## 🧩 章节索引

| 章节 | 范围 | 推荐阅读 |
|---|---|---|
| [快速开始](快速开始/README.md) | 环境要求、安装、启动、默认入口 | 新用户先读 |
| [用户教程](用户教程/README.md) | 后台页面操作、关键按钮、风险确认 | 管理员和测试先读 |
| [系统功能](系统功能/README.md) | 系统能力边界、数据模型、权限、日志、租户、发布 | 理解平台能力 |
| [开发指南](开发指南/README.md) | Controller/Service/Mapper/Model 分层、插件和前端接入 | 二次开发前必读 |
| [接口参考](接口参考/README.md) | 路由、权限、字段、示例、错误码、在线调试 | 联调和自动化测试 |
| [架构设计](架构设计/README.md) | 整体设计、权限菜单、深度分析 | 架构评审和扩展设计 |
| [部署运维](部署运维/README.md) | 开发部署、生产部署、Docs 静态站、发布升级 | 上线前必读 |
| [开源协作](开源协作/README.md) | 授权协议、贡献、安全、路线图、FAQ | 参与社区协作 |
| [文档维护](文档维护/README.md) | 文档规范、接口文档标准、检查和发布规则 | 修改 docs 前必读 |
| [模块文档](模块文档/README.md) | 模块能力、权限清单和前后端对接补充 | 作为主线文档补充 |
| [运维手册](运维手册/README.md) | 缓存、迁移、日志审计和异常响应专题 | 运维排障补充 |

## 🗺️ 系统能力地图

| 能力 | 实现位置 | 文档入口 |
|---|---|---|
| 登录、Token、个人资料 | `plugin/System/src/Controller/AuthController.php` | [认证接口](接口参考/认证接口.md) |
| 用户、角色、菜单、部门、岗位 | `plugin/System` | [组织权限管理](用户教程/组织权限管理.md) |
| 权限注解、节点同步、数据范围 | `plugin/Library/Events`、`plugin/System/src/Command` | [认证权限与数据范围](系统功能/认证权限与数据范围.md) |
| 文件管理、上传通道、去重 | `plugin/System/src/Controller/FileController.php` | [文件上传与存储](系统功能/文件上传与存储.md) |
| 操作日志、请求日志、变更审计 | `plugin/Library/Events`、`plugin/System/src/Controller/Logs*Controller.php` | [日志审计与公告](系统功能/日志审计与公告.md) |
| 多租户管理和租户上下文 | `plugin/System`、`TenantContext` | [租户与发布构建](系统功能/租户与发布构建.md) |
| 微信客户端与开放平台 | `plugin/WechatClient`、`plugin/WechatService` | [微信客户端接口](接口参考/微信客户端接口.md)、[微信开放平台接口](接口参考/微信开放平台接口.md) |
| 数据库发布快照和 Phar 打包 | `plugin/Library/Command`、`plugin/Builder` | [发布升级](部署运维/发布升级.md) |
| Vue 3 管理端 | `web/apps/web-antd` 通用壳、`plugin/*/stc/view` 插件页面 | [前端接入](开发指南/前端接入.md) |

## 🚀 5 分钟启动

```bash
composer install
cp .env.example .env
composer setup
sh bin/start-swoole start
cd web && pnpm install && pnpm dev:antd
```

默认入口：

| 入口 | 地址或账号 |
|---|---|
| 前端 | `http://localhost:5666` |
| 后端 | `http://127.0.0.1:9501` |
| 文档 | `composer docs:serve` 后访问 `http://127.0.0.1:18100` |
| 默认账号 | `admin/admin` |

完整启动说明见 [本地启动](快速开始/本地启动.md)。

## 🔎 公共导航

- [整体架构](架构设计/系统架构.md)
- [权限与菜单](架构设计/权限与菜单.md)
- [接口参考](接口参考/README.md)
- [在线测试](接口参考/在线测试.md)
- [插件开发](开发指南/插件开发.md)
- [前端接入](开发指南/前端接入.md)
- [Docs 静态站](部署运维/Docs静态站.md)
- [文档维护规范](文档维护/维护规范.md)
- [更新日志](文档维护/更新日志.md)
