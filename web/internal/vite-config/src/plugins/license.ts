import type {
  NormalizedOutputOptions,
  OutputBundle,
  OutputChunk,
} from 'rollup';
import type { PluginOption } from 'vite';

import { EOL } from 'node:os';

import { dateUtil, readPackageJSON } from '@vben/node-utils';

/**
 * 用于注入版权信息
 * @returns
 */

async function viteLicensePlugin(
  root = process.cwd(),
): Promise<PluginOption | undefined> {
  const {
    description = '',
    homepage = '',
    version = '',
  } = await readPackageJSON(root);

  return {
    apply: 'build',
    enforce: 'post',
    generateBundle: {
      handler: (_options: NormalizedOutputOptions, bundle: OutputBundle) => {
        const date = dateUtil().format('YYYY-MM-DD');
        // 构建产物会进入版本控制，版权信息逐行去尾空格，避免重复构建后产生脏 diff。
        const copyrightText = [
          '/*!',
          '  * SmartAdmin',
          `  * Version: ${version.trim()}`,
          '  * Author: Anyon',
          '  * Copyright (C) 2026 Anyon',
          '  * License: Apache-2.0',
          `  * Description: ${description.trim()}`,
          `  * Date Created: ${date}`,
          `  * Homepage: ${homepage.trim()}`,
          '  * Third-party notices: THIRD_PARTY_NOTICES.md',
          '*/',
        ]
          .map((line) => line.trimEnd())
          .join(EOL);

        for (const [, fileContent] of Object.entries(bundle)) {
          if (fileContent.type === 'chunk' && fileContent.isEntry) {
            const chunkContent = fileContent as OutputChunk;
            // 插入版权信息
            const content = chunkContent.code;
            const updatedContent = `${copyrightText}${EOL}${content}`;

            // 更新bundle
            (fileContent as OutputChunk).code = updatedContent;
          }
        }
      },
      order: 'post',
    },
    name: 'vite:license',
  };
}

export { viteLicensePlugin };
