<template>
  <Modal
    :open="open"
    :title="multiple ? '选择文件' : '选择单个文件'"
    width="960px"
    @cancel="handleCancel"
    @ok="handleConfirm"
  >
    <div class="mb-4 flex items-center gap-3">
      <Input v-model:value="keyword" allow-clear placeholder="搜索文件名" @press-enter="loadFiles" />
      <Button @click="triggerUpload" :disabled="disabled">上传新文件</Button>
      <Button @click="loadFiles">刷新</Button>
      <input
        ref="inputRef"
        hidden
        class="picker-native-input"
        type="file"
        :accept="accept"
        @change="handleUploadSelect"
      />
    </div>
    <div class="picker-grid">
      <div
        v-for="item in items"
        :key="item.id"
        class="picker-card"
        :class="{ 'picker-card-active': isSelected(item) }"
        @click="toggleSelect(item)"
      >
        <div class="picker-preview">
          <Image v-if="isImage(item)" :src="item.preview_url || item.url" class="picker-image" />
          <video
            v-else-if="isVideo(item)"
            :src="item.preview_url || item.url"
            class="picker-video"
            controls
            preload="metadata"
          />
          <div v-else class="picker-file">{{ item.suffix.toUpperCase() || 'FILE' }}</div>
        </div>
        <div class="picker-name" :title="item.origin_name">{{ item.origin_name }}</div>
        <div class="picker-meta">{{ item.size_info }}</div>
      </div>
    </div>
    <div v-if="items.length === 0" class="picker-empty">暂无可选文件</div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';

import { Button, Image, Input, Modal, message, theme } from 'ant-design-vue';

import { getUploadRuntimeConfig, listUploadAssets, resolveUploadAccept, uploadFile } from './upload-client';
import type { UploadAsset } from './types';

const props = withDefaults(
  defineProps<{
    disabled?: boolean;
    driver?: string;
    limit?: number;
    mode?: 'file' | 'image' | 'video';
    multiple?: boolean;
    open: boolean;
    scene?: string;
  }>(),
  {
    disabled: false,
    limit: 1,
    mode: 'file',
    multiple: false,
  },
);

const emit = defineEmits<{
  (event: 'cancel'): void;
  (event: 'select', value: UploadAsset[]): void;
  (event: 'update:open', value: boolean): void;
}>();

const items = ref<UploadAsset[]>([]);
const keyword = ref('');
const inputRef = ref<HTMLInputElement>();
const selectedIds = ref<number[]>([]);
const selectedMap = ref<Record<number, UploadAsset>>({});
const accept = ref('');
const { token } = theme.useToken();

watch(
  () => props.open,
  async (open) => {
    if (!open) {
      selectedIds.value = [];
      selectedMap.value = {};
      return;
    }

    selectedIds.value = [];
    selectedMap.value = {};
    const runtime = await getUploadRuntimeConfig();
    accept.value = resolveUploadAccept(runtime, props.scene);
    await loadFiles();
  },
);

async function loadFiles() {
  const response = await listUploadAssets({
    origin_name: keyword.value,
    page: 1,
    pageSize: 24,
    scene: props.scene,
  });
  items.value = response.items || [];
}

function triggerUpload() {
  inputRef.value?.click();
}

async function handleUploadSelect(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  target.value = '';
  if (!file) {
    return;
  }

  try {
    const asset = await uploadFile(file, { driver: props.driver, mode: props.scene });
    message.success('上传成功');
    await loadFiles();
    toggleSelect(asset, true);
  } catch (error: any) {
    message.error(error?.message || '上传失败');
  }
}

function toggleSelect(item: UploadAsset, force = false) {
  if (!props.multiple) {
    selectedIds.value = [item.id];
    selectedMap.value = { [item.id]: item };
    return;
  }

  if (selectedIds.value.includes(item.id) && !force) {
    selectedIds.value = selectedIds.value.filter((id) => id !== item.id);
    delete selectedMap.value[item.id];
    selectedMap.value = { ...selectedMap.value };
    return;
  }

  if (selectedIds.value.length >= props.limit) {
    message.warning(`最多选择 ${props.limit} 个文件`);
    return;
  }

  selectedIds.value = [...selectedIds.value, item.id];
  selectedMap.value = {
    ...selectedMap.value,
    [item.id]: item,
  };
}

function isSelected(item: UploadAsset) {
  return selectedIds.value.includes(item.id);
}

function handleConfirm() {
  emit('select', selectedIds.value.map((id) => selectedMap.value[id]).filter(Boolean) as UploadAsset[]);
  emit('update:open', false);
}

function handleCancel() {
  emit('cancel');
  emit('update:open', false);
}

function isImage(item: UploadAsset) {
  return item.mime_type.startsWith('image/');
}

function isVideo(item: UploadAsset) {
  return item.mime_type.startsWith('video/');
}
</script>

<style scoped>
.picker-native-input {
  display: none !important;
}

.picker-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  max-height: 520px;
  overflow: auto;
}

.picker-card {
  border: 1px solid v-bind('token.colorBorder');
  border-radius: 12px;
  cursor: pointer;
  padding: 12px;
  transition: all 0.2s ease;
}

.picker-card-active {
  border-color: v-bind('token.colorPrimary');
  box-shadow: 0 0 0 2px v-bind('`${token.colorPrimary}1f`');
}

.picker-preview {
  align-items: center;
  background: v-bind('token.colorFillTertiary');
  border-radius: 10px;
  display: flex;
  height: 128px;
  justify-content: center;
  margin-bottom: 10px;
  overflow: hidden;
}

.picker-image,
.picker-video {
  height: 100%;
  object-fit: cover;
  width: 100%;
}

.picker-file {
  color: v-bind('token.colorTextSecondary');
  font-size: 20px;
  font-weight: 700;
}

.picker-name {
  color: v-bind('token.colorText');
  font-size: 14px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.picker-meta {
  color: v-bind('token.colorTextSecondary');
  font-size: 12px;
  margin-top: 4px;
}

.picker-empty {
  color: v-bind('token.colorTextDescription');
  font-size: 14px;
  padding: 32px 0;
  text-align: center;
}
</style>
