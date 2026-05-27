<template>
  <div class="admin-rich-text-editor" :class="{ 'is-source': viewMode === 'source', 'is-preview': viewMode === 'preview' }">
    <div class="admin-rich-text-editor__head">
      <div class="admin-rich-text-editor__copy">
        <div class="admin-rich-text-editor__title">{{ title }}</div>
        <div v-if="resolvedDescription" class="admin-rich-text-editor__desc">{{ resolvedDescription }}</div>
      </div>
      <div class="admin-rich-text-editor__actions">
        <!-- 业务模块可把 AI 辅助等字段级操作放在模式切换同一区域，避免表单额外占行。 -->
        <div v-if="$slots.actions" class="admin-rich-text-editor__slot-actions">
          <slot name="actions"></slot>
        </div>
        <Tag v-if="uploading" class="admin-rich-text-editor__uploading" color="processing">上传中</Tag>
        <RadioGroup v-model:value="viewMode" class="admin-rich-text-editor__mode" size="small" @change="handleViewModeChange">
          <RadioButton v-for="item in resolvedModes" :key="item.value" :value="item.value">{{ item.label }}</RadioButton>
        </RadioGroup>
      </div>
    </div>

    <div v-show="viewMode === 'visual'" class="admin-rich-text-editor__visual">
      <template v-if="editorMounted">
        <Toolbar
          v-if="mediaToolbarKeys.length"
          class="admin-rich-text-editor__toolbar"
          :default-config="toolbarConfig"
          :editor="richEditorRef"
          :mode="wangEditorMode"
        />
        <input
          ref="fileInputRef"
          aria-hidden="true"
          class="admin-rich-text-editor__file-input"
          hidden
          tabindex="-1"
          type="file"
          @change="handleFilePicked"
        />
        <Editor
          v-model="contentValue"
          class="admin-rich-text-editor__body"
          :default-config="editorConfig"
          :mode="wangEditorMode"
          :style="editorBodyStyle"
          @customAlert="handleEditorAlert"
          @customPaste="handleEditorPaste"
          @onCreated="handleEditorCreated"
        />
      </template>
      <div v-else class="admin-rich-text-editor__loading" :style="editorBodyStyle">编辑器加载中...</div>
      <div v-if="resolvedFooterText || uploading" class="admin-rich-text-editor__foot">
        <span>{{ resolvedFooterText }}</span>
        <Tag v-if="uploading" color="processing">媒体上传中</Tag>
      </div>
    </div>

    <div v-show="viewMode === 'source'" class="admin-rich-text-editor__source">
      <Textarea :value="contentValue" :rows="sourceRows" placeholder="HTML 源码模式，切回编辑后会重新渲染当前 HTML。" @update:value="updateSource" />
    </div>

    <iframe v-show="viewMode === 'preview'" class="admin-rich-text-editor__preview" sandbox="" :srcdoc="previewHtml" :style="previewStyleObject"></iframe>
  </div>
</template>

