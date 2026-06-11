import path from 'node:path';
import { fileURLToPath } from 'node:url';

import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, '..');

export default defineConfig({
  plugins: [vue()],
  server: {
    fs: {
      allow: [projectRoot],
    },
  },
  test: {
    environment: 'happy-dom',
    include: [
      '**/*.{test,spec}.?(c|m)[jt]s?(x)',
      '../plugin/*/stc/view/**/*.{test,spec}.?(c|m)[jt]s?(x)',
    ],
    setupFiles: ['./vitest.setup.ts'],
  },
});
