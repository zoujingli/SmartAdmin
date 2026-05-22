<template>
  <Tag :color="booleanValue ? trueColor : falseColor">
    {{ booleanValue ? trueText : falseText }}
  </Tag>
</template>

<script lang="ts" setup>
import { computed } from 'vue';

import { Tag } from 'ant-design-vue';

interface Props {
  falseColor?: string;
  falseText?: string;
  trueColor?: string;
  trueText?: string;
  value?: boolean | number | string;
}

const props = withDefaults(defineProps<Props>(), {
  falseColor: 'red',
  falseText: '禁用',
  trueColor: 'green',
  trueText: '启用',
  value: false,
});

const booleanValue = computed(() => {
  if (typeof props.value === 'string') {
    return props.value === '1' || props.value.toLowerCase() === 'true';
  }

  return Boolean(props.value);
});
</script>
