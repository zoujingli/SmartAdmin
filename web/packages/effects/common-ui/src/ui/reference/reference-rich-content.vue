<template>
  <div class="reference-rich-content-wrap">
    <div class="reference-rich-content" v-html="html" @click="handleClick" @keydown.enter="handleKeydown" @keydown.space="handleKeydown"></div>
    <ReferenceDetailDrawer v-model:open="drawerOpen" :mask-closable="maskClosable" :provider-key="providerKey" :reference="selectedReference" :rich-content-mode="richContentMode" />
  </div>
</template>

<script setup lang="ts">
import type { ReferenceItem } from './types';

import { computed, ref } from 'vue';

import ReferenceDetailDrawer from './reference-detail-drawer.vue';
import { referenceFromDataset, renderReferenceHtml } from './reference-utils';
import { normalizeAttachmentLinkLabels } from '../rich-text/attachment-label';

const props = withDefaults(defineProps<{
  onReferenceClick?: (reference: ReferenceItem) => boolean | void;
  maskClosable?: boolean;
  providerKey?: string;
  richContentMode?: 'inline' | 'thumbnail';
  value?: string;
}>(), {
  maskClosable: true,
  providerKey: 'default',
  richContentMode: 'inline',
  value: '',
});

const html = computed(() => normalizeAttachmentHtml(renderReferenceHtml(props.value || '')));
const drawerOpen = ref(false);
const selectedReference = ref<null | ReferenceItem>(null);

function openFromEvent(event: Event) {
  const target = event.target instanceof HTMLElement ? event.target.closest<HTMLElement>('[data-reference-token="1"]') : null;
  if (!target) return;
  const reference = referenceFromDataset(target);
  if (!reference) return;
  event.preventDefault();
  event.stopPropagation();
  if (props.onReferenceClick?.(reference) === false) {
    return;
  }
  selectedReference.value = reference;
  drawerOpen.value = true;
}

function handleClick(event: MouseEvent) {
  openFromEvent(event);
}

function handleKeydown(event: KeyboardEvent) {
  openFromEvent(event);
}

function normalizeAttachmentHtml(value: string) {
  if (!value || typeof document === 'undefined' || typeof document.createElement !== 'function') {
    return value;
  }
  const template = document.createElement('template');
  template.innerHTML = value;
  normalizeAttachmentLinkLabels(template.content);

  return template.innerHTML;
}
</script>

<style scoped>
.reference-rich-content-wrap { min-width: 0; }
.reference-rich-content { color: var(--ant-colorText, hsl(var(--foreground))); line-height: 1.7; white-space: pre-wrap; }
.reference-rich-content :deep(.reference-token) {
  display: inline;
  max-width: 100%;
  padding: 0;
  margin: 0;
  border: 0;
  background: transparent;
  color: var(--ant-colorPrimary, hsl(var(--primary)));
  font: inherit;
  line-height: inherit;
  vertical-align: baseline;
  cursor: pointer;
}
.reference-rich-content :deep(.reference-token:hover),
.reference-rich-content :deep(.reference-token:focus-visible) {
  color: var(--ant-colorPrimaryHover, var(--ant-colorPrimary, hsl(var(--primary))));
  text-decoration: underline;
  text-underline-offset: 2px;
}
.reference-rich-content :deep(img) { max-width: 100%; height: auto; border-radius: 8px; }
.reference-rich-content :deep(video) {
  display: block;
  max-width: 100%;
  height: auto;
  max-height: min(420px, 70vh);
  border-radius: 8px;
  background: #000;
}
.reference-rich-content :deep(a[data-project-file='1']) {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  max-width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 8px;
  background: var(--ant-colorFillQuaternary, hsl(var(--muted)));
  color: var(--ant-colorPrimary, hsl(var(--primary)));
  font-weight: 600;
  line-height: 1.4;
  text-decoration: none;
  overflow-wrap: anywhere;
  white-space: normal;
}
.reference-rich-content :deep(a[data-project-file='1']:hover),
.reference-rich-content :deep(a[data-project-file='1']:focus-visible) {
  border-color: var(--ant-colorPrimaryBorder, var(--ant-colorPrimary, hsl(var(--primary))));
  background: var(--ant-colorPrimaryBg, hsl(var(--primary) / 0.12));
  text-decoration: underline;
  text-underline-offset: 2px;
}
.reference-rich-content :deep(table) { width: 100%; border-collapse: collapse; }
.reference-rich-content :deep(td), .reference-rich-content :deep(th) { padding: 8px; border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); }
</style>
