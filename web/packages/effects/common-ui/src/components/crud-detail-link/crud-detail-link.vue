<template>
  <span v-if="!href">{{ emptyText }}</span>
  <div v-else class="crud-detail-link">
    <a
      :href="href"
      class="crud-detail-link__text"
      rel="noreferrer"
      target="_blank"
      :title="displayText"
    >
      {{ displayText }}
    </a>
    <Button v-if="copyable" size="small" @click="handleCopy">复制</Button>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';

import { Button, message, theme } from 'ant-design-vue';

interface Props {
  copyLabel?: string;
  copyable?: boolean;
  emptyText?: string;
  href?: string;
  text?: string;
}

const props = withDefaults(defineProps<Props>(), {
  copyLabel: '链接',
  copyable: true,
  emptyText: '-',
  href: '',
  text: '',
});

const { token } = theme.useToken();

const displayText = computed(() => props.text || props.href);

async function handleCopy() {
  if (!props.href) {
    return;
  }

  try {
    await navigator.clipboard.writeText(props.href);
    message.success(`${props.copyLabel}已复制`);
  } catch {
    message.error(`${props.copyLabel}复制失败`);
  }
}
</script>

<style scoped>
.crud-detail-link {
  align-items: center;
  display: flex;
  max-width: 100%;
  gap: 10px;
  min-width: 0;
  overflow: hidden;
  width: 100%;
}

.crud-detail-link__text {
  color: v-bind('token.colorPrimary');
  display: block;
  flex: 1 1 0;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.crud-detail-link :deep(.ant-btn) {
  flex: 0 0 auto;
}

@media (max-width: 767px) {
  .crud-detail-link {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
