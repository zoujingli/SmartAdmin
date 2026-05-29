# 📘 SmartAdmin

> 面向开源使用、二次开发、接口联调、插件扩展和私有化部署的文档工作台。先从这里确认阅读路径，再进入对应章节查看字段、示例、命令和风险边界。🚀

最后更新：2026-05-29

## 🧭 文档边界

<!--
内部维护规则：docs 是 SmartAdmin 开源交付、二次开发和接口合同的当前文档源头；代码注释、历史讨论、临时任务记录和外部说明仅作背景参考。该规则不在发布内容中展示。
-->

> 小提示：接口字段、请求示例、响应示例、权限码和错误边界以 [接口参考](接口参考/README.md) 与当前源码为准；本页用于快速找入口和理解维护边界。

公开文档站保留全生态插件文档，包括会员授权插件的接口与使用说明。标记为“会员授权”的插件不随开源仓源码或公开 Release ZIP 分发，安装使用需会员授权或内部交付。

## 🧭 系统定位

SmartAdmin 是面向“开源基础后台 + 本地业务插件 + 私有化发布”的后台框架，不是运行时远程插件市场。它把基础后台能力沉淀在 `plugin/System` 和 `zoujingli/smart-admin-library`，把业务能力放在 `plugin/<Module>`，把 Vue 管理端作为通用壳与插件编译宿主，最后可通过 `zoujingli/smart-admin-builder` 打包为 Phar/SFX 二进制。

| 维度 | 当前口径 |
|---|---|
| 开源主仓 | 普通用户和二次开发者使用 `zoujingli/smartadmin`，包含可运行后台框架、通用 Web 壳、开源插件、文档和测试。 |
| 插件扩展 | 插件通过本地 Composer path 包、Provider、`plugin.json`、菜单/节点同步和 Web 编译期扫描接入。 |
| 前端边界 | `web/apps/web-antd` 只放通用壳、公共页面和共享组件；业务插件页面、API service、`routes.ts`、`auth-entry.ts` 放插件自己的 `plugin.view_root`。 |
| 发布边界 | 源码模式可开发、安装 ZIP 插件、迁移和构建；发布二进制只运行已打包内容，不支持运行时动态安装、更新或移除插件。 |
| 文档边界 | 文档公开展示全生态能力；标记“会员授权”的插件文档可见，不代表源码或 ZIP 随开源版分发。 |

## 🧭 按角色阅读路径

| 角色 | 推荐路径 | 目标 |
|---|---|---|
| 体验用户 | [快速开始](快速开始/README.md) → [用户教程](用户教程/README.md) | 登录演示站或本地后台，理解系统基础页面。 |
| 接口联调 | [接口参考](接口参考/README.md) → [在线测试](接口参考/在线测试.md) → 具体接口页 | 确认认证方式、字段、响应壳、权限码和可调试示例。 |
| 二次开发 | [开发指南](开发指南/README.md) → [新模块开发](开发指南/新模块开发.md) → [编码规范](开发指南/编码规范.md) | 按 Controller / Service / Mapper / Model 分层新增功能。 |
| 插件开发 | [插件开发](开发指南/插件开发.md) → [插件实战教程](开发指南/插件实战教程.md) → [前端接入](开发指南/前端接入.md) | 从零完成一个可安装、可构建、可授权的业务插件。 |
| 运维部署 | [部署运维](部署运维/README.md) → [生产部署](部署运维/生产部署.md) → [发布升级](部署运维/发布升级.md) | 区分源码命令和生产二进制命令，完成发布、备份和回滚。 |
| 贡献者 | [开源协作](开源协作/README.md) → [贡献指南](开源协作/贡献指南.md) → [文档维护](文档维护/README.md) | 提交可复核的代码、文档和验证结果。 |

📌 文档、接口或菜单变更后必须运行 `composer docs:check`，接口数量和路由覆盖以检查结果为准。

## ⚡ 快速入口

| 入口 | 适合对象 | 当前口径 |
|---|---|---|
| [快速开始](快速开始/README.md) | 新用户、体验者 | 环境准备、本地启动、默认入口和常见问题 |
| [用户教程](用户教程/README.md) | 管理员、产品、测试 | 登录、工作台、组织权限、文件公告日志、租户运维 |
| [系统功能](系统功能/README.md) | 实施、架构、开发 | 认证权限、系统管理、上传、审计、租户和发布能力边界 |
| [开发指南](开发指南/README.md) | 后端、前端、插件开发 | 分层规则、接口规范、插件开发、前端接入和编码约束 |
| [接口参考](接口参考/README.md) | 前后端联调、测试 | 当前 `770` 个 HTTP 接口的字段、示例、权限和在线调试，含会员授权插件文档 |
| [部署运维](部署运维/README.md) | 运维、私有化交付 | 开发/生产部署、Docs 静态站、发布升级、缓存和日志 |
| [开源协作](开源协作/README.md) | 贡献者、维护者 | 协议、贡献、赞助、发布流程、安全披露和路线图 |

## 🧷 最新标准入口

| 标准事项 | 当前标准 |
|---|---|
| 插件资源 | 业务插件通过本地 Composer path 包、Provider、`plugin.json` 和 Web 编译期扫描接入；源码/CI 模式可使用 SmartAdminLibrary 提供的 `xadmin:plugin:*` 打包、安装、移除和备份恢复，backup 默认只备份代码，`--with-data` 才包含插件自有表，发布二进制内不出现这些命令，也不支持运行时远程安装、更新或删除插件。 |
| 文件上传 | 统一使用 `upload/runtime`、`upload/prepare`、`upload/relay`、`upload/relay-chunk`、`upload/part-sign`、`upload/complete`、`upload/abort`。 |
| 微信支付回调 | 只保留订单与退款标准回调：`/wechat-client/api/payment/notify/order/{merchantId}`、`/wechat-client/api/payment/notify/refund/{merchantId}`。 |
| 微信开放平台回调 | 授权账号消息与事件必须使用 `/wechat-service/api/callback/notify/{appid}`，由标准 URL 中的 AppID 确认消息归属。 |
| 前端菜单 | 后台动态路由统一读取 `/system/menu/user`，按钮权限统一读取 `/system/menu/permissions`。 |
| 发布升级 | 生产安装/升级使用 release 安装包，`xadmin:release:restore --install --dry-run` 先输出 SQL 和必要数据恢复计划。 |

