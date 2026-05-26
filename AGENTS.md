# AGENTS.md

本文件是 AI 工具参与本项目开发时的基础规则。修改代码前先阅读本文件；当规则与局部代码风格冲突时，优先保持现有模块风格，并补充必要说明。

## 项目定位

- 本项目是基于 Hyperf 3.2、Swoole 6.2、PHP >= 8.4 的通用开源后台架构。
- 目标是低成本接入新模块，同时保证权限、数据范围、租户隔离、发布升级和性能默认安全。
- 后端代码主要位于 `plugin/*/src`，业务插件前端、语言包、迁移等资源位于 `plugin/*/stc`，主前端 `web/apps/web-antd` 只作为通用壳、公共页面、共享组件和编译期插件宿主。
- 插件通过本地 Composer path 包、Provider、`plugin.json`、菜单/节点同步和 Web 编译期扫描接入；源码/CI 模式下由 Library 内置 `xadmin:plugin:package/install/remove/backup/restore` 辅助打包、安装、移除和备份恢复，backup 默认只备份代码，显式 `--with-data` 才备份插件自有表，remove 自动备份必须带数据。命令必须使用 `SourceOnlyCommand`，发布 Phar/SFX 内不出现这些命令，也不是运行时远程插件加载。插件前端变更后需要重新生成前端产物。
- 业务插件命令必须明确源码期还是运行期：构建、调试和高风险清理命令必须使用 `SourceOnlyCommand`；确需在生产定时或手动执行的业务运行命令可以保留，但文档要说明边界和风险。
- 仓库与发布链仅使用 GitHub：`SmartAdminDeveloper` 为私有全量开发源，也是唯一生态维护入口；TAG Actions 自动同步 `SmartAdminLibrary`、`SmartAdminBuilder` 与 `SmartAdmin`；不得再配置其它代码托管地址或发布说明。普通用户只使用公开 `SmartAdmin` / `zoujingli/smartadmin` 运行和二次开发。
- Composer 包名统一使用横线规则：主包为 `zoujingli/smartadmin`，基础库为 `zoujingli/smart-admin-library`，构建器为 `zoujingli/smart-admin-builder`，插件为 `zoujingli/smart-plugin-xxx`；私有/商用插件不发布 Composer 远程包，只通过 `xadmin:plugin:*` ZIP 分发，ZIP 内 `composer.json` 仅服务本地 path autoload。
- AI 修改时优先复用现有基类和注册机制，不新增重复框架层。

## 分层标准

- `Controller` 只负责路由、参数读取、权限注解、操作日志注解和标准响应。
- `Service` 负责业务编排、事务、唯一性校验、跨 Mapper 调用和领域规则。
- `Mapper` 负责数据访问、查询条件、数据范围、分页、软删、状态变更和列表后处理。
- `Model` 只负责表结构映射、fillable、隐藏字段、关联、访问器和转换器。
- 标准 CRUD 优先继承 `CoreController`、`CoreService`、`CoreMapper`、`CoreModel`。
- `Library/System` 是基础插件，`SmartAdminBuilder` 是独立构建器 Composer 包；新业务能力优先放在 `plugin/<Business>`，不要把业务功能继续堆入 `System` 或 `web/apps/web-antd`。
- 应用插件推荐目录结构：`plugin/<Module>/src/Controller`、`src/Service`、`src/Mapper`、`src/Model`、`src/Support`、`src/Provider.php`，静态与资料目录可使用 `stc/view`、`stc/languages`、`stc/migrations`，并由 `plugin.view_root`、`plugin.language_root`、`plugin.migration_root` 显式启用。
- 不要把 SQL 查询写在 Controller；不要把 HTTP Request、Response 传入 Mapper。

## Controller 规则

