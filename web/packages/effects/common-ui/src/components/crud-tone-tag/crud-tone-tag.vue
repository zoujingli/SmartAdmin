<template>
  <Tag :color="color">{{ text }}</Tag>
</template>

<script lang="ts" setup>
import { Tag } from 'ant-design-vue';

interface Props {
  color?: string;
  text: string;
}

withDefaults(defineProps<Props>(), {
  color: 'default',
});
</script>