<script setup lang="ts">
import type { IButtonMenu, IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor';
import type { CSSProperties } from 'vue';
import type { UploadAsset } from '../upload/types';

import { computed, nextTick, onBeforeUnmount, ref, shallowRef, watch } from 'vue';

import { Boot } from '@wangeditor/editor';
import { Editor, Toolbar } from '@wangeditor/editor-for-vue';
import { message, RadioButton, RadioGroup, Tag, Textarea } from 'ant-design-vue';
import '@wangeditor/editor/dist/css/style.css';

import { uploadSceneFile } from '../upload/upload-client';

type RichTextViewMode = 'preview' | 'source' | 'visual';
type PasteResultSetter = (value: boolean) => void;

const ATTACHMENT_MENU_KEY = 'uploadProjectAttachment';
const ATTACHMENT_MENU_ICON = '<svg viewBox="0 0 1024 1024"><path d="M704 128a192 192 0 0 1 135.76 327.76L426.72 868.8a144 144 0 0 1-203.64-203.64l402.4-402.4a96 96 0 0 1 135.76 135.76l-393.04 393.04a32 32 0 1 1-45.28-45.28l393.04-393.04a32 32 0 0 0-45.28-45.28l-402.4 402.4a80 80 0 0 0 113.12 113.12l413.04-413.04A128 128 0 1 0 613.44 229.44L218.88 624a32 32 0 1 1-45.28-45.28l394.56-394.56A191.36 191.36 0 0 1 704 128z"/></svg>';
const attachmentUploadHandlers = new WeakMap<IDomEditor, () => void>();

class UploadAttachmentMenu implements IButtonMenu {
  readonly iconSvg = ATTACHMENT_MENU_ICON;
  readonly tag = 'button';
  readonly title = '上传附件';

  exec(editor: IDomEditor): void {
    const handler = attachmentUploadHandlers.get(editor);
    if (!handler) {
      message.warning('编辑器尚未初始化，请稍后再试');
      return;
    }
    handler();
  }

  getValue(): string {
    return '';
  }

  isActive(): boolean {
    return false;
  }

  isDisabled(): boolean {
    return false;
  }
}

function registerAttachmentMenu() {
  const globalObject = globalThis as typeof globalThis & { __SMART_ADMIN_RICH_ATTACHMENT_MENU__?: boolean };
  if (globalObject.__SMART_ADMIN_RICH_ATTACHMENT_MENU__) {
    return;
  }

  // 附件上传作为 wangEditor 标准工具栏菜单注册，确保按钮位置跟随工具栏顺序而不是浮在编辑器头部。
  Boot.registerMenu({
    key: ATTACHMENT_MENU_KEY,
    factory: () => new UploadAttachmentMenu(),
  });
  globalObject.__SMART_ADMIN_RICH_ATTACHMENT_MENU__ = true;
}

registerAttachmentMenu();

const props = withDefaults(defineProps<{
  allowVideo?: boolean;
  allowFile?: boolean;
  description?: string;
  footerText?: string;
  height?: number | string;
  minHeight?: number | string;
  modelValue?: string;
  placeholder?: string;
  sourceRows?: number;
  title?: string;
  uploadable?: boolean;
  visible?: boolean;
  wangEditorMode?: 'default' | 'simple';
}>(), {
  allowVideo: false,
  allowFile: false,
  description: '',
  footerText: '',
  height: 'min(520px, 58vh)',
  minHeight: 360,
  modelValue: '',
  placeholder: '',
  sourceRows: 16,
  title: '富文本编辑器',
  uploadable: true,
  visible: true,
  wangEditorMode: 'default',
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
  'uploading-change': [value: boolean];
}>();

const viewMode = ref<RichTextViewMode>('visual');
const editorMounted = ref(false);
const editorUploadingCount = ref(0);
const richEditorRef = shallowRef<IDomEditor>();
const fileInputRef = ref<HTMLInputElement>();
const uploading = computed(() => editorUploadingCount.value > 0);
const contentValue = computed({
  get: () => props.modelValue || '',
  set: (value: string) => emit('update:modelValue', value || ''),
});
const resolvedModes = [
  { label: '编辑', value: 'visual' },
  { label: '预览', value: 'preview' },
  { label: '源码', value: 'source' },
] as const;
const resolvedDescription = computed(() => props.description || (props.allowVideo
  ? `支持常用排版、链接、列表、表格、代码块，以及图片、视频${props.allowFile ? '和普通附件' : ''}上传。`
  : `支持常用排版、链接、列表、表格、代码块，以及图片${props.allowFile ? '和普通附件' : ''}上传。`));
const resolvedFooterText = computed(() => props.footerText || (props.allowVideo
  ? `图片和视频可通过工具栏上传，${props.allowFile ? '普通文件可通过“上传附件”插入下载链接，' : ''}也可直接从剪贴板粘贴；上传内容统一走系统文件流程。`
  : `图片可通过工具栏上传，${props.allowFile ? '普通文件可通过“上传附件”插入下载链接，' : ''}也可直接从剪贴板粘贴；上传内容统一走系统文件流程。`));
const resolvedPlaceholder = computed(() => props.placeholder || (props.allowVideo
  ? `请输入正文内容，可通过工具栏上传或直接粘贴图片/视频${props.allowFile ? '，也可插入附件下载链接' : ''}。`
  : `请输入正文内容，可通过工具栏上传或直接粘贴图片${props.allowFile ? '，也可插入附件下载链接' : ''}。`));
const mediaToolbarKeys = computed<NonNullable<IToolbarConfig['toolbarKeys']>>(() => {
  // 业务编辑场景保留常用排版能力，仅把图片/视频上传收口到系统文件流程。
  return [
    'headerSelect',
    '|',
    'bold',
    'underline',
    'italic',
    'through',
    'clearStyle',
    '|',
    'bulletedList',
    'numberedList',
    'todo',
    '|',
    'insertLink',
    ...(props.uploadable ? ['uploadImage'] : []),
    ...(props.allowVideo && props.uploadable ? ['uploadVideo'] : []),
    ...(props.allowFile && props.uploadable ? [ATTACHMENT_MENU_KEY] : []),
    'insertTable',
    'codeBlock',
    '|',
    'undo',
    'redo',
  ] as NonNullable<IToolbarConfig['toolbarKeys']>;
});

const toolbarConfig = computed<Partial<IToolbarConfig>>(() => {
  return {
    // wangEditor 的修改弹层需要由编辑器根据选区自动计算位置；挂到 body 后库只会设置 left/right，
    // 在抽屉/弹框页面里会跑到左侧或被遮住，所以保持挂载到编辑器内容区并配合外层 overflow 放开。
    modalAppendToBody: false,
    toolbarKeys: mediaToolbarKeys.value,
  };
});

const editorConfig = computed<Partial<IEditorConfig>>(() => ({
  placeholder: resolvedPlaceholder.value,
  MENU_CONF: {
    uploadImage: {
      allowedFileTypes: ['image/*'],
      maxNumberOfFiles: 10,
      // 富文本图片必须复用后台统一上传入口，禁止插入 base64 或绕过 system_file 文件记录。
      customUpload(file: File, _insertFn: (src: string, alt: string, href: string) => void) {
        void uploadEditorAsset('image', file)
          .then((asset) => {
            const url = getAssetUrl(asset);
            if (!url) throw new Error('图片上传成功但未返回访问地址');
            insertUploadedMedia('image', asset, file);
          })
          .catch((error) => handleEditorUploadError(error));
      },
    },
    uploadVideo: {
      allowedFileTypes: ['video/*'],
      maxNumberOfFiles: 1,
      // 视频同样进入系统上传机制；正文只持久化可访问 URL，避免保存本地临时路径或 base64。
      customUpload(file: File, _insertFn: (src: string, poster: string) => void) {
        void uploadEditorAsset('video', file)
          .then((asset) => {
            const url = getAssetUrl(asset);
            if (!url) throw new Error('视频上传成功但未返回访问地址');
            insertUploadedMedia('video', asset, file);
          })
          .catch((error) => handleEditorUploadError(error));
      },
    },
  },
}));

const editorBodyStyle = computed<CSSProperties>(() => ({
  height: cssSize(props.height),
  minHeight: cssSize(props.minHeight),
}));

const previewStyleObject = computed<CSSProperties>(() => ({
  minHeight: cssSize(props.minHeight),
}));

const previewHtml = computed(() => {
  const content = sanitizePreviewHtml(contentValue.value || '');
  return `<!doctype html><html><head><meta charset="UTF-8"><style>${buildPreviewStyle()}</style></head><body>${content || '<p class="empty">暂无内容</p>'}</body></html>`;
});

function cssSize(value: number | string) {
  return typeof value === 'number' ? `${value}px` : value;
}

function updateSource(value?: string) {
  contentValue.value = value || '';
}

async function handleViewModeChange() {
  // 从源码模式回到可视化编辑时，用当前 HTML 重建编辑器节点，避免源码与可视化内容不同步。
  if (viewMode.value === 'visual') {
    await nextTick();
    richEditorRef.value?.setHtml(contentValue.value || '');
  }
}

function handleEditorCreated(editor: IDomEditor) {
  richEditorRef.value = editor;
  attachmentUploadHandlers.set(editor, openFilePicker);
  editor.setHtml(contentValue.value || '');
}

function handleEditorAlert(info: string, type: 'error' | 'info' | 'success' | 'warning') {
  const text = String(info || '');
  if (!text) return;
  if (type === 'error') message.error(text);
  else if (type === 'warning') message.warning(text);
  else if (type === 'success') message.success(text);
  else message.info(text);
}

function handleEditorPaste(editor: IDomEditor, event: ClipboardEvent, setResult: PasteResultSetter) {
  const files = getClipboardMediaFiles(event);
  if (files.length === 0) {
    setResult(true);
    return;
  }

  // 粘贴图片或视频时阻止编辑器默认行为，统一先上传到后台文件系统再插入标准 HTML。
  event.preventDefault();
  setResult(false);
  void uploadAndInsertPastedMedia(editor, files);
}

async function uploadEditorAsset(scene: 'file' | 'image' | 'video', file: File) {
  if (!props.uploadable) {
    throw new Error('缺少系统文件上传权限');
  }
  if (scene === 'video' && !props.allowVideo) {
    throw new Error('当前编辑器未启用视频上传');
  }
  if (scene === 'file' && !props.allowFile) {
    throw new Error('当前编辑器未启用附件上传');
  }
  if (scene === 'image' && !file.type.startsWith('image/')) {
    throw new Error('请选择图片文件');
  }
  if (scene === 'video' && !file.type.startsWith('video/')) {
    throw new Error('请选择视频文件');
  }

  editorUploadingCount.value += 1;
  try {
    return await uploadSceneFile(scene, file);
  } finally {
    editorUploadingCount.value = Math.max(0, editorUploadingCount.value - 1);
  }
}

function handleEditorUploadError(error: unknown) {
  message.error(`文件上传失败: ${error instanceof Error ? error.message : String(error)}`);
}

function getAssetUrl(asset: UploadAsset) {
  return asset.url || asset.preview_url || asset.download_url || '';
}

function getClipboardMediaFiles(event: ClipboardEvent) {
  return Array.from(event.clipboardData?.items || [])
    .filter((item) => item.kind === 'file' && (item.type.startsWith('image/') || (props.allowVideo && item.type.startsWith('video/'))))
    .map((item) => item.getAsFile())
    .filter((file): file is File => file instanceof File);
}

async function uploadAndInsertPastedMedia(editor: IDomEditor, files: File[]) {
  try {
    for (const file of files) {
      const scene = file.type.startsWith('video/') ? 'video' : 'image';
      const asset = await uploadEditorAsset(scene, file);
      const html = scene === 'video' ? buildVideoHtml(asset, file) : buildImageHtml(asset, file);
      editor.restoreSelection();
      editor.dangerouslyInsertHtml(html);
    }
    contentValue.value = editor.getHtml();
    message.success('媒体已上传并插入正文');
  } catch (error) {
    handleEditorUploadError(error);
  }
}

function insertUploadedMedia(scene: 'image' | 'video', asset: UploadAsset, file: File) {
  const html = scene === 'video' ? buildVideoHtml(asset, file) : buildImageHtml(asset, file);
  if (!html) {
    throw new Error('文件上传成功但未返回可插入内容');
  }
  const editor = richEditorRef.value;
  if (!editor) {
    contentValue.value = `${contentValue.value || ''}${html}`;
    message.success('媒体已上传并插入正文');
    return;
  }

  // wangEditor 工具栏上传在抽屉/弹窗中偶发只回调成功、不触发内置插入；
  // 这里统一恢复选区、显式写入 HTML 并同步 v-model，确保截图上传后正文立即可见。
  editor.restoreSelection();
  editor.dangerouslyInsertHtml(html);
  contentValue.value = editor.getHtml();
  message.success('媒体已上传并插入正文');
}

function openFilePicker() {
  if (!props.uploadable) {
    message.warning('缺少系统文件上传权限');
    return;
  }
  fileInputRef.value?.click();
}

function handleFilePicked(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  void uploadEditorAsset('file', file)
    .then((asset) => {
      const editor = richEditorRef.value;
      if (!editor) throw new Error('编辑器尚未初始化');
      editor.restoreSelection();
      editor.dangerouslyInsertHtml(buildFileHtml(asset, file));
      contentValue.value = editor.getHtml();
      message.success('附件已上传并插入正文');
    })
    .catch((error) => handleEditorUploadError(error));
}

function buildImageHtml(asset: UploadAsset, file: File) {
  const url = getAssetUrl(asset);
  if (!url) return '';
  return `<p><img src="${escapeHtml(url)}" alt="${escapeHtml(asset.origin_name || file.name || '图片')}" style="max-width:100%;height:auto;" /></p>`;
}

function buildVideoHtml(asset: UploadAsset, file: File) {
  const url = getAssetUrl(asset);
  if (!url) return '';
  return `<p><video src="${escapeHtml(url)}" controls preload="metadata" style="max-width:100%;width:100%;border-radius:8px;">${escapeHtml(asset.origin_name || file.name || '视频')}</video></p>`;
}

function buildFileHtml(asset: UploadAsset, file: File) {
  const fileId = Number(asset.id || 0);
  if (fileId <= 0) return '';
  const fileName = asset.origin_name || file.name || `附件${fileId}`;
  return `<p><a href="/project/file/download/${fileId}" title="下载附件：${escapeHtml(fileName)}" data-project-file="1" data-file-id="${fileId}" data-file-name="${escapeHtml(fileName)}" target="_blank" rel="noopener noreferrer" download>${escapeHtml(fileName)}</a></p>`;
}

function escapeHtml(value: string) {
  return value.replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
}

function sanitizePreviewHtml(value: string) {
  return value
    .replace(/<\s*(script|style|iframe|object|embed)[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
    .replace(/\son[a-z]+\s*=\s*(['"])[\s\S]*?\1/gi, '')
    .replace(/\s(href|src)\s*=\s*(['"])\s*(javascript:|data:)[\s\S]*?\2/gi, '');
}

function buildPreviewStyle() {
  return `body{margin:0;padding:18px 22px;color:CanvasText;background:Canvas;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.85;}img{max-width:100%;height:auto;border-radius:8px;}video{max-width:100%;border-radius:10px;background:#000;}a[data-project-file="1"]{display:inline-flex;align-items:center;gap:8px;max-width:100%;padding:8px 10px;border:1px solid ButtonBorder;border-radius:8px;background:color-mix(in srgb, CanvasText 5%, Canvas);color:LinkText;text-decoration:none;font-weight:500;overflow-wrap:anywhere;}a[data-project-file="1"]::before{content:"📎";flex:none;}a[data-project-file="1"]:hover{text-decoration:underline;text-underline-offset:2px;}table{width:100%;border-collapse:collapse;}td,th{padding:8px;border:1px solid ButtonBorder;}blockquote{margin:8px 0;padding:8px 12px;border-left:4px solid Highlight;background:color-mix(in srgb, Highlight 8%, Canvas);}pre{padding:12px;overflow:auto;background:color-mix(in srgb, CanvasText 6%, Canvas);border-radius:8px;}.empty{color:GrayText;}`;
}

async function mountEditor() {
  if (editorMounted.value) return;
  editorMounted.value = true;
  await nextTick();
  richEditorRef.value?.setHtml(contentValue.value || '');
}

function destroyEditor() {
  if (richEditorRef.value) {
    attachmentUploadHandlers.delete(richEditorRef.value);
  }
  richEditorRef.value?.destroy();
  richEditorRef.value = undefined;
  editorMounted.value = false;
}

watch(() => props.visible, (visible) => {
  if (visible) {
    void mountEditor();
    return;
  }
  destroyEditor();
}, { immediate: true });

watch(() => props.modelValue, (value) => {
  const next = value || '';
  if (richEditorRef.value && next !== richEditorRef.value.getHtml()) {
    richEditorRef.value.setHtml(next);
  }
});

watch(uploading, (value) => emit('uploading-change', value), { immediate: true });

onBeforeUnmount(() => {
  destroyEditor();
});
</script>

<style scoped>
.admin-rich-text-editor {
  /*
   * wangEditor 的链接/图片/视频修改弹层会按选区绝对定位，可能向编辑器边界外展开；
   * 这里不能裁剪根容器，否则弹层会只露出一半，看起来像“没有弹出”。
   */
  overflow: visible;
  border: 1px solid var(--ant-colorBorder, hsl(var(--border)));
  border-radius: 10px;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
}

.admin-rich-text-editor__head {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  justify-content: space-between;
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  background: var(--ant-colorFillQuaternary, hsl(var(--muted)));
}

.admin-rich-text-editor__copy {
  min-width: 0;
  flex: 1;
}

.admin-rich-text-editor__title {
  color: var(--ant-colorText, hsl(var(--foreground)));
  font-size: 14px;
  font-weight: 600;
}

.admin-rich-text-editor__desc {
  margin-top: 2px;
  color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground)));
  font-size: 12px;
  line-height: 1.45;
}

.admin-rich-text-editor__actions {
  display: inline-flex;
  flex: none;
  gap: 8px;
  align-items: center;
  justify-content: flex-end;
  padding-top: 1px;
}

.admin-rich-text-editor__slot-actions {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  justify-content: flex-end;
}

.admin-rich-text-editor__uploading {
  margin-inline-end: 0;
}

.admin-rich-text-editor__file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  pointer-events: none;
}

.admin-rich-text-editor__mode {
  display: inline-flex;
  overflow: hidden;
  align-items: center;
  padding: 2px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 999px;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
  box-shadow: inset 0 0 0 1px rgb(255 255 255 / 2%);
}

.admin-rich-text-editor__mode :deep(.ant-radio-button-wrapper) {
  height: 24px;
  padding-inline: 10px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground)));
  font-size: 12px;
  line-height: 24px;
  transition:
    background 0.2s ease,
    color 0.2s ease;
}

.admin-rich-text-editor__mode :deep(.ant-radio-button-wrapper::before) {
  display: none;
}

.admin-rich-text-editor__mode :deep(.ant-radio-button-wrapper:hover) {
  color: var(--ant-colorText, hsl(var(--foreground)));
}

.admin-rich-text-editor__mode :deep(.ant-radio-button-wrapper-checked) {
  background: var(--ant-colorPrimaryBg, hsl(var(--primary) / 0.14));
  color: var(--ant-colorPrimary, hsl(var(--primary)));
  font-weight: 600;
  box-shadow: none;
}

.admin-rich-text-editor__mode :deep(.ant-radio-button-wrapper-checked:hover) {
  color: var(--ant-colorPrimary, hsl(var(--primary)));
}

.admin-rich-text-editor__visual {
  overflow: visible;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
}

.admin-rich-text-editor__toolbar {
  position: sticky;
  top: 0;
  z-index: 3;
  border-bottom: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  background: var(--ant-colorBgContainer, hsl(var(--background)));
}

.admin-rich-text-editor__body {
  overflow: visible;
}

.admin-rich-text-editor__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
}

.admin-rich-text-editor__foot {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-top: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  background: var(--ant-colorFillQuaternary, hsl(var(--muted)));
  color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground)));
  font-size: 12px;
}

