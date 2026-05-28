<script lang="ts" setup>
import { Fallback } from '@vben/common-ui';

import { useFallbackHomePath } from './use-fallback-home-path';

defineOptions({ name: 'Fallback404' });

const homePath = useFallbackHomePath();
</script>

<template>
  <Fallback :home-path="homePath" status="404" />
</template>