- Controller 类使用 `final class XxxController extends CoreController`。
- 路由使用 Hyperf 注解：`#[Controller]`、`#[GetMapping]`、`#[PostMapping]`、`#[PutMapping]`、`#[DeleteMapping]`。
- 每个受保护接口必须有 `#[Auth]`，权限码要与菜单/节点对应，不能复用无关模块权限码。
- 写操作必须加 `#[Logger]`，敏感字段使用 `excludeFields`。
- 响应使用 `$this->success()`、`$this->error()`、`$this->respondFound()`、`$this->deleteByIds()`。
- 标准响应体固定为 `code/info/data/path`，`info` 是项目标准消息字段；自定义响应直接抛 `BaseResponseException`，不要在 Controller 中直接 `json()`。
- HTTP status 固定返回 `200`，标准业务码只允许写入 body.code：`200/401/403/404/500`。
- 统一响应由 `ResponseExceptionHandler` 处理，包含业务响应异常和 Hyperf 路由 404；不要再新增独立 404 响应处理器。
- `401` 仅表示 Token 缺失、过期、无效或刷新失败；账号密码错误、账号禁用等登录业务失败必须返回 `500`。
- `403` 仅表示 Token 有效但无操作权限；业务校验失败、状态不允许、数据不存在必须返回 `500`。
- `404` 仅表示页面或 API 路由不存在；业务数据、文件、父级记录不存在必须返回 `500`。
- 控制器只做轻量参数整理，复杂校验和业务逻辑下沉到 Service。

## Service 规则

- Service 类使用 `final class XxxService extends CoreService`。
- 标准 Service 通过构造函数注入 `protected XxxMapper $mapper`。
- 新增/更新数据通过 `create()`、`update()` 和 `filterData()` 做统一过滤。
- `filterData()` 是写入数据验证与过滤的标准入口；优先直接使用 `_vali($rules, $data)` 返回过滤后的数据，再追加唯一性、跨表、状态流转等业务校验。
- `_vali()` 只应在 Service 层或更底层业务支持类中使用；Controller 不做字段级业务验证，只做路由参数轻量整理和响应。
- 唯一性校验优先使用 `ensureUniqueField()`。
- 涉及多表写入必须显式事务处理。
- Swoole 下不要在单例 Service 属性中保存请求态、用户态、租户态、Token 用户模型上下文等可变状态；使用方法参数或协程 `Context`。

## Mapper 规则

- Mapper 类使用 `final class XxxMapper extends CoreMapper`。
- 标准 Mapper 通过构造函数声明 `protected string $model = XxxModel::class`。
- 列表查询优先使用 `getPageList()`、`getDataList()`、`makeQuery()`、`handleSearch()`、`handleListItems()`。
- `handleSearch()` 的常规筛选优先使用 `_query($query, $params)` 统一处理 `like/equal/in/dateBetween` 等白名单条件；特殊查询只在必要时手写，并显式说明范围边界。
- 读、改、删、恢复、启停默认走 `CoreMapper` 的数据范围保护方法，不要直接 `Model::find()` 后写入。
- 特殊原生查询必须显式处理数据范围；无用户上下文时必须 fail closed。
- 对外请求参数不能直接控制 select、orderBy、raw SQL、关联名或字段名；需要白名单。
- 查询别名和表达式必须在代码中注册，不能让请求直接传入表达式。

## Model 规则

- Model 类使用 `final class Xxx extends CoreModel`。
- 表字段必须写入 `$fillable`；敏感字段写入 `$hidden`。
- 有软删除字段时使用 `SoftDeletes`。
- JSON 字段使用 `_toJson()`、`_toArray()` 做统一转换。
- 有 `tenant_id` 且在 `$fillable` 中时会自动启用租户范围；不要绕过租户上下文做普通业务查询。
- 关联关系写在 Model，列表展示转换写在 Mapper 或 Service。

## 权限与数据范围

- 接口权限以 `#[Auth]`、`plugin.json` 菜单清单、权限节点同步为准。
- 新模块菜单通过 `plugin.json` 清单与 Registry 注册，避免写死在 System 中央配置。
- 数据权限默认 fail closed；拿不到用户上下文不能返回全量数据。
- 部门数据范围必须明确使用用户字段或部门字段，不能让 `deptField` 参数空转。
- 角色数据范围枚举必须与数据库、前端表单、后端校验保持一致。
- 多角色数据范围策略必须在代码和注释中保持一致，不能注释写“最严格”但实现取最宽。
- 用户选项、导出、统计、详情、更新、删除等非标准接口也必须套数据范围。

## 租户规则

- 登录后必须建立租户上下文，普通业务查询依赖 `TenantContext` 和 `CoreModel` 租户范围。
- 超级管理员逻辑要显式判断，不要让租户 ID 默认为 0 后误用平台数据。
- 新模块如支持租户，表必须包含 `tenant_id`，Model `$fillable` 必须包含该字段。