.admin-rich-text-editor__source {
  padding: 16px;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
}

.admin-rich-text-editor__preview {
  display: block;
  width: 100%;
  min-height: 360px;
  border: 0;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
}

.admin-rich-text-editor :deep(.w-e-toolbar) {
  gap: 2px;
  border: 0 !important;
  padding: 6px 8px !important;
  background: var(--ant-colorBgContainer, hsl(var(--background))) !important;
  color: var(--ant-colorText, hsl(var(--foreground))) !important;
  line-height: 1;
}

.admin-rich-text-editor :deep(.w-e-bar) {
  background: transparent !important;
}

.admin-rich-text-editor :deep(.w-e-bar-item) {
  height: 30px;
  padding: 0 1px;
  color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground))) !important;
}

.admin-rich-text-editor :deep(.w-e-bar-item button) {
  width: 30px;
  min-width: 30px;
  height: 30px;
  border: 0 !important;
  border-radius: 6px !important;
  background: transparent !important;
  color: inherit !important;
}

.admin-rich-text-editor :deep(.w-e-bar-item button:hover),
.admin-rich-text-editor :deep(.w-e-bar-item button:focus) {
  background: var(--ant-colorFillSecondary, hsl(var(--muted))) !important;
  color: var(--ant-colorText, hsl(var(--foreground))) !important;
}

