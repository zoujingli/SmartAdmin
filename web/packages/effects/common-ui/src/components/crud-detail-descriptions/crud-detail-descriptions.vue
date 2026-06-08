<template>
  <Descriptions :column="{ md: 2, sm: 1, xs: 1 }" bordered class="crud-detail-descriptions" size="small">
    <slot />
  </Descriptions>
</template>

<script lang="ts" setup>
import { Descriptions } from 'ant-design-vue';
</script>

<style scoped>
.crud-detail-descriptions {
  width: 100%;
}

.crud-detail-descriptions :deep(.ant-descriptions-view) {
  overflow: hidden;
}

.crud-detail-descriptions :deep(.ant-descriptions-item-label) {
  min-width: 104px;
  white-space: nowrap;
  width: 112px;
}

.crud-detail-descriptions :deep(.ant-descriptions-item-content) {
  min-width: 0;
  overflow-wrap: anywhere;
  word-break: break-word;
}

@media (max-width: 767px) {
  .crud-detail-descriptions :deep(.ant-descriptions-item-label) {
    min-width: 88px;
    width: 96px;
  }
}
</style>
