<template>
  <Card class="crud-table-panel" :body-style="bodyStyle">
    <slot name="header">
      <CrudTableHeader
        v-if="hasHeader && $slots['header-extra']"
        :count-color="countColor"
        :count-text="countText"
        :description="description"
        :title="title"
      >
        <slot name="header-extra" />
      </CrudTableHeader>
      <CrudTableHeader
        v-else-if="hasHeader"
        :count-color="countColor"
        :count-text="countText"
        :description="description"
        :title="title"
      />
    </slot>
    <slot />
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Card } from 'ant-design-vue';
import { CrudTableHeader } from '@vben/common-ui';

const props = withDefaults(defineProps<{
  bodyStyle?: Record<string, number | string>;
  countColor?: string;
  countText?: string;
  description?: string;
  title?: string;
}>(), {
  bodyStyle: () => ({}),
  countColor: 'default',
  countText: '',
  description: '',
  title: '',
});

const hasHeader = computed(() => Boolean(props.title || props.description || props.countText));
</script>
