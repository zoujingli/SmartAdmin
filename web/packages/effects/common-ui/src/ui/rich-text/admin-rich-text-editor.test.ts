import type { IDomEditor } from '@wangeditor/editor';

import { flushPromises, mount } from '@vue/test-utils';
import { Editor } from '@wangeditor/editor-for-vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';

import AdminRichTextEditor from './admin-rich-text-editor.vue';

vi.mock('../upload/upload-client', () => ({
  uploadSceneFile: vi.fn(),
}));

const wrappers: ReturnType<typeof mount>[] = [];

async function mountForm(initial: string) {
  const content = ref(initial);
  const visible = ref(true);
  const wrapper = mount(defineComponent({
    setup: () => () => h(AdminRichTextEditor, {
      modelValue: content.value,
      'onUpdate:modelValue': (value: string) => { content.value = value; },
      uploadable: false,
      visible: visible.value,
    }),
  }), { attachTo: document.body });
  wrappers.push(wrapper);
  await flushPromises();
  const editor = wrapper.getComponent(Editor).emitted('onCreated')![0]![0] as IDomEditor;
  return { content, editor, visible, wrapper };
}

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => {
    const element = wrapper.element;
    wrapper.unmount();
    element.remove();
  });
});

describe('AdminRichTextEditor external content', () => {
  it.each(['', '<p>完成接口联调补充测试</p>'])('keeps a confirmed candidate after a pending selection change (initial: %s)', async (initial) => {
    const { content, editor, wrapper } = await mountForm(initial);
    const candidate = '<p>完成接口联调。<br />\n补充边界测试，等待验收。</p>';
    // 真实选区操作会异步触发 change，必须覆盖它与父表单回填发生在同一轮的场景。
    editor.select({ anchor: { path: [0, 0], offset: 0 }, focus: { path: [0, 0], offset: 0 } });

    // 模拟人工确认 AI 候选后，父表单替换 v-model；使用真实编辑器验证最终正文。
    content.value = candidate;
    await flushPromises();
    await nextTick();
    expect(wrapper.get('[data-slate-editor]').text()).toContain('补充边界测试，等待验收。');
    expect(content.value).toContain('补充边界测试，等待验收。');
  });

  it('still writes user edits and intentional deletion back to the form', async () => {
    const { content, editor } = await mountForm('<p>完成联调</p>');
    editor.select({ anchor: { path: [0, 0], offset: 4 }, focus: { path: [0, 0], offset: 4 } });
    editor.insertText('，补充测试');
    await flushPromises();
    expect(content.value).toContain('完成联调，补充测试');

    editor.clear();
    await flushPromises();
    expect(content.value).toBe('<p><br></p>');
  });

  it('keeps source edits when a selection callback is pending', async () => {
    const { content, editor, wrapper } = await mountForm('<p>原正文</p>');
    await wrapper.get('input[value="source"]').setValue(true);
    editor.select({ anchor: { path: [0, 0], offset: 0 }, focus: { path: [0, 0], offset: 0 } });
    await wrapper.get('textarea').setValue('<p>源码更新后的正文</p>');
    await flushPromises();
    expect(content.value).toContain('源码更新后的正文');
    await wrapper.get('input[value="visual"]').setValue(true);
    await flushPromises();
    expect(wrapper.get('[data-slate-editor]').text()).toBe('源码更新后的正文');
  });

  it('ignores callbacks from an editor closed before the next form opens', async () => {
    const { content, editor, visible, wrapper } = await mountForm('<p>旧表单</p>');
    editor.select({ anchor: { path: [0, 0], offset: 0 }, focus: { path: [0, 0], offset: 0 } });
    visible.value = false;
    content.value = '<p>新表单正文</p>';
    await flushPromises();
    expect(content.value).toBe('<p>新表单正文</p>');
    visible.value = true;
    await flushPromises();
    expect(wrapper.get('[data-slate-editor]').text()).toBe('新表单正文');
  });
});
