<template>
  <div class="admin-upload-field">
    <div v-if="mode === 'file'" class="space-y-3">
      <div class="admin-upload-inline">
        <Input
          :value="fileInputValue"
          :placeholder="placeholder"
          :allow-clear="singleValueClearable"
          class="admin-upload-inline__input"
          @beforeinput="preventInputBeforeInput"
          @drop.prevent
          @keydown="preventInputKeydown"
          @paste.prevent
          @update:value="handleInputValueUpdate"
        />
        <div class="admin-upload-inline__actions">
          <Button type="primary" :disabled="disabled || readonly || uploading" @click="triggerFileInput">
            {{ primaryActionText }}
          </Button>
          <Button
            v-if="allowSelectExisting"
            :disabled="disabled || readonly"
            @click="pickerOpen = true"
          >
            选择已有
          </Button>
        </div>
      </div>
      <div
        v-if="props.multiple && normalizedValue.length > 0"
        class="upload-list-panel space-y-2"
      >
        <div v-for="item in normalizedValue" :key="item.id" class="flex items-center justify-between gap-3">
          <a
            :href="item.download_url || item.url"
            class="upload-link"
            :download="item.origin_name || undefined"
            rel="noopener noreferrer"
            target="_blank"
          >
            {{ item.origin_name }}
          </a>
          <Button type="link" danger size="small" :disabled="disabled || readonly" @click="removeItem(item.id)">
            移除
          </Button>
        </div>
      </div>
    </div>

    <div v-else-if="mode === 'image'" class="space-y-3">
      <div class="admin-upload-inline">
        <Tooltip placement="rightTop">
          <template #title>
            <div v-if="imagePreviewSource" class="image-tooltip-preview">
              <div class="image-tooltip-preview__frame">
                <Image :preview="false" :src="imagePreviewSource" class="image-tooltip-preview__image" />
              </div>
              <div class="image-tooltip-preview__meta">
                <div class="image-tooltip-preview__name">{{ primaryAsset?.origin_name || '图片预览' }}</div>
                <div class="image-tooltip-preview__url">{{ imagePreviewSource }}</div>
              </div>
            </div>
            <span v-else>暂无图片</span>
          </template>
          <Input
            :value="imageInputValue"
            :placeholder="placeholder || '请上传图片'"
            :allow-clear="singleValueClearable"
            class="admin-upload-inline__input image-upload-input"
            @beforeinput="preventInputBeforeInput"
            @drop.prevent
            @keydown="preventInputKeydown"
            @paste.prevent
            @update:value="handleInputValueUpdate"
          />
        </Tooltip>
        <div class="admin-upload-inline__actions">
          <Button type="primary" :disabled="disabled || readonly || uploading" @click="triggerFileInput">
            {{ primaryActionText }}
          </Button>
          <Button
            v-if="allowSelectExisting"
            :disabled="disabled || readonly"
            @click="pickerOpen = true"
          >
            选择已有
          </Button>
        </div>
      </div>
    </div>

    <div v-else-if="mode === 'video' && !multiple" class="space-y-3">
      <div class="admin-upload-inline">
        <Input
          :value="videoInputValue"
          :placeholder="placeholder || '请上传视频'"
          :allow-clear="singleValueClearable"
          class="admin-upload-inline__input"
          @beforeinput="preventInputBeforeInput"
          @drop.prevent
          @keydown="preventInputKeydown"
          @paste.prevent
          @update:value="handleInputValueUpdate"
        />
        <div class="admin-upload-inline__actions">
          <Button type="primary" :disabled="disabled || readonly || uploading" @click="triggerFileInput">
            {{ primaryActionText }}
          </Button>
          <Button
            v-if="allowSelectExisting"
            :disabled="disabled || readonly"
            @click="pickerOpen = true"
          >
            选择已有
          </Button>
        </div>
      </div>
    </div>

    <div v-else class="upload-grid">
      <div v-for="item in normalizedValue" :key="item.id" class="upload-card">
        <div class="upload-preview">
          <video :src="item.preview_url || item.url" class="upload-video" controls preload="metadata" />
        </div>
        <div class="upload-footer">
          <span class="upload-title" :title="item.origin_name">{{ item.origin_name }}</span>
          <Button type="link" danger size="small" :disabled="disabled || readonly" @click="removeItem(item.id)">
            移除
          </Button>
        </div>
      </div>
      <button
        v-if="canAppend"
        class="upload-trigger"
        type="button"
        :disabled="disabled || readonly || uploading"
        @click="triggerFileInput"
      >
        {{ primaryActionText }}
      </button>
      <Button
        v-if="allowSelectExisting && canAppend"
        class="mt-2"
        :disabled="disabled || readonly"
        @click="pickerOpen = true"
      >
        选择已有
      </Button>
    </div>

    <input
      ref="inputRef"
      hidden
      class="admin-upload-native-input"
      type="file"
      :accept="accept"
      :multiple="multiple"
      @change="handleFileChange"
    />
    <AdminFilePicker
      v-model:open="pickerOpen"
      :disabled="disabled || readonly"
      :driver="driver"
      :limit="limit"
      :mode="mode"
      :multiple="multiple"
      :scene="scene"
      @select="handlePickerSelect"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { Button, Image, Input, Tooltip, message, theme } from 'ant-design-vue';