## 日志与敏感数据

- 操作日志和全局请求日志都必须脱敏。
- 操作日志包含三个主体：`change_data` 变更、`request_data` 请求、`response_data` 响应；列表和详情首屏以变更日志为主。
- 关键业务 Model 如需审计必须定义 `$logRules`，字段展示格式为 `字段中文名(字段代码)旧值改为新值`。
- 枚举字段必须配置 `values`，展示为 `映射文本(原始值)`；数量、金额、年龄、比例等字段必须配置 `unit`。
- 角色授权、用户角色/部门/岗位分配、公告接收人等关系变更不会稳定触发模型字段事件，必须手动追加变更记录。
- 脱敏规则要支持点路径，例如 `drivers.oss.access_secret`。
- 全局请求日志默认记录请求和响应 body 预览；大内容必须只读取并保存长度限制内的内容，超出部分用 `...` 截断，且必须脱敏。
- 密码、Token、Secret、Key、Cookie、Authorization、上传签名等不得明文入库或入日志。

## Release 数据库规则

- `migrations` 主要用于开发期建表和 fresh 初始化。
- 打包发布升级使用 DBAL 快照机制，不依赖迁移文件执行生产升级。
- 正式 Phar/SFX 二进制不注册 `migrate`；首次安装和发布升级统一使用 `xadmin:release:install` 或 `xadmin:release:restore --install` 从包内安装包恢复。
- 发布配置只允许使用 `config/autoload/release.php` 中的 `backup_tables` 和 `ignore_tables`。
- `backup_tables` 是发布包必要数据表；不加 `--with-data` 时只备份和恢复这些必要数据。
- `ignore_tables` 不进入必要数据；`ignore_tables` 优先级高于 `backup_tables`。结构快照仍包含当前数据库全部表结构，正式恢复不再依赖迁移文件。
- 安装包固定为 `storage/extra/release/database.schema.gz`、`database.data.gz`、`database.meta.json`，由 `xadmin:release:backup --install` 生成并打入 Phar。
- 运行备份默认写入 `runtime/backup/<timestamp>/`，并维护 `runtime/backup/latest` 指向最新备份。
- `xadmin:release:backup` 默认生成运行备份（结构 + 必要数据）；`--with-data` 生成结构 + 全量数据；`--install` 生成待打包安装包；`--install --with-data` 禁止。
- `xadmin:release:restore` 默认从最新运行备份恢复结构 + 必要数据；`--with-data` 从全量运行备份恢复结构 + 全量数据；`--install` 从包内安装包恢复；`--install --with-data` 禁止。
- `xadmin:release:install` 用于正式发布包一键安装，包含 `restore --install` 和前端 publish。
- 历史发布升级专用命令已删除，发布升级统一使用 `xadmin:release:restore --install`。
- `composer release:check` 必须覆盖 release install package backup 和 restore install dry-run。
- 发布构建入口使用 `composer build`；分阶段命令使用 `composer build:web`、`build:sync`、`build:clean`、`build:install-prod`、`build:snapshot`、`build:phar`、`build:cleanup`、`build:restore-dev`。

## 前端对应规则

