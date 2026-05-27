# 开发指南

本章面向二次开发者，说明如何按现有分层、权限、日志、菜单和前端约定新增业务功能。

## 你会学到什么

- 如何按 Controller、Service、Mapper、Model 分层开发。
- 如何为接口添加 `#[Auth]` 和 `#[Logger]`。
- 如何同步菜单、按钮和权限节点。
- 如何让新模块接入数据范围和租户隔离。
- 如何把业务能力整理为本地插件模块，并声明本地 `composer.json` 与 `plugin.json` 双清单。
- 如何编写前端 API service 和管理页面。
- 如何补齐接口参考、用户教程和部署说明。

## 必读

- [接口规范](./接口规范.md)
- [编码规范](./编码规范.md)
- [新模块开发](./新模块开发.md)
- [插件开发](./插件开发.md)
- [前端接入](./前端接入.md)

## 推荐开发路径

```mermaid
flowchart LR
  Design["确认模块边界"] --> DB["设计表和迁移"]
  DB --> Model["Model"]
  Model --> Mapper["Mapper"]
  Mapper --> Service["Service"]
  Service --> Controller["Controller + Auth/Logger"]
  Controller --> Menu["菜单/节点同步"]
  Menu --> Plugin["插件清单/授权元数据"]
  Plugin --> Web["插件 view + Web 编译期扫描"]
  Web --> Docs["文档和接口参考"]
  Docs --> Test["验证命令"]
```

## 开发原则

- 标准 CRUD 优先继承 `CoreController`、`CoreService`、`CoreMapper`、`CoreModel`。
- Controller 只处理路由、参数、权限注解、日志注解和标准响应。
- Service 负责事务、唯一性校验、跨 Mapper 编排和业务规则。
- Mapper 负责查询、数据范围、分页、软删、状态和列表后处理。
- Model 负责表映射、fillable、hidden、关联、转换器和审计规则。
- 前端权限码、菜单 code 和后端 `#[Auth]` code 必须一致。

## 模块接入清单

| 项目 | 检查点 |
|------|--------|
| 数据库 | 是否需要 `tenant_id`、软删除、审计字段、唯一索引 |
| Model | `$fillable`、`$hidden`、关联、转换器、`$logRules` |
| Mapper | 查询白名单、分页、数据范围、列表后处理 |
| Service | 唯一性校验、事务、关系变更日志 |
| Controller | 路由、`#[Auth]`、`#[Logger]`、标准响应 |
| 插件 | 本地 `composer.json`、`plugin.json`、Provider、授权信息、view/language/migration 资源根 |
| 菜单 | 目录、菜单、按钮、code、route、component |
| 前端 | API service、页面、表单、表格、按钮权限 |
| 文档 | 用户教程、系统功能、接口参考、部署说明 |
| 验证 | docs、analyse、test、web build、release check |

## 常用命令

```bash
composer docs:check
composer analyse
composer test
composer web:build
composer release:check
./bin/smart.php xadmin:menu:sync --dry-run --json
./bin/smart.php xadmin:node:sync --dry-run --json
```

## 不要这样做

- 不要在 Controller 写 SQL 或复杂业务逻辑。
- 不要把 HTTP Request/Response 传入 Mapper。
- 不要让请求参数直接控制 select、orderBy、raw SQL、关联名或字段名。
- 不要绕过 `$fillable` 批量写入请求参数。
- 不要把请求态、用户态、租户态存在单例 Service 属性。
- 不要依赖前端隐藏按钮作为安全边界。
- 不要把密码、Token、Secret、Key 写入日志、响应、导出或文档。
- 插件新增或升级走源码合并、菜单/节点同步和前端重新构建；需要 ZIP 分发时使用 SmartAdminLibrary 提供的 `xadmin:plugin:*` 源码命令，backup 默认只备份代码，`--with-data` 才包含插件自有表，已发布二进制不支持动态安装、更新或移除插件。

最后更新：2026-05-18
