<template>
  <div class="crud-detail-section">
    <div class="crud-detail-section__title">{{ title }}</div>
    <pre v-if="preformatted" class="crud-detail-section__pre">{{ content || '' }}</pre>
    <div v-else class="crud-detail-section__body">
      <slot>{{ content || '-' }}</slot>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { theme } from 'ant-design-vue';

interface Props {
  content?: string;
  preformatted?: boolean;
  title: string;
}

withDefaults(defineProps<Props>(), {
  content: '',
  preformatted: false,
});

const { token } = theme.useToken();
</script>

<style scoped>
.crud-detail-section {
  display: grid;
  gap: 8px;
}

.crud-detail-section__title {
  color: v-bind('token.colorText');
  font-size: 14px;
  font-weight: 600;
}

.crud-detail-section__body {
  color: v-bind('token.colorText');
  line-height: 1.75;
}

.crud-detail-section__pre {
  background: v-bind('token.colorFillQuaternary');
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 8px;
  color: v-bind('token.colorText');
  font-size: 12px;
  line-height: 1.5;
  margin: 0;
  max-height: 220px;
  overflow: auto;
  padding: 10px 12px;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
