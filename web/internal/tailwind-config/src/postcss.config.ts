import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';
import postcssAntdFixes from 'postcss-antd-fixes';
import postcssImport from 'postcss-import';
import postcssPresetEnv from 'postcss-preset-env';
import tailwindcss from 'tailwindcss';
import tailwindcssNesting from 'tailwindcss/nesting';

import config from '.';

export default {
  // PostCSS 配置由 @vben/tailwind-config 包对外输出，插件必须在本包上下文中解析；
  // 不能再把插件名字符串交给应用侧解析，否则 pnpm 严格依赖隔离下生产构建会找不到 cssnano 等依赖。
  plugins: [
    ...(process.env.NODE_ENV === 'production' ? [cssnano({})] : []),
    // Specifying the config is not necessary in most cases, but it is included
    autoprefixer({}),
    // 修复 element-plus 和 ant-design-vue 的样式和 tailwindcss 冲突问题。
    postcssAntdFixes({ prefixes: ['ant', 'el'] }),
    postcssImport({}),
    postcssPresetEnv({}),
    tailwindcss({ config }),
    tailwindcssNesting({}),
  ],
};
