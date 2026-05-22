<template>
  <div class="crud-selection-bar">
    <Tag color="default">已选 {{ count }} 项</Tag>
    <div class="crud-selection-bar__actions">
      <slot />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { Tag } from 'ant-design-vue';

interface Props {
  count: number;
}

defineProps<Props>();
</script>

<style scoped>
.crud-selection-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.crud-selection-bar__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 768px) {
  .crud-selection-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .crud-selection-bar__actions {
    justify-content: flex-start;
  }
}
</style>
