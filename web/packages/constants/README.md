# @vben/constants

用于多个 `app` 公用的常量，继承了 `@vben-core/shared/constants` 的所有能力。业务上有通用常量可以放在这里。

## 用法

### 添加依赖

```bash
# 进入目标应用目录，例如 apps/xxxx-app
# cd apps/xxxx-app
pnpm add @vben/constants
```

### 使用

```ts
import { SUPPORT_LANGUAGES } from '@vben/constants';
```

认证入口、登录页和业务首页由插件 `auth-entry.ts` 声明，公共常量包不承载插件默认路径。
