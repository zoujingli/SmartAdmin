<template>
  <div class="crud-empty-state" :class="`crud-empty-state--${size}`">
    <Empty :description="description" />
  </div>
</template>

<script lang="ts" setup>
import { Empty } from 'ant-design-vue';

interface Props {
  description: string;
  size?: 'lg' | 'md' | 'sm';
}

withDefaults(defineProps<Props>(), {
  size: 'md',
});
</script>

<style scoped>
.crud-empty-state {
  display: flex;
  justify-content: center;
}

.crud-empty-state--sm {
  padding: 16px 0;
}

.crud-empty-state--md {
  padding: 24px 0;
}

.crud-empty-state--lg {
  padding: 32px 0;
}
</style>
