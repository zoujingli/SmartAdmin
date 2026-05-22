# 系统管理基础插件

提供用户、角色、菜单、部门、岗位、字典、日志、文件、公告、系统参数与租户管理等后台基础能力。

## 目录规范

- `src/`：插件运行期 PHP 代码，包含 `Controller`、`Service`、`Mapper`、`Model`、`Support` 等目录。
- `stc/view/`：插件前端页面与组件资源，由 `plugin.view_root` 显式启用。
- `stc/languages/`：插件语言包，由 `plugin.language_root` 显式启用。
- `stc/migrations/`：插件开发期数据库迁移文件，由 `plugin.migration_root` 显式启用。
- `composer.json`：插件包元数据、autoload 与 Hyperf Provider 配置。
- `plugin.json`：应用、菜单、view、按钮权限和模块元数据清单。

## 约束

运行期类命名空间保持不变，仅通过 composer autoload 将命名空间根目录指向 `src/`。
页面、语言包和迁移目录均由 `plugin.json` 的资源根字段声明；未配置的资源不会被运行时或构建流程自动扫描。