import AdminFilePicker from './file-picker.vue';
import { getUploadRuntimeConfig, resolveUploadAccept, uploadFile } from './upload-client';
import type { UploadAsset, UploadFieldProps, UploadFieldValue } from './types';

const props = withDefaults(
  defineProps<UploadFieldProps>(),
  {
    allowSelectExisting: true,
    buttonText: '',
    clearable: true,
    disabled: false,
    limit: 1,
    mode: 'file',
    multiple: false,
    placeholder: '请选择文件',
    readonly: false,
    sortable: false,
  },
);

const emit = defineEmits<{
  (event: 'change', value: UploadFieldValue): void;
  (event: 'error', error: Error): void;
  (event: 'exceed', value: number): void;
  (event: 'remove', value: number): void;
  (event: 'success', value: UploadAsset): void;
  (event: 'update:modelValue', value: UploadFieldValue): void;
}>();

const inputRef = ref<HTMLInputElement>();
const pickerOpen = ref(false);
const progressPercent = ref(0);
const uploading = ref(false);
const accept = ref('');
const { token } = theme.useToken();

const normalizedValue = computed<UploadAsset[]>(() => {
  if (!props.modelValue) {
    return [];
  }

  return Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue];
});

const fileInputValue = computed(() =>
  normalizedValue.value.map((item) => item.url || item.download_url || item.origin_name).join('，'),
);
const primaryAsset = computed(() => normalizedValue.value[0] || null);
const imagePreviewSource = computed(() => primaryAsset.value?.preview_url || primaryAsset.value?.url || '');
const imageInputValue = computed(() =>
  normalizedValue.value.map((item) => item.url || item.preview_url || item.origin_name).join('，'),
);
const videoInputValue = computed(() =>
  normalizedValue.value.map((item) => item.url || item.preview_url || item.origin_name).join('，'),
);
const canAppend = computed(() =>
  props.multiple ? normalizedValue.value.length < props.limit : normalizedValue.value.length === 0,
);
const singleValueClearable = computed(
  () => !props.multiple && props.clearable && normalizedValue.value.length > 0 && !props.disabled && !props.readonly,
);
const primaryActionText = computed(() => {
  if (uploading.value) {
    return `上传中 ${progressPercent.value}%`;
  }

  if (props.buttonText) {
    return props.buttonText;
  }

  if (props.mode === 'image') {
    return '上传图片';
  }

  if (props.mode === 'video') {
    return '上传视频';
  }

  return '上传文件';
});

watch(
  () => props.scene,
  async () => {
    const runtime = await getUploadRuntimeConfig();
    accept.value = resolveUploadAccept(runtime, props.scene);
  },
  { immediate: true },
);

function updateValue(items: UploadAsset[]) {
  const nextValue = props.multiple ? items : (items[0] || null);
  emit('update:modelValue', nextValue);
  emit('change', nextValue);
}

function triggerFileInput() {
  inputRef.value?.click();
}

async function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement;
  const files = Array.from(target.files || []);
  target.value = '';
  await appendFiles(files);
}

