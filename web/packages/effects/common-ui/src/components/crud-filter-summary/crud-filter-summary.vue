<template>
  <div class="crud-filter-summary">
    <template v-if="items.length > 0">
      <span class="crud-filter-summary__label">当前筛选</span>
      <Tag v-for="item in items" :key="item.label" class="crud-filter-summary__tag">
        {{ item.label }}：{{ item.value }}
      </Tag>
    </template>
    <span v-else class="crud-filter-summary__text">{{ emptyText }}</span>
  </div>
</template>

<script lang="ts" setup>
import { Tag, theme } from 'ant-design-vue';

export interface CrudFilterSummaryItem {
  label: string;
  value: string;
}

interface Props {
  items: CrudFilterSummaryItem[];
  emptyText: string;
}

defineProps<Props>();

const { token } = theme.useToken();
</script>

<style scoped>
.crud-filter-summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border-top: 1px solid v-bind('token.colorBorderSecondary');
  padding-top: 16px;
}

.crud-filter-summary__label {
  color: v-bind('token.colorTextSecondary');
  font-size: 12px;
  font-weight: 600;
}

.crud-filter-summary__text {
  color: v-bind('token.colorTextSecondary');
  font-size: 12px;
}

.crud-filter-summary__tag {
  margin-inline-end: 0;
}
</style>
