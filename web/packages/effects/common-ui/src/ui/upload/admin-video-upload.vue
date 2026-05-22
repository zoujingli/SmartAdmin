<script setup lang="ts">
import type { UploadAsset, UploadFieldProps, UploadFieldValue } from './types';

import AdminUploadField from './upload-field.vue';

const props = defineProps<UploadFieldProps>();
const emit = defineEmits<{
  (event: 'change', value: UploadFieldValue): void;
  (event: 'error', error: Error): void;
  (event: 'exceed', value: number): void;
  (event: 'remove', value: number): void;
  (event: 'success', value: UploadAsset): void;
  (event: 'update:modelValue', value: UploadFieldValue): void;
}>();
</script>

<template>
  <AdminUploadField
    v-bind="props"
    mode="video"
    @change="(value) => emit('change', value)"
    @error="(error) => emit('error', error)"
    @exceed="(value) => emit('exceed', value)"
    @remove="(value) => emit('remove', value)"
    @success="(value) => emit('success', value)"
    @update:model-value="(value) => emit('update:modelValue', value)"
  />
</template>
