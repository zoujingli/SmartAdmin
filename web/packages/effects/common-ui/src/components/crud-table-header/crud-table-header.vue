<template>
  <div class="crud-table-header">
    <div>
      <div class="crud-table-header__title">{{ title }}</div>
      <div class="crud-table-header__desc">{{ description }}</div>
    </div>
    <slot>
      <Tag v-if="countText" :color="countColor">{{ countText }}</Tag>
    </slot>
  </div>
</template>

<script lang="ts" setup>
import { Tag, theme } from 'ant-design-vue';

interface Props {
  title: string;
  description: string;
  countColor?: string;
  countText?: string;
}

withDefaults(defineProps<Props>(), {
  countColor: 'default',
  countText: '',
});

const { token } = theme.useToken();
</script>

<style scoped>
.crud-table-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.crud-table-header__title {
  color: v-bind('token.colorText');
  font-size: 16px;
  font-weight: 700;
  line-height: 1.2;
}

.crud-table-header__desc {
  color: v-bind('token.colorTextSecondary');
  font-size: 13px;
  line-height: 1.6;
  margin-top: 6px;
}

@media (max-width: 768px) {
  .crud-table-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