async function appendFiles(files: File[]) {
  if (files.length === 0) {
    return;
  }

  if (normalizedValue.value.length + files.length > props.limit && props.multiple) {
    emit('exceed', props.limit);
    message.warning(`最多上传 ${props.limit} 个文件`);
    return;
  }

  uploading.value = true;
  progressPercent.value = 0;
  try {
    const current = props.multiple ? [...normalizedValue.value] : [];
    for (const file of files) {
      const asset = await uploadFile(file, {
        driver: props.driver,
        mode: props.scene,
        onProgress: (percent) => {
          progressPercent.value = percent;
        },
        uploadType: props.uploadType,
      });
      emit('success', asset);
      if (props.multiple) {
        current.push(asset);
      } else {
        updateValue([asset]);
      }
    }

    if (props.multiple) {
      updateValue(current.slice(0, props.limit));
    }
  } catch (error: any) {
    emit('error', error);
    message.error(error?.message || '上传失败');
  } finally {
    uploading.value = false;
    progressPercent.value = 0;
  }
}

function handlePickerSelect(items: UploadAsset[]) {
  if (props.multiple) {
    updateValue([...normalizedValue.value, ...items].slice(0, props.limit));
    return;
  }

  updateValue(items.slice(0, 1));
}

function removeItem(id: number) {
  const nextValue = normalizedValue.value.filter((item) => item.id !== id);
  updateValue(nextValue);
  emit('remove', id);
}

function clearValue() {
  updateValue([]);
}

function handleInputValueUpdate(value: string) {
  if (value === '') {
    clearValue();
  }
}

function preventInputBeforeInput(event: Event) {
  event.preventDefault();
}

function preventInputKeydown(event: KeyboardEvent) {
  if (event.metaKey || event.ctrlKey || event.altKey) {
    return;
  }

  if (event.key.length === 1 || event.key === 'Backspace' || event.key === 'Delete' || event.key === 'Enter') {
    event.preventDefault();
  }
}
</script>

<style scoped>
.admin-upload-field {
  width: 100%;
}

.admin-upload-inline {
  align-items: center;
  display: flex;
  gap: 12px;
  width: 100%;
}

.admin-upload-inline__input {
  flex: 1;
  min-width: 0;
}

.admin-upload-inline__actions {
  display: flex;
  flex-shrink: 0;
  gap: 8px;
}

.admin-upload-native-input {
  display: none !important;
}

.admin-upload-inline__input :deep(.ant-input) {
  caret-color: transparent;
}

.image-upload-input {
  cursor: pointer;
}

.upload-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.image-tooltip-preview {
  max-width: 260px;
}

.image-tooltip-preview__frame {
  background: var(--ant-color-bg-container, #fff);
  border-radius: 12px;
  overflow: hidden;
}

.image-tooltip-preview__image {
  display: block;
  height: 180px;
  object-fit: cover;
  width: 100%;
}

.image-tooltip-preview__meta {
  margin-top: 8px;
}

.image-tooltip-preview__name {
  color: var(--ant-color-text, inherit);
  font-size: 12px;
  font-weight: 600;
  line-height: 18px;
}

.image-tooltip-preview__url {
  color: var(--ant-color-text-description, inherit);
  font-size: 12px;
  line-height: 18px;
  margin-top: 4px;
  overflow-wrap: anywhere;
}

.upload-list-panel {
  border: 1px solid v-bind('token.colorBorder');
  border-radius: 8px;
  padding: 12px;
}

.upload-link {
  color: v-bind('token.colorPrimary');
  font-size: 14px;
  line-height: 22px;
}

.upload-link:hover {
  color: v-bind('token.colorPrimaryHover');
}

.upload-card,
.upload-trigger {
  background: v-bind('token.colorBgContainer');
  border: 1px dashed v-bind('token.colorBorder');
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  min-height: 220px;
  overflow: hidden;
  width: 180px;
}

.upload-card {
  border-style: solid;
}

.upload-trigger {
  align-items: center;
  color: v-bind('token.colorTextSecondary');
  cursor: pointer;
  font-size: 14px;
  justify-content: center;
  padding: 16px;
}

.upload-preview {
  background: v-bind('token.colorFillTertiary');
  flex: 1;
  min-height: 156px;
  overflow: hidden;
}

.upload-image,
.upload-video {
  height: 100%;
  object-fit: cover;
  width: 100%;
}

.upload-footer {
  align-items: center;
  display: flex;
  gap: 8px;
  justify-content: space-between;
  padding: 12px;
}

.upload-title {
  color: v-bind('token.colorText');
  flex: 1;
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
