<template>
  <Card class="crud-search-panel" :body-style="bodyStyle">
    <Row class="crud-search-grid crud-search-panel__grid" :style="gridStyle">
      <slot />
      <Col class="crud-search-grid__actions">
        <slot name="actions">
          <Space wrap>
            <Button type="primary" :disabled="searchDisabled" :loading="loading" @click="emit('search')">
              <span class="i-lucide-search" />
              {{ searchText }}
            </Button>
            <Button :disabled="loading || resetDisabled" @click="emit('reset')">
              <span class="i-lucide-refresh-cw" />
              {{ resetText }}
            </Button>
          </Space>
        </slot>
      </Col>
    </Row>

    <slot name="summary">
      <CrudFilterSummary v-if="showSummary" :empty-text="emptyText" :items="filterItems" />
    </slot>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Button, Card, Col, Row, Space } from 'ant-design-vue';
import { CrudFilterSummary } from '@vben/common-ui';
import type { CrudFilterSummaryItem } from '@vben/common-ui';

const props = withDefaults(defineProps<{
  bodyStyle?: Record<string, number | string>;
  emptyText?: string;
  filterItems?: CrudFilterSummaryItem[];
  itemWidth?: number | string;
  loading?: boolean;
  resetDisabled?: boolean;
  resetText?: string;
  searchDisabled?: boolean;
  searchText?: string;
  showSummary?: boolean;
}>(), {
  bodyStyle: () => ({ padding: '20px 24px' }),
  emptyText: '当前显示全部记录，可使用筛选条件缩小范围。',
  filterItems: () => [],
  itemWidth: undefined,
  loading: false,
  resetDisabled: false,
  resetText: '重置',
  searchDisabled: false,
  searchText: '搜索',
  showSummary: true,
});

const emit = defineEmits<{
  reset: [];
  search: [];
}>();

const gridStyle = computed(() => {
  const width = normalizeCssSize(props.itemWidth);
  return width ? { '--crud-search-item-width': width } : undefined;
});

function normalizeCssSize(value: number | string | undefined) {
  if (typeof value === 'number' && Number.isFinite(value) && value > 0) {
    return `${value}px`;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    return value.trim();
  }

  return '';
}
</script>

<style scoped>
.crud-search-panel__grid {
  margin-bottom: 16px;
}
</style>
