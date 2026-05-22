<template>
  <Descriptions :column="2" bordered size="small">
    <slot />
  </Descriptions>
</template>

<script lang="ts" setup>
import { Descriptions } from 'ant-design-vue';
</script>
