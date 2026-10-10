/**
 * 富文本浮层真实浏览器回归：从仓库根目录执行 node web/scripts/check-rich-text-toolbar.mjs。
 * 默认使用 Playwright Chromium；本机可设置 RICH_TEXT_BROWSER_CHANNEL=chrome 复用已安装浏览器。
 * 仅加载内存夹具，上传入口明确拒绝调用，不连接业务接口或保存真实内容。
 */
import assert from 'node:assert/strict';
import { access, mkdtemp, realpath, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import vue from '@vitejs/plugin-vue';
import { expect } from '@playwright/test';
import { chromium } from 'playwright';
import { createServer } from 'vite';

const webRoot = await realpath(fileURLToPath(new URL('..', import.meta.url)));
const commonRoot = path.join(webRoot, 'packages/effects/common-ui');
const component = path.join(commonRoot, 'src/ui/rich-text/admin-rich-text-editor.vue');
await access(component);
const fixtureId = path.join(commonRoot, '__rich-text-browser-fixture__.js');
const cacheDir = await mkdtemp(path.join(tmpdir(), 'rich-text-browser-'));
const imageKeys = ['imageWidth30', 'imageWidth50', 'imageWidth100', 'editImage', 'viewImageLink', 'deleteImage'];
const errors = [];
let browser;
let server;

async function closeEditors(page) {
  // 先走真实失焦流程，等待原生选区节流和浮层防抖完成，再模拟抽屉销毁。
  await page.locator('.admin-rich-text-editor__title').first().click();
  await page.evaluate(() => window.getSelection()?.removeAllRanges());
  await expect.poll(() => page.evaluate(() => window.fixture.editors.every(editor => editor.selection === null))).toBe(true);
  await expect(page.locator('.w-e-hover-bar.w-e-bar-show')).toHaveCount(0);
  // wangEditor 5 的 selectionchange 存在 100ms trailing throttle，销毁前排空回调；这不是布局等待。
  await page.waitForTimeout(150);
  await page.evaluate(() => { window.fixture.state.visible = false; });
  await expect(page.locator('[data-slate-editor]')).toHaveCount(0);
}

try {
  // 使用真实 Vue 组件、Ant 主题和 wangEditor；只隔离与本次 UI 验证无关的上传服务。
  server = await createServer({
    configFile: false,
    root: webRoot,
    cacheDir,
    logLevel: 'error',
    plugins: [{
      name: 'rich-text-browser-fixture',
      resolveId(id, importer) {
        if (id === '/rich-text-fixture.js') return fixtureId;
        if (id === '../upload/upload-client' && importer?.startsWith(commonRoot)) return '\0upload-disabled';
      },
      load(id) {
        if (id === '\0upload-disabled') return 'export function uploadSceneFile() { throw new Error("Browser fixture must not upload"); }';
        if (id !== fixtureId) return;
        return `
          import { createApp, h, reactive } from 'vue';
          import { ConfigProvider, theme } from 'ant-design-vue';
          import { Boot } from '@wangeditor/editor';
          import Editor from ${JSON.stringify(component)};
          const image = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="620" height="360"><rect width="620" height="360" fill="#53657d"/><text x="40" y="100" fill="white" font-size="32">Image toolbar</text></svg>');
          const initial = '<p>上方正文：浮层不应透出文字或裁切按钮。</p><p>点击图片检查工具栏。</p><p><img src="' + image + '" /></p><p><a href="https://example.invalid/">测试链接</a></p><table><tbody><tr><td>测试单元格</td><td>第二列</td></tr></tbody></table><div data-w-e-type="video" data-w-e-is-void><video controls width="240" height="120"></video></div><p>正文结束</p>';
          const state = reactive({ dark: true, primary: '#1677ff', width: 740, visible: true, values: [initial, initial] });
          const editors = [];
          Boot.registerPlugin(editor => {
            editors.push(editor);
            editor.on('destroyed', () => editors.splice(editors.indexOf(editor), 1));
            return editor;
          });
          window.fixture = { state, image, initial, editors };
          const Content = { setup() {
            const { token } = theme.useToken();
            return () => h('div', { style: Object.fromEntries(Object.entries(token.value).map(([key, value]) => ['--ant-' + key, value])) }, state.values.map((value, index) => h('section', { style: { width: state.width + 'px', margin: '24px auto' } }, [h(Editor, {
              modelValue: value, 'onUpdate:modelValue': value => state.values[index] = value,
              visible: state.visible, title: '正文内容', height: 520, allowVideo: true,
            })])));
          } };
          createApp({ render: () => h(ConfigProvider, { theme: { algorithm: state.dark ? theme.darkAlgorithm : theme.defaultAlgorithm, token: { colorPrimary: state.primary } } }, {
            default: () => h(Content)
          }) }).mount('#app');
        `;
      },
      configureServer(vite) {
        vite.middlewares.use((req, res, next) => {
          if (req.url !== '/') return next();
          res.setHeader('Content-Type', 'text/html');
          res.end('<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;font:14px sans-serif;background:#808080}*{box-sizing:border-box}</style></head><body><div id="app"></div><script type="module" src="/rich-text-fixture.js"></script></body></html>');
        });
      },
    }, vue()],
    resolve: {
      dedupe: ['vue'],
      alias: Object.fromEntries(['ant-design-vue', '@wangeditor/editor', '@wangeditor/editor-for-vue'].map(name => [name, path.join(commonRoot, 'node_modules', name)])),
    },
    server: { host: '127.0.0.1', port: 0 },
  });
  await server.listen();
  browser = await chromium.launch({ headless: true, channel: process.env.RICH_TEXT_BROWSER_CHANNEL || undefined });
  const page = await browser.newPage({ viewport: { width: 1100, height: 950 } });
  page.on('pageerror', error => errors.push(error.stack || error.message));
  // 禁止夹具访问外部站点，避免媒体或链接测试向外发送请求。
  await page.route(/^https?:\/\/(?!127\.0\.0\.1[:/])/, route => route.abort());
  await page.goto(server.resolvedUrls.local[0]);
  const editor = page.locator('.admin-rich-text-editor').first();
  const image = editor.locator('[data-slate-editor] img');
  const bar = editor.locator('.w-e-hover-bar');
  try {
    await image.waitFor({ timeout: 60_000 });
  } catch (failure) {
    // 首屏失败时带出浏览器异常和实际页面，避免 CI 只留下无上下文的定位器超时。
    console.error('Browser page errors:', errors);
    console.error('Browser page body:', (await page.locator('body').innerText()).slice(0, 4_000));
    throw failure;
  }
  // Chromium 与模拟 DOM 对 img/br 的序列化不同；必须用真实按键覆盖 v-model 回传，
  // 确认每次输入后选区仍在图片下方，下一字符也不能落到正文开头。
  const body = editor.locator('[data-slate-editor]');
  await page.evaluate(() => window.fixture.editors[0].focus(true));
  await page.keyboard.type('A');
  await expect.poll(() => page.evaluate(() => {
    const selection = window.getSelection();
    return { text: selection?.anchorNode?.textContent, offset: selection?.anchorOffset };
  })).toEqual({ text: '正文结束A', offset: 5 });
  await page.keyboard.type('B');
  await expect(body.locator('> p').last()).toHaveText('正文结束AB');
  await expect(body.locator('> p').first()).toHaveText('上方正文：浮层不应透出文字或裁切按钮。');
  await expect.poll(() => page.evaluate(() => window.fixture.state.values[0].endsWith('<p>正文结束AB</p>'))).toBe(true);
  console.log('PASS: continuous typing after images keeps the caret and form content');
  await image.click();
  await expect(bar).toBeVisible();
  await expect(bar.locator('button')).toHaveCount(6);
  assert.deepEqual(await bar.locator('button').evaluateAll(buttons => buttons.map(button => button.dataset.menuKey)), imageKeys);

  // 原故障的三个独立断言：面板不透明、文字不裁切、图标继承对应按钮的状态色。
  assert.notEqual(await bar.evaluate(el => getComputedStyle(el).backgroundColor), 'rgba(0, 0, 0, 0)', 'image toolbar must have an opaque background');
  for (const button of await bar.locator('button').all()) {
    assert.equal(await button.evaluate(el => el.scrollWidth <= el.clientWidth), true, 'toolbar button text must fit');
    await expect(button).toHaveCSS('height', '30px');
  }
  for (const svg of await bar.locator('button svg').all()) {
    assert.equal(await svg.evaluate(el => getComputedStyle(el).fill === getComputedStyle(el.closest('button')).color), true, 'icons must follow button state colors');
  }
  console.log('PASS: opaque card, readable percentage buttons and themed icons');
  // 可选截图仅写到调用方明确指定的本地验证产物路径。
  if (process.env.RICH_TEXT_SCREENSHOT) await editor.screenshot({ path: process.env.RICH_TEXT_SCREENSHOT });

  const darkBackground = await bar.evaluate(el => getComputedStyle(el).backgroundColor);
  await page.evaluate(() => { window.fixture.state.dark = false; });
  await expect.poll(() => bar.evaluate(el => getComputedStyle(el).backgroundColor)).not.toBe(darkBackground);
  const lightBackground = await bar.evaluate(el => getComputedStyle(el).backgroundColor);
  assert.notEqual(lightBackground, 'rgba(0, 0, 0, 0)');
  await page.evaluate(() => { window.fixture.state.dark = true; });
  await expect(bar).toHaveCSS('background-color', darkBackground);
  for (const width of ['30', '50', '100']) {
    const button = bar.locator(`[data-menu-key="imageWidth${width}"]`);
    await button.click();
    await expect.poll(() => page.evaluate(() => window.fixture.editors[0].getElemsByType('image')[0].style.width)).toBe(`${width}%`);
  }
  // 百分比菜单原生不提供 active 状态，使用真正带状态的加粗菜单验证主题色。
  await editor.locator('[data-slate-editor] > p').first().click();
  const active = editor.locator('.w-e-toolbar [data-menu-key="bold"]');
  await expect(active).not.toHaveClass(/disabled/);
  await active.click();
  await expect(active).toHaveClass(/active/);
  const activeColor = await active.evaluate(el => getComputedStyle(el).color);
  await page.evaluate(() => { window.fixture.state.primary = '#722ed1'; });
  await expect.poll(() => active.evaluate(el => getComputedStyle(el).color)).not.toBe(activeColor);
  await image.click();
  const disabled = bar.locator('[data-menu-key="viewImageLink"]');
  await expect(disabled).toHaveClass(/disabled/);
  const disabledColor = await disabled.evaluate(el => getComputedStyle(el).color);
  await disabled.hover();
  await expect(disabled).toHaveCSS('color', disabledColor);
  await expect(disabled).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
  const edit = bar.locator('[data-menu-key="editImage"]');
  await page.keyboard.press('Tab');
  await edit.focus();
  await expect(edit).toHaveCSS('outline-style', 'solid');
  await edit.hover();
  assert.notEqual(await edit.evaluate(el => getComputedStyle(el).backgroundColor), 'rgba(0, 0, 0, 0)');
  console.log('PASS: light/dark/accent themes, scaling, hover/focus/active/disabled states');

  await edit.click();
  const modal = editor.locator('.w-e-modal');
  await expect(modal).toBeVisible();
  // 修改图片说明并由原生编辑菜单回写内存正文，确认样式不会破坏弹层交互。
  const inputs = modal.locator('input');
  await inputs.nth(1).fill('图片说明回归');
  await modal.locator('button').click();
  await expect(image).toHaveAttribute('alt', '图片说明回归');
  await image.click();
  await bar.locator('[data-menu-key="deleteImage"]').click();
  await expect(image).toHaveCount(0);
  await editor.locator('.w-e-toolbar [data-menu-key="undo"]').click();
  await expect(image).toHaveCount(1);
  console.log('PASS: edit image, delete and undo');

  // 真正切换组件模式并销毁/重建实例，不能用手工隐藏 DOM 冒充生命周期验证。
  const saved = await page.evaluate(() => window.fixture.state.values[0]);
  for (const mode of ['源码', '预览', '编辑']) {
    await editor.getByText(mode, { exact: true }).click();
    if (mode === '源码') await expect(editor.locator('.admin-rich-text-editor__source textarea')).toHaveValue(saved);
    if (mode === '预览') await expect(editor.locator('iframe')).toBeVisible();
    if (mode === '编辑') await expect(image).toBeVisible();
  }
  assert.equal(await page.evaluate(() => window.fixture.state.values[0]), saved);
  await closeEditors(page);
  await page.evaluate(() => { window.fixture.state.visible = true; });
  await expect(page.locator('[data-slate-editor]')).toHaveCount(2);
  await image.click();
  await expect(bar).toBeVisible();
  const second = page.locator('.admin-rich-text-editor').nth(1);
  await expect(second.locator('.w-e-hover-bar')).toBeHidden();
  await second.locator('[data-slate-editor] img').click();
  await expect(second.locator('.w-e-hover-bar')).toBeVisible();
  await expect(bar).toBeHidden();
  console.log('PASS: source/preview/edit, reopen and multiple independent editors');

  for (const [selector, menu] of [['a', 'editLink'], ['td', 'insertTableRow'], ['.w-e-textarea-video-container', 'editVideoSize']]) {
    const target = editor.locator(`[data-slate-editor] ${selector}`).first();
    // 点击视频容器空白处，避开浏览器原生播放控件对鼠标事件的接管。
    await target.click(selector.startsWith('.') ? { position: { x: (await target.boundingBox()).width - 2, y: 2 } } : {});
    await expect(bar.locator(`[data-menu-key="${menu}"]`)).toBeVisible();
    await expect(bar).toHaveCSS('background-color', darkBackground);
  }
  console.log('PASS: link, table and video hoverbars');

  // 首行/末行、左右对齐和窄容器都走原生浮层定位；只验证结果，不替换定位算法。
  for (const width of [740, 320, 220]) {
    for (const bottom of [false, true]) {
      for (const align of ['left', 'right']) {
        // 每组边界夹具在重新打开前赋值，避免上一组尚未提交的选区事件覆盖新正文。
        await closeEditors(page);
        await page.evaluate(({ width, bottom, align }) => {
          const { state, image } = window.fixture;
          state.width = width;
          state.values[0] = (bottom ? '<p>测试段落</p>'.repeat(10) : '') + `<p style="text-align: ${align}"><img src="${image}" style="width:64px" /></p><p>底部正文</p>`;
          state.visible = true;
        }, { width, bottom, align });
        await expect(image).toHaveCSS('width', '64px');
        await expect(editor.locator('[data-slate-editor] > p')).toHaveCount(bottom ? 12 : 2);
        await image.click();
        await expect(bar).toBeVisible();
        await expect.poll(() => bar.evaluate(el => {
          const bounds = el.closest('.w-e-text-container').getBoundingClientRect();
          const rect = el.getBoundingClientRect();
          return rect.left >= bounds.left - 1 && rect.right <= bounds.right + 1 && rect.top >= bounds.top - 1 && rect.bottom <= bounds.bottom + 1;
        })).toBe(true);
        for (const button of await bar.locator('button').all()) {
          assert.equal(await button.evaluate(el => el.scrollWidth <= el.clientWidth), true);
        }
      }
    }
  }
  await editor.locator('.w-e-scroll').evaluate(el => { el.scrollTop = 0; el.dispatchEvent(new Event('scroll')); });
  await expect(bar).toBeHidden();
  console.log('PASS: edge placement, narrow wrapping and scroll dismissal');
  assert.deepEqual(errors, [], 'browser must not raise runtime errors');
} finally {
  await browser?.close();
  await server?.close();
  // 只清理此脚本创建的临时依赖缓存，不触及仓库或用户浏览器资料。
  await rm(cacheDir, { recursive: true, force: true });
}
