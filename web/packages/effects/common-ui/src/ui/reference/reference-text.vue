<template>
  <span class="reference-text">
    <template v-for="(segment, index) in segments" :key="index">
      <span v-if="segment.type === 'text'">{{ segment.text }}</span>
      <span
        v-else
        class="reference-token"
        role="button"
        tabindex="0"
        :title="describe(segment.reference)"
        @click.stop.prevent="openReference(segment.reference)"
        @keydown.enter.stop.prevent="openReference(segment.reference)"
        @keydown.space.stop.prevent="openReference(segment.reference)"
      >{{ referenceClickableText(segment.reference) }}</span>
      <span v-if="segment.type === 'reference'">{{ referenceTrailingText(segment.reference) }}</span>
    </template>
    <ReferenceDetailDrawer v-model:open="drawerOpen" :provider-key="providerKey" :reference="selectedReference" />
  </span>
</template>

<script setup lang="ts">
import type { ReferenceItem } from './types';

import { computed, ref } from 'vue';

import ReferenceDetailDrawer from './reference-detail-drawer.vue';
import { getReferenceProvider } from './registry';
import { parseReferenceSegments, referenceClickableText, referenceTrailingText } from './reference-utils';

const props = withDefaults(defineProps<{
  providerKey?: string;
  value?: number | string | null;
}>(), {
  providerKey: 'default',
  value: '',
});

const segments = computed(() => parseReferenceSegments(String(props.value ?? '')));
const drawerOpen = ref(false);
const selectedReference = ref<null | ReferenceItem>(null);

function describe(reference: ReferenceItem) {
  return getReferenceProvider(props.providerKey)?.describe?.(reference) || `查看 ${reference.raw}`;
}

function openReference(reference: ReferenceItem) {
  selectedReference.value = reference;
  drawerOpen.value = true;
}
</script>

<style scoped>
.reference-text { white-space: pre-wrap; }
.reference-token {
  display: inline;
  max-width: 100%;
  padding: 0;
  margin: 0;
  border: 0;
  appearance: none;
  background: transparent;
  color: var(--ant-colorPrimary, hsl(var(--primary)));
  font: inherit;
  line-height: inherit;
  vertical-align: baseline;
  cursor: pointer;
}
.reference-token:hover,
.reference-token:focus-visible {
  color: var(--ant-colorPrimaryHover, var(--ant-colorPrimary, hsl(var(--primary))));
  text-decoration: underline;
  text-underline-offset: 2px;
}
</style>