## 📦 源码模式与发布二进制模式

| 模式 | 可以做什么 | 不应该做什么 |
|---|---|---|
| 源码模式 | 本地开发、迁移建表、菜单/节点同步、安装或恢复插件 ZIP、重新构建前端、生成 release 安装包。 | 不把源码 watch 命令交给生产进程管理器长期运行。 |
| CI 模式 | 执行 `composer analyse`、`composer test`、`composer web:build`、`composer release:check`、插件打包和发布包生成。 | 不写入生产环境数据，不把 CI 临时目录当运行备份。 |
| 发布二进制模式 | 使用 `./system-xxx --self xadmin:release:install` 或 `restore --install` 安装升级，使用 `start` 由进程管理器托管。 | 不执行 `migrate`、`xadmin:plugin:*`、rollback/fresh、生成器、docs 或 build 命令。 |

## 🔐 授权与分发矩阵

| 类型 | 文档可见 | 源码随开源仓 | ZIP 随公开 Release | 安装方式 | 示例 |
|---|---|---|---|---|---|
| 开源 | 是 | 是 | 按公开发布包提供 | Composer / Git / 本地源码 | `System`、开源基础能力 |
| 私有交付 | 可按项目约定公开或隐藏 | 否 | 否 | 项目私有 ZIP 或客户源码仓，不进入公开分发链路 | 客户定制插件 |

文档里的“会员授权”只说明能力和接口合同，不能理解为开源版已经包含对应插件源码、密钥、迁移或前端页面。

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
| 权限注解、节点同步、数据范围 | `SmartAdminLibrary` Events、`plugin/System/src/Command` | [认证权限与数据范围](系统功能/认证权限与数据范围.md) |
| 文件管理、上传通道、去重 | `plugin/System/src/Controller/FileController.php` | [文件上传与存储](系统功能/文件上传与存储.md) |
| 操作日志、请求日志、变更审计 | `SmartAdminLibrary` Events、`plugin/System/src/Controller/Logs*Controller.php` | [日志审计与公告](系统功能/日志审计与公告.md) |
| 多租户管理和租户上下文 | `plugin/System`、`TenantContext` | [租户与发布构建](系统功能/租户与发布构建.md) |
| 微信客户端 | `plugin/WechatClient` | [微信客户端接口](接口参考/微信客户端接口.md) |
| 微信开放平台（会员授权） | `plugin/WechatService` | [微信开放平台接口](接口参考/微信开放平台接口.md) |
| 项目管理（会员授权） | `plugin/Project` | [项目管理接口](接口参考/项目管理接口.md) |
| 资产管理（会员授权） | `plugin/Asset` | [资产管理接口](接口参考/资产管理接口.md) |
| 积分管理（会员授权） | `plugin/Points` | [积分管理接口](接口参考/积分管理接口.md) |
| 智能通道（会员授权） | `plugin/Smart` | [智能通道接口](接口参考/智能通道接口.md)、[智能通道标准化调用](开发指南/智能通道标准化调用.md) |
| 数据库 release 安装包、运行备份和 Phar 打包 | `SmartAdminLibrary` Command、`zoujingli/smart-admin-builder` | [发布升级](部署运维/发布升级.md) |
| Vue 3 管理端 | `web/apps/web-antd` 通用壳、`plugin/*/stc/view` 插件页面 | [前端接入](开发指南/前端接入.md) |

## 🚀 5 分钟启动

```bash
composer create-project zoujingli/smartadmin SmartAdmin
cd SmartAdmin
cp .env.example .env
composer setup
./bin/smart.php
cd web && pnpm install && pnpm dev:antd
```

如需跟踪 GitHub `master` 或提交 PR，可改用 `git clone https://github.com/zoujingli/SmartAdmin.git` 后执行 `composer install`。

默认入口：

| 入口 | 地址或账号 |
|---|---|
| 在线演示 | `https://smart.thinkadmin.top` |
| 前端 | `http://localhost:5666` |
| 后端 | `http://127.0.0.1:9501` |
| 文档 | `composer docs:serve` 后访问 `http://127.0.0.1:18100` |
| 默认账号 | `admin/admin` |

在线演示默认账号和密码均为 `admin`。演示站按 `APP_ENV=demo` 启用关键写操作保护，可体验查询和 Project 普通业务流程；系统基础资料、账号权限、全局配置、微信配置、删除、恢复和禁用类操作会被拦截。

完整启动说明见 [本地启动](快速开始/本地启动.md)。

## 🔎 公共导航

- [整体架构](架构设计/系统架构.md)
- [权限与菜单](架构设计/权限与菜单.md)
- [接口参考](接口参考/README.md)
- [在线测试](接口参考/在线测试.md)
- [插件开发](开发指南/插件开发.md)
- [智能通道标准化调用](开发指南/智能通道标准化调用.md)
- [前端接入](开发指南/前端接入.md)
- [Docs 静态站](部署运维/Docs静态站.md)
- [文档维护规范](文档维护/维护规范.md)
- [更新日志](文档维护/更新日志.md)
