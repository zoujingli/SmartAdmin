# SmartAdmin

[![Latest Stable Version](https://img.shields.io/packagist/v/zoujingli/smartadmin.svg)](https://packagist.org/packages/zoujingli/smartadmin)
[![Total Downloads](https://img.shields.io/packagist/dt/zoujingli/smartadmin.svg)](https://packagist.org/packages/zoujingli/smartadmin)
[![Monthly Downloads](https://img.shields.io/packagist/dm/zoujingli/smartadmin.svg)](https://packagist.org/packages/zoujingli/smartadmin)
[![License](https://img.shields.io/packagist/l/zoujingli/smartadmin.svg)](https://packagist.org/packages/zoujingli/smartadmin)

基于 **Hyperf 3.2 + Swoole 6.2 + Vue 3 + TypeScript** 的高性能前后端一体化管理系统框架。

采用插件化分层架构，内置完整的 RBAC 权限体系、多租户支持和一键二进制打包能力，可将整个应用（后端 + 前端资源包）打包为**单个可执行文件**，实现低依赖部署。

## 公开仓库与安装方式

SmartAdmin 公开生态由主项目和两个 Composer 基础包组成，普通用户直接使用 `zoujingli/smartadmin` 创建项目即可运行和二次开发。

| 仓库 | Composer / 分发 | 角色边界 |
|------|-----------------|----------|
| [`SmartAdmin`](https://github.com/zoujingli/SmartAdmin) | `zoujingli/smartadmin` | 开源主项目和正常使用入口，包含可运行后台框架、Web 通用壳、公开插件、文档和测试 |
| [`SmartAdminLibrary`](https://github.com/zoujingli/SmartAdminLibrary) | `zoujingli/smart-admin-library` | 开源基础库 Composer 包，提供 Core CRUD、统一响应、权限、数据范围、租户、日志、发布升级和源码期插件 ZIP 管理能力 |
| [`SmartAdminBuilder`](https://github.com/zoujingli/SmartAdminBuilder) | `zoujingli/smart-admin-builder` | 开源构建器 Composer 包，提供 Phar/SFX 打包、配置 AST 改写、源码加固、前端资源归档和二进制产物生成能力 |

推荐通过 Composer 创建正式发布版项目：

```bash
composer create-project zoujingli/smartadmin SmartAdmin
```

需要扩展插件时，可在 SmartAdmin 源码环境通过 `xadmin:plugin:install <zip>` 安装或用 `--force` 更新 ZIP 插件，再重新执行 Composer 更新和前端构建。私有/商用插件不发布 Composer 远程包，只通过 ZIP 分发，ZIP 内的 `composer.json` 仅用于本地 path autoload。

## 快捷入口

- 文档中心：[`docs/README.md`](docs/README.md)
- 快速开始：[`docs/快速开始/README.md`](docs/快速开始/README.md)
- 用户教程：[`docs/用户教程/README.md`](docs/用户教程/README.md)
- 接口参考：[`docs/接口参考/README.md`](docs/接口参考/README.md)
- 开源协作：[`docs/开源协作/README.md`](docs/开源协作/README.md)

## 常见排障入口

- 登录/鉴权问题：[`docs/开源协作/常见问题.md`](docs/开源协作/常见问题.md)
- 权限/菜单不一致：[`docs/架构设计/权限与菜单.md`](docs/架构设计/权限与菜单.md)
- 上传异常：[`docs/系统功能/文件上传与存储.md`](docs/系统功能/文件上传与存储.md)
- 发布升级异常：[`docs/部署运维/发布升级.md`](docs/部署运维/发布升级.md)
- 日志诊断：[`docs/系统功能/日志审计与公告.md`](docs/系统功能/日志审计与公告.md)

## 特性亮点

- **高性能协程架构** — 基于 Swoole 协程引擎，单进程可处理数万并发连接
- **插件化分层设计** — SmartAdminLibrary 作为基础库、System 作为基础插件、SmartAdminBuilder 作为独立构建包，业务能力通过 `plugin/<Business>` 接入，Web 仅提供通用壳与共享运行库
- **完整权限体系** — 注解声明 + 菜单编码 + 节点注册表，三位一体的 RBAC 权限控制
- **多租户就绪** — 行级数据隔离，平台/租户空间自动切换
- **前后端一体打包** — `composer build` 复用已生成 `web/dist` 生成跨平台二进制，部署只需一个文件 + 一份 `.env`
- **前端运行时配置** — 后端动态注入 API 地址和应用标题，部署后可改无需重新构建前端
- **现代前端技术栈** — Vue 3 + Vben Admin + Ant Design Vue，Monorepo 工程化管理

## 技术栈

| 层面 | 技术选型 |
|------|----------|
| 后端框架 | Hyperf 3.2（PHP 8.4+） |
| 协程引擎 | Swoole 6 |
| 数据库 | MySQL 8.0+ |
| 缓存 | 默认 File；生产推荐 Redis（Memory / CoroutineMemory 可选，`CACHE_DRIVER` 切换） |
| 认证 | JWT（lcobucci/jwt 5.x） |
| 前端框架 | Vue 3 + TypeScript |
| UI 组件库 | Ant Design Vue（Vben Admin Pro） |
| 构建工具 | Vite + Turbo（pnpm Monorepo） |
| 包管理 | Composer（后端）/ pnpm 10+（前端） |

## 项目结构

公开 `SmartAdmin` 主仓保持可直接运行和二次开发的最小源码结构；基础库与构建器通过独立 Composer 包安装到 `vendor/`，私有/商用插件只在本地安装 ZIP 后进入 `plugin/<Module>`。

```text
.
├── bin/                          # 应用入口、统一开发脚本与 Swoole 运行时
│   ├── hyperf.php                # Hyperf 应用入口
│   ├── smart                     # 统一脚本：启动、热重载、Composer、SQLite 准备与 runtime 透传
│   ├── swoole-linux-x64          # Linux x86_64 Swoole CLI 运行时
│   ├── swoole-linux-a64          # Linux ARM64 Swoole CLI 运行时
│   └── swoole-macos-a64          # macOS ARM64 Swoole CLI 运行时
├── config/                       # Hyperf 配置
│   ├── autoload/                 # 自动加载配置（server、db、cache、jwt、release 等）
│   ├── routes.php                # 路由注册
│   └── container.php             # 容器初始化
├── plugin/                       # 公开源码插件与用户本地安装插件
│   ├── System/                   # 系统管理核心层：Controller/Service/Mapper/Model/Command/Support 与 stc 资源
│   ├── WechatClient/             # 微信公众号/支付客户端插件
│   └── WechatService/            # 微信开放平台插件
├── web/                          # 前端工程（Vue 3 Monorepo）
│   ├── apps/web-antd/            # 通用前端壳、公共页面、插件扫描和共享运行库
│   ├── packages/                 # 公共包（组件、工具、类型等）
│   ├── internal/                 # 内部构建配置
│   └── dist/                     # 本地/CI 生成的前端构建产物（不提交 Git，供打包复用）
├── docs/                         # 全生态文档静态站（会员插件仅公开文档说明）
├── composer.json                 # 后端依赖、Composer 包约束与构建脚本
└── .env.example                  # 环境配置模板
```

`zoujingli/smart-admin-library` 与 `zoujingli/smart-admin-builder` 是独立公开 Composer 包，正式环境可直接使用已发布版本；私有/商用插件通过 ZIP 安装后进入本地 `plugin/<Module>`，不属于 `SmartAdmin` 开源主仓目录。

## 快速开始

### 环境要求

| 依赖 | 版本 | 说明 |
|------|------|------|
| PHP | >= 8.4 | 需启用 swoole、zlib、bcmath、openssl、pdo_mysql、redis 扩展 |
| MySQL | >= 8.0 | 主数据存储 |
| Redis | >= 6.0 | 生产/多实例下缓存与会话推荐；本地开发可用 File 驱动 |
| Node.js | >= 20 | 前端构建 |
| pnpm | >= 10 | 前端包管理 |

Composer 脚本默认通过 `bin/smart.php runtime` 选择仓库内置 Swoole 6.2 运行时；本机没有对应架构的内置运行时时，才回退到带 Swoole/OpenSwoole 扩展的 `php`。项目统一使用 `bin/smart.php` 作为命令入口；无参数 `./bin/smart.php` 默认进入开发 watch 模式，生产或一次性前台服务请显式执行 `./bin/smart.php start`。

### 后端安装

推荐直接使用已发布的 Composer 包创建项目；这样会从 Packagist 安装 `zoujingli/smartadmin` 的正式版本，并自动安装后端依赖。

```bash
# 推荐：通过 Composer 创建正式发布版项目
composer create-project zoujingli/smartadmin SmartAdmin
cd SmartAdmin
```

如果需要跟踪 GitHub `master` 或参与贡献，也可以克隆公开仓源码：

```bash
# 可选：克隆公开仓源码，适合跟踪最新提交或提交 PR
git clone https://github.com/zoujingli/SmartAdmin.git
cd SmartAdmin
composer install
```

完成项目创建或克隆后，继续配置环境、初始化数据库并启动服务：

```bash
# 配置环境
cp .env.example .env
# 编辑 .env，填入数据库和 Redis 连接信息

# 初始化数据库、菜单与权限节点
composer setup

# 开发启动（默认进入 watch，监听源码变更并自动重启）
./bin/smart.php

# 不需要热重载或由生产进程管理器托管时，显式使用 start
./bin/smart.php start
```

如果解压或复制后执行权限丢失，先执行 `chmod +x ./bin/smart.php`；`./bin/smart.php` 默认等价于开发 watch 启动，只适合本地源码调试。生产环境请用 `./bin/smart.php start` 配合 systemd、Supervisor 或容器编排管理进程，不要使用无参数入口或 watch 模式。

### 前端安装

```bash
cd web
pnpm install
pnpm dev:antd
```

前端开发默认**直连**后端 `http://127.0.0.1:9501`（由 `apps/web-antd/.env.development` 中 `VITE_DEV_USE_PROXY=false` 控制，具体 `VITE_GLOB_API_URL` 由 `vite.config.mts` 注入）。若希望**走 Vite 代理**（避免配置 CORS），可在该文件将 `VITE_DEV_USE_PROXY` 设为 `true`，或使用 `pnpm dev:antd:proxy`；代理模式统一请求 `/api/<真实接口前缀>`，由 Vite 转发到后端真实路径。强制直连可用 `pnpm dev:antd:direct`。

### 默认访问

| 入口 | 地址 | 说明 |
|------|------|------|
| 在线演示 | https://smart.thinkadmin.top | 默认账号 `admin / admin`，演示环境限制关键数据修改、删除和禁用 |
| 前端开发 | http://localhost:5666 | Vite Dev Server |
| 后端 API | http://localhost:9501 | 以 `.env` 中 `APP_WORKER_PORT` 为准 |
| 默认账号 | admin / admin | 超级管理员 |

在线演示站使用 `APP_ENV=demo` 运行，可正常登录和体验查询、Project 普通业务流程；系统基础资料、账号权限、全局配置、微信配置、删除、恢复和禁用类操作会返回 `演示环境禁止修改关键数据`。

## 核心功能

### 系统管理（System 模块）

| 功能模块 | 能力说明 |
|----------|----------|
| **认证鉴权** | 登录 / 登出 / Token 刷新 / 用户信息获取 |
| **用户管理** | CRUD / 状态 / 密码重置 / 分配角色 / 分配部门 / 分配岗位 |
| **角色管理** | CRUD / 状态 / 权限节点分配 / 查看关联用户 |
| **菜单管理** | 树形 CRUD / 排序 / 用户菜单 / 权限按钮 |
| **部门管理** | 树形 CRUD / 排序 / 部门成员 |
| **岗位管理** | CRUD / 状态 / 排序 |
| **租户管理** | 租户资料 / 状态 / 有效期 / 租户管理员开通 |
| **文件管理** | 文件记录 / 上传配置 / 回收站 / 统计去重 |
| **通知中心** | 公告发布 / 接收人投放 / 收件箱 / 已读归档 |
| **日志管理** | 查询 / 导出 / 清理 / 归档 / 统计分析 |
| **系统参数** | 运行参数 / 界面配置 / 安全配置脱敏 |
| **系统数据** | 系统配置 / JSON 文档存储 / 能力矩阵 |

### 权限体系

采用三层权限模型，注解声明与数据库存储相结合：

```text
┌─────────────────────────────────────────────────┐
│  权限来源                                        │
│  ┌─────────────┐    ┌──────────────────┐        │
│  │ @Auth 注解   │    │ plugin.json code │        │
│  └──────┬──────┘    └────────┬─────────┘        │
│         └──────┬─────────────┘                   │
│                ▼                                 │
│  ┌──────────────────────┐                        │
│  │ system_node 权限节点  │ ← xadmin:node:sync
│  └──────────┬───────────┘                        │
│             ▼                                    │
│  ┌──────────────────────┐                        │
│  │ system_role_node     │ ← 角色分配节点          │
│  └──────────┬───────────┘                        │
│             ▼                                    │
│  ┌──────────────────────┐                        │
│  │ AuthAspect 运行时校验 │ ← 请求拦截              │
│  └──────────────────────┘                        │
└─────────────────────────────────────────────────┘
```

- **操作权限**：通过 `#[Auth]` 注解声明接口级访问控制
- **数据权限**：通过 Scope 机制控制查询可见范围（全部 / 本部门 / 本人）
- **节点权限**：角色绑定权限节点，用户权限由角色自动聚合
- **超管通配**：超级管理员持有 `*` 节点，短路所有权限校验

### 多租户（System 内置能力）

- 共享库、共享表、行级隔离策略
- 统一 `tenant_id` 字段自动注入与过滤
- 平台空间与租户空间自动切换
- 合入 System 的租户管理接口（`/system/tenant/*`）
- 租户菜单基线通过注册表聚合

### 前端功能

基于 Vben Admin Pro 深度定制，已实现以下页面：

- 用户管理（列表、新增、编辑、查看、角色/部门/岗位分配）
- 角色管理（列表、权限树分配）
- 菜单管理（树形列表、新增、编辑）
- 部门管理（树形列表、成员管理）
- 岗位管理（列表、CRUD）
- 日志管理（查询、详情、监控）
- 系统总览（运行信息、能力矩阵）

## API 接口规范

所有接口遵循 RESTful 风格，统一响应格式：

```json
{
  "code": 200,
  "info": "操作成功",
  "data": {
    "items": [],
    "pageInfo": { "total": 0, "totalPage": 0, "currentPage": 1 }
  },
  "path": "/system/user/index"
}
```

HTTP status 固定返回 `200`，body.code 约定：`200` 成功、`401` Token 缺失/过期/无效或刷新失败、`403` 有效 Token 无操作权限、`404` 页面或 API 路由不存在、`500` 业务异常/校验失败/数据不存在。统一响应由 `ResponseExceptionHandler` 输出，路由 404 不单独维护第二套处理器。

标准 CRUD 接口路径：

| 操作 | 方法 | 路径 |
|------|------|------|
| 列表 | GET | `/{module}/{resource}/index` |
| 详情 | GET | `/{module}/{resource}/info/{id}` |
| 创建 | POST | `/{module}/{resource}/create` |
| 更新 | PUT | `/{module}/{resource}/update/{id}` |
| 删除 | DELETE | `/{module}/{resource}/delete/{ids}` |
| 状态 | PUT | `/{module}/{resource}/status/{id}` |
| 选项 | GET | `/{module}/{resource}/options` |
| 统计 | GET | `/{module}/{resource}/statistics` |

## 分层架构

### Library — 基础能力层

不绑定具体业务类型的通用底座，提供：

- **四大基类**：`CoreController` / `CoreService` / `CoreMapper` / `CoreModel`
- **认证体系**：JWT 签发与校验、Token 黑名单、会话管理
- **注解系统**：`#[Auth]` 权限注解、`#[Logger]` 日志注解
- **切面拦截**：`AuthAspect` 权限校验、`LoggerAspect` 自动日志
- **统一异常**：标准化错误响应（`SuccessResponseException` / `ErrorResponseException` 等）
- **工具集合**：查询构建器、数组树处理、表单校验、请求解析
- **中间件**：CORS 跨域、请求日志、站点静态服务（`SiteMiddleware`）
- **全局函数**：`syspath()` / `runpath()` / `_once()` / `_query()` / `_cache()` 等
- **租户上下文**：`TenantContext` 协程安全的租户标识管理

### System — 系统管理核心层

任何后台项目都需要的标准管理能力，并内置平台租户管理：

- 12 个控制器覆盖认证、用户、角色、菜单、部门、岗位、租户、文件、日志、公告、数据、设置
- 完整的 Service / Mapper / Model 三层实现
- `plugin.json` 菜单基线 + 权限节点同步机制
- 数据权限范围与租户范围自动注入

### SmartAdminBuilder — 构建工具

独立 Composer 包 `zoujingli/smart-admin-builder` 提供 Phar 打包与 SFX 二进制生成：

- 智能文件过滤（排除测试、文档等非运行时文件）
- AST 级代码改写（适配 Phar 流路径）
- 外部挂载机制（`.env` 运行时读取）
- 前端资源自动打包

## 构建与部署

### 一键构建

SmartAdmin 支持将整个应用打包为独立可执行文件：

```bash
# 一键打包（复用并校验已有 web/dist，同步菜单与权限节点，并预编译 Hyperf 容器缓存）
export COMPOSER_ALLOW_SUPERUSER=1
composer web:build
composer build
```

构建产物：

```text
build/
├── system-linux-x64    # Linux x86_64    (~147 MiB)
├── system-linux-a64    # Linux ARM64     (~131 MiB)
└── system-macos-a64    # macOS ARM64     (~122 MiB)
```

### 构建流程

```text
源码 + vendor(优化过滤) + 已生成 web/dist(通用壳 + 当前已安装插件前端)
                │
                ▼
runtime/container/*.cache + build.manifest.json
                │
                ▼
         system.bin (Phar + 预编译缓存)
                │
                ▼
      SFX 合并 Swoole 运行时
                │
                ▼
  system-linux-x64 / system-linux-a64 / system-macos-a64
```

| 步骤 | 说明 |
|------|------|
| 前端产物 | `composer build` 只复用并校验已有 `web/dist/index.html`；打包前需先执行 `composer web:build` 或由 CI 提供等价产物 |
| 基线同步 | 执行 `xadmin:menu:sync` 与 `xadmin:node:sync` |
| 清理环境 | 清理 `build/` 产物、临时 Phar 与 `runtime/container` 预编译缓存 |
| 安装生产依赖 | `composer install --no-dev`，仅运行时依赖 |
| 发布安装包 | 执行 `xadmin:release:backup --install`，生成 `storage/extra/release` 数据库安装包并打入 Phar |
| 预编译缓存 | 预生成 `scan.cache`、`classes.cache`、`aspects.cache` 与构建清单，降低二进制首次启动扫描成本 |
| Phar 打包 | 源码 + 依赖 + 前端资源打入归档，自动过滤测试/文档 |
| SFX 合并 | Phar 与 Swoole 运行时拼接为可执行二进制 |
| 恢复开发环境 | 重新安装完整依赖 |

### 发布指令合集

| 命令 | 用途 |
|------|------|
| `composer release:check` | 发布前完整检查：静态分析、单测、前端构建、安装包生成与恢复 dry-run |
| `composer release:backup` | 生成 `storage/extra/release` 数据库安装包（结构 + 必要数据） |
| `composer release:restore:dry-run` | 基于安装包预览 `restore --install` SQL 与必要数据恢复计划 |
| `composer release:snapshot` | 连续执行安装包生成与 `restore --install --dry-run` |
| `composer build:web` | 仅构建前端产物 |
| `composer build:sync` | 同步菜单、权限、模型和结构索引 |
| `composer build:clean` | 清理发布产物和容器预编译缓存 |
| `composer build:install-prod` | 安装生产依赖 |
| `composer build:snapshot` | 生成 `storage/extra/release` 发布安装包 |
| `composer build:precompile` | 预编译 Hyperf 容器缓存并生成构建指纹 |
| `composer build:phar` | 构建 Phar 并合并 Swoole 运行时 |
| `composer build:audit` | 审计 SFX/Phar 产物、预编译缓存和前端资源包 |
| `composer build:cleanup` | 清理临时 Phar 文件 |
| `composer build:restore-dev` | 恢复开发依赖环境 |
| `composer build` | 调用 `.php-sfx-packer.php build` 执行完整发布打包，复用并校验已有 `web/dist` |

> `.php-sfx-packer.php` 是标准打包编排器，支持 `build`、`precompile`、`audit`、`pack` 四种模式；单独合并 SFX 时使用 `@php .php-sfx-packer.php pack system.bin build/system`。

### Swoole 基库

项目内置 `bin/swoole-linux-x64`、`bin/swoole-linux-a64`、`bin/swoole-macos-a64` 为精简 PHP 8.4 + Swoole 6.2 SFX 运行时，构建审计会校验必要扩展与禁用扩展。若需要自定义扩展或重新构建基库，可参考 [zoujingli/phpsfx](https://github.com/zoujingli/phpsfx)；本仓库不内置 phpsfx 构建工具。

### 最小化部署

最小启动只需两个文件即可运行；数据库安装包已经打入 Phar，运行备份默认写入二进制同级 `runtime/backup`：

```text
/opt/smartadmin/
├── system-linux-x64    # 可执行文件
└── .env                # 环境配置
```

```bash
chmod +x system-linux-x64
./system-linux-x64 --self xadmin:release:install
./system-linux-x64 --self start
```

> Swoole CLI 的 SFX 包装层需要通过 `--self` 进入追加的应用 Phar；二进制模式下执行应用命令统一使用 `./system-xxx --self <command>`。

启动后自动创建：

```text
/opt/smartadmin/
├── system-linux-x64
├── .env
├── public/             # Swoole 静态文件根目录（自动创建）
└── runtime/            # 日志、缓存、PID（自动创建）
```

### 环境配置

所有运行参数通过 `.env` 控制，无需修改代码：

```ini
# 服务端口（默认 9501）
APP_WORKER_PORT=9501

# 工作进程数（默认 = CPU 核心数，最小 2）
APP_WORKER_NUMS=4

# 前端应用标题（可选，动态注入）
APP_TITLE=我的管理系统

# 前端 API 地址（可选，默认同源 /）
APP_API_URL=/

# 数据库 / Redis
DB_HOST=127.0.0.1
DB_DATABASE=smartadmin
REDIS_HOST=127.0.0.1
```

### 前端动态配置

`SiteMiddleware` 拦截 `/_app.config.js` 请求，将 `.env` 中的 `APP_TITLE`、`APP_API_URL` 动态生成为 JavaScript 注入前端运行时，实现**部署后修改前端行为而无需重新构建前端**。

### 静态资源加速

Phar 内部携带 `storage/extra/web-dist.zip` 和 `storage/extra/release/*`，不会再打入 raw `web/dist` 目录，也不依赖外置数据库安装包目录。Phar 首次 `start` 且 `public/index.html` 缺失时会自动发布；也可以手动发布到 `public/` 目录，由 Swoole 原生处理器直接服务：

```bash
# 预览
./system-linux-x64 --self xadmin:website:publish --dry-run

# 发布（自动跳过 _app.config.js，保留动态配置）
./system-linux-x64 --self xadmin:website:publish

# 回退
./system-linux-x64 --self xadmin:website:publish --clean
```

### 路径系统

两个全局函数确保开发环境与 Phar 环境行为一致：

| 函数 | 开发环境 | Phar 环境 | 用途 |
|------|----------|-----------|------|
| `syspath()` | 项目根目录 | `phar://` 包内 | 读取内部文件（配置、模板、前端） |
| `runpath()` | 项目根目录 | 二进制同级目录 | 读写运行时数据（日志、缓存、上传） |

## 开发指南

### 新模块接入流程

1. 在对应插件的 `stc/migrations` 维护开发期基线迁移，并由 `plugin.migration_root` 显式启用
2. 新增 Model / Mapper / Service / Controller（分别继承四大基类）
3. 添加 `#[Auth]` 权限注解和 `#[Logger]` 日志注解
4. 在插件 `plugin.json` 声明应用、菜单、按钮权限、模块摘要和相对 `view`
5. 将前端页面、API service、`routes.ts` 或 `auth-entry.ts` 放入插件自己的 `plugin.view_root`
6. `Provider` 仅注册注解扫描、依赖、监听器和运行期命令等装配能力
7. 在源码/CI 环境执行迁移，并按需执行 `xadmin:menu:sync`、`xadmin:node:sync`；插件分发可使用 SmartAdminLibrary 提供的 `xadmin:plugin:*` 源码命令
8. 若发布二进制，先生成 `web/dist`，再执行 `composer build`

### 源码插件管理命令

`xadmin:plugin:*` 命令由 SmartAdminLibrary 提供，仅源码/CI 模式出现；发布 Phar/SFX 二进制不会注册这些命令。普通插件包文件名固定为 `plugin-插件code-插件版本.zip`（如 `plugin-demo-1.0.0.zip`），ZIP 顶层包含 `composer.json`、`plugin.json`、`src/`、`stc/` 等；备份包默认包含插件代码和 `_xadmin/plugin-backup.json`，只有指定 `--with-data` 或执行 remove 自动备份时才额外包含数据库结构与数据快照。插件自有表通过 `plugin.tables`、`plugin.table_prefixes` 或 `plugin.code` 下划线前缀识别。

```bash
./bin/smart.php xadmin:plugin:package <PluginCode> -o runtime/plugin/packages -p <zip密码>
./bin/smart.php xadmin:plugin:install runtime/plugin/packages/plugin-<plugin-code>-1.0.0.zip -p <zip密码>
./bin/smart.php xadmin:plugin:backup <PluginCode> --with-data -p <zip密码>
./bin/smart.php xadmin:plugin:remove <PluginCode> -p <备份zip密码>
./bin/smart.php xadmin:plugin:restore <plugin-code>-1.0.0-backup-20260522-123000 -p <zip密码> --force
```

备份默认写入 `runtime/plugin/backups`，文件名为 `插件code-版本号-backup-YYYYMMDD-HHMMSS.zip`；恢复仅传文件名时从该目录读取，且 `.zip` 可省略，传绝对路径或带目录的相对路径时按指定路径读取。带数据备份恢复时默认恢复数据，可用 `--no-data` 只恢复代码。安装/恢复会维护根 `composer.json` 的 path repository 与 require，并执行 Composer 更新；若插件包含 `plugin.view_root`，仍需执行 `composer web:build` 重新生成前端产物。`SmartAdminLibrary` 属于基础库、`System` 属于基础插件，`SmartAdminBuilder` 属于基础构建包，不允许通过插件管理命令移除或覆盖。

### 常用命令

```bash
# 开发启动（默认 watch，监听 .env / PHP 变更）
./bin/smart.php

# 显式开发热重载（与无参数入口等价）
./bin/smart.php watch

# 一次性前台启动（无 watch，适合进程管理器）
./bin/smart.php start

# 数据库迁移
./bin/smart.php migrate

# 全量重建基线
./bin/smart.php migrate:fresh

# 菜单与权限节点同步
./bin/smart.php xadmin:menu:sync --details
./bin/smart.php xadmin:node:sync --details

# 生成发布运行备份（结构 + 必要数据）
./bin/smart.php xadmin:release:backup

# 生成待打包安装包（结构 + 必要数据，composer build 默认执行）
./bin/smart.php xadmin:release:backup --install

# 发布包首次安装（正式二进制使用）
./system-linux-x64 --self xadmin:release:install --dry-run
./system-linux-x64 --self xadmin:release:install

# 发布升级预览 / 执行（正式二进制使用）
./system-linux-x64 --self xadmin:release:restore --install --dry-run --json
./system-linux-x64 --self xadmin:release:restore --install

# 运行全量备份与恢复
./system-linux-x64 --self xadmin:release:backup --with-data
./system-linux-x64 --self xadmin:release:restore --with-data --dry-run --json
./system-linux-x64 --self xadmin:release:restore --with-data

# 模型生成
./bin/smart.php xadmin:build:model

# 代码格式化 + 静态分析
composer sync

# 构建前端
composer web:build

# 打包二进制
composer build
```

## 文档索引

| 分类 | 文档 | 说明 |
|------|------|------|
| 入口 | [文档首页](docs/README.md) | 文档统一入口 |
| 快速开始 | [快速开始](docs/快速开始/README.md) | 环境准备与本地启动 |
| 用户教程 | [用户教程](docs/用户教程/README.md) | 登录、权限、文件、公告、日志、租户等使用教程 |
| 系统功能 | [系统功能](docs/系统功能/README.md) | 认证、权限、上传、日志、租户、发布构建说明 |
| 接口参考 | [接口参考](docs/接口参考/README.md) | 按控制器整理的接口清单 |
| 架构设计 | [系统架构](docs/架构设计/系统架构.md) | 分层模型与职责边界 |
| 架构设计 | [权限与菜单](docs/架构设计/权限与菜单.md) | 权限节点与菜单机制 |
| 开发指南 | [接口规范](docs/开发指南/接口规范.md) | RESTful 与统一响应规范 |
| 开发指南 | [编码规范](docs/开发指南/编码规范.md) | 分层开发与注解约束 |
| 部署运维 | [生产部署](docs/部署运维/生产部署.md) | 生产环境部署建议 |
| 部署运维 | [发布升级](docs/部署运维/发布升级.md) | release 安装包、运行备份、升级恢复和 Phar 构建 |
| 部署运维 | [Docs 静态站](docs/部署运维/Docs静态站.md) | 独立文档站部署与检查 |
| 文档维护 | [文档维护](docs/文档维护/README.md) | 文档维护、检查和更新规则 |
| 模块文档 | [模块文档](docs/模块文档/README.md) | 模块能力、权限清单和前后端对接要点 |
| 运维手册 | [运维手册](docs/运维手册/README.md) | 缓存、迁移、日志和异常等细分运维主题 |

## License

SmartAdmin is open source software licensed under the [Apache License 2.0](LICENSE).
Third-party components keep their own licenses; key attribution details are kept in [NOTICE](NOTICE).
