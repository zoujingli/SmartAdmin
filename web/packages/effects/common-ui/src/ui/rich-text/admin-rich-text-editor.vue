<template>
  <div class="admin-rich-text-editor" :class="{ 'is-source': viewMode === 'source', 'is-preview': viewMode === 'preview' }">
    <div class="admin-rich-text-editor__head">
      <div class="admin-rich-text-editor__copy">
        <div class="admin-rich-text-editor__title">{{ title }}</div>
        <div v-if="description" class="admin-rich-text-editor__desc">{{ description }}</div>
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
          class="admin-rich-text-editor__toolbar"
          :default-config="toolbarConfig"
          :editor="richEditorRef"
          :mode="wangEditorMode"
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
      <div v-if="footerText || uploading" class="admin-rich-text-editor__foot">
        <span>{{ footerText }}</span>
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
import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor';
import type { CSSProperties } from 'vue';
import type { UploadAsset } from '../upload/types';

import { computed, nextTick, onBeforeUnmount, ref, shallowRef, watch } from 'vue';

import { Editor, Toolbar } from '@wangeditor/editor-for-vue';
import { message, RadioButton, RadioGroup, Tag, Textarea } from 'ant-design-vue';
import '@wangeditor/editor/dist/css/style.css';

import { uploadSceneFile } from '../upload/upload-client';

type RichTextViewMode = 'preview' | 'source' | 'visual';
type PasteResultSetter = (value: boolean) => void;

const props = withDefaults(defineProps<{
  allowVideo?: boolean;
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
  description: '支持标题、样式、链接、表格和代码块；图片仅支持本地上传或粘贴上传。',
  footerText: '可通过工具栏上传或直接粘贴剪贴板图片；媒体文件统一进入系统文件上传台账。',
  height: 'min(520px, 58vh)',
  minHeight: 360,
  modelValue: '',
  placeholder: '请输入富文本内容，可通过工具栏选择或粘贴图片。',
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

const toolbarConfig = computed<Partial<IToolbarConfig>>(() => {
  // 业务编辑场景只保留常用排版、列表、链接、图片、表格和撤销能力，避免默认工具栏过重影响表单维护效率。
  const toolbarKeys = [
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
    // 图片只允许走本地上传并进入 system_file 台账，不开放网络图片地址插入入口。
    ...(props.uploadable ? ['uploadImage'] : []),
    ...(props.allowVideo ? ['insertVideo', ...(props.uploadable ? ['uploadVideo'] : [])] : []),
    'insertTable',
    'codeBlock',
    '|',
    'undo',
    'redo',
  ] as NonNullable<IToolbarConfig['toolbarKeys']>;

  return {
    modalAppendToBody: true,
    toolbarKeys,
  };
});

const editorConfig = computed<Partial<IEditorConfig>>(() => ({
  placeholder: props.placeholder,
  MENU_CONF: {
    uploadImage: {
      allowedFileTypes: ['image/*'],
      maxNumberOfFiles: 10,
      // 富文本图片必须复用后台统一上传入口，禁止插入 base64 或绕过 system_file 台账。
      customUpload(file: File, insertFn: (src: string, alt: string, href: string) => void) {
        void uploadEditorAsset('image', file)
          .then((asset) => {
            const url = getAssetUrl(asset);
            if (!url) throw new Error('图片上传成功但未返回访问地址');
            insertFn(url, asset.origin_name || file.name, '');
          })
          .catch((error) => handleEditorUploadError(error));
      },
    },
    uploadVideo: {
      allowedFileTypes: ['video/*'],
      maxNumberOfFiles: 1,
      // 视频同样进入系统上传机制；正文只持久化可访问 URL，避免保存本地临时路径或 base64。
      customUpload(file: File, insertFn: (src: string, poster: string) => void) {
        void uploadEditorAsset('video', file)
          .then((asset) => {
            const url = getAssetUrl(asset);
            if (!url) throw new Error('视频上传成功但未返回访问地址');
            insertFn(url, asset.preview_url || '');
          })
          .catch((error) => handleEditorUploadError(error));
      },
    },
    insertVideo: {
      checkVideo(src: string) {
        return /^https?:\/\//i.test(src) || src.startsWith('/') ? true : '视频地址必须是 http(s) 或站内绝对路径';
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

async function uploadEditorAsset(scene: 'image' | 'video', file: File) {
  if (!props.uploadable) {
    throw new Error('缺少系统文件上传权限');
  }
  if (scene === 'video' && !props.allowVideo) {
    throw new Error('当前编辑器未启用视频上传');
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
  message.error(`媒体上传失败: ${error instanceof Error ? error.message : String(error)}`);
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
  return `body{margin:0;padding:18px 22px;color:CanvasText;background:Canvas;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.85;}img{max-width:100%;height:auto;border-radius:8px;}video{max-width:100%;border-radius:10px;background:#000;}table{width:100%;border-collapse:collapse;}td,th{padding:8px;border:1px solid ButtonBorder;}blockquote{margin:8px 0;padding:8px 12px;border-left:4px solid Highlight;background:color-mix(in srgb, Highlight 8%, Canvas);}pre{padding:12px;overflow:auto;background:color-mix(in srgb, CanvasText 6%, Canvas);border-radius:8px;}.empty{color:GrayText;}`;
}

async function mountEditor() {
  if (editorMounted.value) return;
  editorMounted.value = true;
  await nextTick();
  richEditorRef.value?.setHtml(contentValue.value || '');
}

function destroyEditor() {
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
  overflow: hidden;
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
  overflow: hidden;
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
