<template>
  <span v-if="normalizedItems.length === 0">{{ emptyText }}</span>
  <Space v-else wrap>
    <Tag v-for="item in normalizedItems" :key="item" :color="color">{{ item }}</Tag>
  </Space>
</template>

<script lang="ts" setup>
import { computed } from 'vue';

import { Space, Tag } from 'ant-design-vue';

interface Props {
  color?: string;
  emptyText?: string;
  items?: Array<number | string | undefined>;
}

const props = withDefaults(defineProps<Props>(), {
  color: undefined,
  emptyText: '-',
  items: () => [],
});

const normalizedItems = computed(() =>
  props.items
    .map((item) => String(item ?? '').trim())
    .filter((item) => item.length > 0),
);
</script>