.admin-rich-text-editor :deep(.w-e-bar-item button.active),
.admin-rich-text-editor :deep(.w-e-bar-item button[data-active='true']) {
  background: var(--ant-colorPrimaryBg, hsl(var(--primary) / 0.12)) !important;
  color: var(--ant-colorPrimary, hsl(var(--primary))) !important;
}

.admin-rich-text-editor :deep(.w-e-bar-item .title) {
  padding: 0 8px;
  color: inherit !important;
  font-size: 12px;
}

.admin-rich-text-editor :deep(.w-e-bar-divider) {
  height: 18px;
  margin: 6px 4px;
  border-left-color: var(--ant-colorBorderSecondary, hsl(var(--border))) !important;
}

.admin-rich-text-editor :deep(.w-e-select-list),
.admin-rich-text-editor :deep(.w-e-drop-panel) {
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))) !important;
  border-radius: 8px !important;
  background: var(--ant-colorBgElevated, var(--ant-colorBgContainer, hsl(var(--background)))) !important;
  box-shadow: var(--ant-boxShadowSecondary, 0 8px 24px rgb(0 0 0 / 12%)) !important;
  color: var(--ant-colorText, hsl(var(--foreground))) !important;
}

.admin-rich-text-editor :deep(.w-e-modal) {
  z-index: 2200 !important;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))) !important;
  border-radius: 8px !important;
  background: var(--ant-colorBgElevated, var(--ant-colorBgContainer, hsl(var(--background)))) !important;
  box-shadow: var(--ant-boxShadowSecondary, 0 8px 24px rgb(0 0 0 / 12%)) !important;
  color: var(--ant-colorText, hsl(var(--foreground))) !important;
}

