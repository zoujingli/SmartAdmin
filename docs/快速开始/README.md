# 快速开始

本章帮助你从零启动 SmartAdmin。完成本章后，你应该能打开管理端、使用默认管理员登录、访问后端 API，并知道常见启动问题应该从哪里排查。

## 适合谁阅读

| 角色 | 目标 |
|------|------|
| 第一次接触项目的开发者 | 在本机启动后端、前端和文档站 |
| 准备部署的运维人员 | 理解依赖、端口、配置和初始化流程 |
| 开源体验用户 | 快速登录管理端，了解系统基础能力 |
| 贡献者 | 确认本地验证命令和文档检查方式 |

## 推荐阅读顺序

1. [环境准备](./环境准备.md)
2. [本地启动](./本地启动.md)
3. [用户教程](../用户教程/README.md)
4. [开发指南](../开发指南/README.md)

## 最小可用路径

如果本机已经具备 PHP/Swoole、MySQL、Redis、Node.js 和 pnpm，可以按下面路径启动：

```bash
composer install
cp .env.example .env
composer setup
sh bin/start-swoole start

cd web
pnpm install
pnpm dev:antd
```

另开终端启动文档：

```bash
composer docs:serve
```

## 项目启动路径

```bash
composer install
cp .env.example .env
composer setup
sh bin/start-swoole start
cd web
pnpm install
pnpm dev:antd
```

## 启动验收

| 验收项 | 通过标准 |
|--------|----------|
| 后端 | `http://127.0.0.1:9501` 可访问，登录接口返回标准响应 |
| 前端 | `http://localhost:5666` 打开登录页 |
| 登录 | `admin / admin` 可登录 |
| 菜单 | 登录后能看到系统管理、用户、角色、菜单等入口 |
| 权限 | 管理员能看到按钮，普通角色按授权展示 |
| 文档 | `http://127.0.0.1:18100` 可打开 docsify |
| 检查 | `composer docs:check` 通过 |

## 默认入口

| 入口 | 地址 | 说明 |
|------|------|------|
| 管理端 | `http://localhost:5666` | Vite 开发服务 |
| 后端 API | `http://127.0.0.1:9501` | Hyperf/Swoole 服务 |
| 文档服务 | `http://127.0.0.1:18100` | 执行 `composer docs:serve` 后访问 |
| 默认账号 | `admin / admin` | 初始化迁移创建的超级管理员 |

## 常用命令

| 命令 | 作用 |
|------|------|
| `composer setup` | 执行迁移、同步菜单、同步权限节点 |
| `composer start` | 启动后端服务 |
| `composer docs:serve` | 启动独立文档静态站 |
| `composer docs:check` | 检查 docsify 入口、导航、侧边栏规则 |
| `composer analyse` | 后端静态检查 |
| `composer test` | 后端单测 |
| `composer web:build` | 前端类型检查和构建 |
| `composer release:check` | 发布前完整检查 |

## 常见启动分支

| 你遇到的情况 | 下一步 |
|--------------|--------|
| PHP 扩展不满足 | 回到 [环境准备](./环境准备.md) 检查运行时 |
| 数据库连接失败 | 检查 `.env`、MySQL 账号权限和库名 |
| 菜单为空 | 执行 `composer setup` 或单独同步菜单/节点 |
| 前端登录 404 | 检查前端 API 地址和 Vite 代理 |
| 登录后按钮缺失 | 检查角色授权、权限节点和前端按钮 code |
| docs 点击无反应 | 执行 `composer docs:check` |

## 下一步

- 想先学会使用系统：进入 [用户教程](../用户教程/README.md)
- 想理解底层设计：进入 [架构设计](../架构设计/README.md)
- 想二次开发模块：进入 [新模块开发](../开发指南/新模块开发.md)
- 想上线部署：进入 [部署运维](../部署运维/README.md)
- 想贡献代码：进入 [贡献指南](../开源协作/贡献指南.md)

最后更新：2026-04-27
