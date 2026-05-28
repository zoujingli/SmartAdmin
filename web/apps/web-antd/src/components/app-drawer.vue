<template>
  <CommonAppDrawer v-bind="$attrs">
    <template v-for="(_, slotName) in $slots" #[slotName]="slotProps" :key="slotName">
      <slot :name="slotName" v-bind="slotProps || {}" />
    </template>
  </CommonAppDrawer>
</template>

<script setup lang="ts">
import { AppDrawer as CommonAppDrawer } from '@vben/common-ui';

defineOptions({
  inheritAttrs: false,
  name: 'AppDrawer',
});
</script>