:global(.w-e-modal),
:global(.w-e-drop-panel),
:global(.w-e-select-list) {
  z-index: 2200 !important;
}

.admin-rich-text-editor :deep(.w-e-select-list ul .selected),
.admin-rich-text-editor :deep(.w-e-select-list ul li:hover),
.admin-rich-text-editor :deep(.w-e-drop-panel .w-e-panel-tab-title .active),
.admin-rich-text-editor :deep(.w-e-drop-panel .w-e-panel-tab-title span:hover) {
  background: var(--ant-colorPrimaryBg, hsl(var(--primary) / 0.12)) !important;
  color: var(--ant-colorPrimary, hsl(var(--primary))) !important;
}

.admin-rich-text-editor :deep(.w-e-text-container) {
  border: 0 !important;
  background: var(--ant-colorBgContainer, hsl(var(--background))) !important;
}

.admin-rich-text-editor :deep(.w-e-scroll) {
  padding: 18px 22px;
}

.admin-rich-text-editor :deep(.w-e-text-placeholder) {
  top: 18px;
  left: 22px;
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
}

.admin-rich-text-editor :deep(.w-e-text-container [data-slate-editor]) {
  min-height: 320px;
  color: var(--ant-colorText, hsl(var(--foreground)));
  line-height: 1.85;
}

