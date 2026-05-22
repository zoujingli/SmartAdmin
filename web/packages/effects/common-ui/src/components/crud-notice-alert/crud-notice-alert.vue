<template>
  <Alert
    :class="customClass"
    :description="description"
    :message="message"
    :type="type"
    show-icon
  />
</template>

<script lang="ts" setup>
import { Alert } from 'ant-design-vue';

interface Props {
  customClass?: string;
  description?: string;
  message: string;
  type?: 'error' | 'info' | 'success' | 'warning';
}

withDefaults(defineProps<Props>(), {
  customClass: '',
  description: '',
  type: 'info',
});
</script>