- 后端新增权限码、菜单、数据范围枚举、接口参数时，必须同步检查对应插件 `plugin.view_root` 页面和必要的 Web 通用运行库。
- `web/apps/web-antd` 只放通用壳、公共页面、共享组件和插件扫描/鉴权/路由消费逻辑；具体业务页面、插件私有 API service、`routes.ts`、`auth-entry.ts` 应放在对应插件目录。
- 前端表单必须提交后端必需字段，不能依赖后端危险默认值。
- 列表和表格需要设置合理最小宽度，避免内容被挤压。
- 所有 UI 必须适配亮色、暗色和主题色切换；自定义页面、卡片、空状态、图表、标签、悬浮层、边框、阴影、状态色等视觉样式优先使用 Ant Design/Vben 主题 token 或现有 CSS 变量（如 `--ant-colorText`、`--ant-colorBgContainer`、`--ant-colorBorderSecondary`、`--ant-colorPrimary` 及 `hsl(var(--...))` 兜底），不得写死只适配单一主题的颜色、背景、边框或阴影。
- 所有存量和新增列表页、报表页、日志页、配置列表的搜索/筛选区必须统一使用 `#/components/crud-search-field.vue` 包裹 `Input`、`Select`、`DatePicker`、`RangePicker`、`InputNumber` 等控件；标签固定 4 个汉字并放在控件前缀视觉位。多筛选项页面使用 `<Row class="crud-search-grid">` 的统一宽度机制，按钮放入最后一个 `<Col class="crud-search-grid__actions">`，不使用 `block` 拉满，不再靠不同 `:xl` 人工拉长日期范围或关键字；下拉提示最小宽度保持输入框宽度，按内容自适应，最大不超过 2 倍输入框宽度。新增/编辑/详情表单仍使用 `FormItem label + 控件`。
- 表格右侧操作列统一使用 `#/components/crud-table-actions.vue`，并用 `estimateVisibleActionColumnWidth()` 估算固定列宽；平行按钮最多 3 个，超过 3 个必须自动收纳为前 2 个操作 + `更多` 下拉，删除/彻底删除/取消等危险操作放在数组最后并保留二次确认。
- 搜索、刷新、导出、保存、同步、删除、批量处理和登录等异步按钮必须有 `loading` / `confirm-loading` / pending 锁，防止重复点击；表格行异步操作必须让 `CrudTableActions` 等待 Promise，危险操作必须保留确认。
- 前端权限码必须与后端 `#[Auth]` code 保持一致。
- 列表导出统一使用前端 `exportCrudXlsx()`：前端先弹出确认层，再按当前筛选条件分页调用现有列表接口取数，最后在浏览器生成 `.xlsx` 自动下载；后台只负责列表数据、权限、数据范围和租户隔离，不新增或恢复 `*/export` 后端导出接口。

## 代码规则

- PHP 文件必须使用 `declare(strict_types=1);`。
- 新类优先使用 `final class`，抽象基类除外。
- 使用构造函数依赖注入，避免服务定位器式散落调用。
- 主流程优先线性可读；只调用一次且逻辑少于 10 行的 `private`/`protected` 方法必须直接内联，不得为了测试或形式整洁而拆分。
- 只有在确实降低重复、隔离复杂分支或保护职责边界时，才允许新增辅助方法、类或抽象层。
- 默认使用 ASCII；已有中文文档、注释、业务文案可以继续使用中文。
- AI 新增代码，以及修改后新增或调整的关键逻辑，必须补充标准中文注释；说明职责、关键分支、边界条件、异常场景和业务约束，不重复代码字面意思。
- 对外接口、结构化返回、缓存、权限、状态流转、金额计算、时间范围、兼容逻辑等易歧义代码，必须写清输入输出约束和业务语义。
- 注释只解释业务约束或复杂逻辑，不写显而易见的注释。
- 在兼容项目现状的前提下，优先使用 PHP 8.4 语法，但不得破坏既有风格和业务语义。
- 不要引入请求态单例属性；Hyperf/Swoole 环境必须考虑协程并发。
- 不要新增不可控 raw SQL；必须 raw 时要参数绑定并说明原因。
- 不要绕过 fillable 批量写入请求参数。
- 不要执行 `git reset --hard`、`git checkout --` 等破坏性命令，除非用户明确要求。

## 验证命令

- 若已有同类测试且本次修改影响关键业务逻辑，必须优先按现有风格补齐或调整就近测试，不扩散到无关模块。
- PHP 语法检查使用 mac 运行时：`./bin/swoole-macos-a64 -l <file>`。
- 后端静态检查：`composer analyse`。
- 后端单测：`composer test`。
- 前端构建检查：`composer web:build`。
- 发布完整检查：`composer release:check`。
- Hyperf 命令优先使用：`./bin/swoole-macos-a64 bin/hyperf.php <command>`。

## 提交规则

- 提交前运行与改动范围匹配的验证命令。
- 提交信息使用中文描述，并带模块前缀，例如 `发布: ...`、`权限: ...`、`前端: ...`、`文档: ...`。
- 不同模块尽量拆分提交，避免把无关格式化、文档、前端、后端混在一个提交里。
- 不提交 `runtime`、`vendor`、`public`、`build`、前端 `web/dist`、前端 `dist.zip`、本地 `.env` 和 IDE 文件。