.admin-rich-text-editor :deep(.w-e-text-container img) {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
}

.admin-rich-text-editor :deep(.w-e-text-container video) {
  max-width: 100%;
  border-radius: 10px;
  background: #000;
}

.admin-rich-text-editor :deep(.w-e-text-container a[data-project-file='1']) {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  max-width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 8px;
  background: var(--ant-colorFillQuaternary, hsl(var(--muted)));
  color: var(--ant-colorPrimary, hsl(var(--primary)));
  font-weight: 500;
  line-height: 1.4;
  text-decoration: none;
  overflow-wrap: anywhere;
}

.admin-rich-text-editor :deep(.w-e-text-container a[data-project-file='1']::before) {
  flex: none;
  content: '📎';
}

.admin-rich-text-editor :deep(.w-e-text-container a[data-project-file='1']:hover) {
  border-color: var(--ant-colorPrimaryBorder, var(--ant-colorPrimary, hsl(var(--primary))));
  background: var(--ant-colorPrimaryBg, hsl(var(--primary) / 0.12));
  text-decoration: underline;
  text-underline-offset: 2px;
}

.admin-rich-text-editor :deep(.w-e-text-container h2) {
  margin: 1em 0 0.6em;
  font-size: 1.4em;
  font-weight: 600;
}

.admin-rich-text-editor :deep(.w-e-text-container p) {
  margin: 0.6em 0;
}

@media (max-width: 768px) {
  .admin-rich-text-editor__head {
    align-items: stretch;
    flex-direction: column;
  }

  .admin-rich-text-editor__actions {
    flex-wrap: wrap;
    justify-content: flex-start;
  }

  .admin-rich-text-editor__foot {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
