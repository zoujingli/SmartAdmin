<template>
  <Drawer :body-style="{ padding: '20px 24px' }" :open="open" :title="drawerTitle" width="min(920px, calc(100vw - 24px))" @close="close">
    <div v-if="loading" class="reference-detail-state"><Spin /> <span>正在读取引用数据...</span></div>
    <Alert v-else-if="error" show-icon type="warning" :message="error" />
    <div v-else-if="detail" class="reference-detail-panel">
      <section class="reference-detail-hero">
        <div class="reference-detail-head">
          <Tag color="processing">{{ detail.type_text || detail.type || '引用' }}</Tag>
          <span class="reference-detail-id">ID {{ detail.id }}</span>
        </div>
        <h3 class="reference-detail-title">{{ detailTitle }}</h3>
        <p
          v-if="detail.subtitle"
          class="reference-detail-subtitle"
          v-html="renderInlineText(detail.subtitle)"
          @click="handleRichReference"
          @keydown.enter="handleRichReference"
          @keydown.space="handleRichReference"
        ></p>
        <div v-if="detail.tags?.length" class="reference-detail-tags">
          <Tag v-for="tag in detail.tags" :key="`${tag.label}-${tag.color || ''}`" :color="tag.color">{{ tag.label }}</Tag>
        </div>
        <Alert v-if="detail.available === false" show-icon type="warning" :message="detail.message || '引用不可用'" />
      </section>

      <section v-if="detail.chain?.length" class="reference-detail-section">
        <div class="reference-detail-section-title">数据链路</div>
        <div class="reference-detail-chain">
          <template v-for="(item, index) in detail.chain" :key="`${item.type || 'node'}-${item.id || index}`">
            <button
              class="reference-detail-chain-item"
              :class="{ 'is-active': isActiveChainItem(item) }"
              :disabled="!canOpenChainItem(item)"
              type="button"
              @click="openChainItem(item)"
            >
              <span class="reference-detail-chain-type">{{ item.type_text || item.type || '节点' }}</span>
              <span class="reference-detail-chain-label">{{ chainItemDisplayText(item) }}</span>
            </button>
            <span v-if="index < detail.chain.length - 1" class="reference-detail-chain-arrow">→</span>
          </template>
        </div>
      </section>

      <section v-if="detail.fields?.length" class="reference-detail-section">
        <div class="reference-detail-section-title">关键字段</div>
        <Descriptions :column="2" bordered size="small" class="reference-detail-fields">
          <DescriptionsItem v-for="field in detail.fields" :key="field.label">
            <template #label>
              <span
                class="reference-detail-field-label-text"
                v-html="renderInlineText(field.label)"
                @click="handleRichReference"
                @keydown.enter="handleRichReference"
                @keydown.space="handleRichReference"
              ></span>
            </template>
            <span class="reference-detail-inline-text" v-html="renderInlineText(field.value)" @click="handleRichReference" @keydown.enter="handleRichReference" @keydown.space="handleRichReference"></span>
          </DescriptionsItem>
        </Descriptions>
      </section>

      <section v-if="!detail.sections?.length && detail.description" class="reference-detail-section">
        <div class="reference-detail-section-title">完整内容</div>
        <p class="reference-detail-description">{{ detail.description }}</p>
      </section>

      <section v-for="section in detail.sections || []" :key="section.title" class="reference-detail-section reference-detail-card">
        <div class="reference-detail-section-title">{{ section.title }}</div>
        <div
          v-if="section.content_html"
          class="reference-detail-rich-content"
          v-html="renderSectionHtml(section.content_html)"
          @click="handleRichReference"
          @keydown.enter="handleRichReference"
          @keydown.space="handleRichReference"
        ></div>
        <p
          v-else-if="section.content"
          class="reference-detail-description"
          v-html="renderInlineText(section.content)"
          @click="handleRichReference"
          @keydown.enter="handleRichReference"
          @keydown.space="handleRichReference"
        ></p>
        <div v-if="section.fields?.length" class="reference-detail-field-list">
          <div v-for="field in section.fields" :key="`${section.title}-${field.label}`" class="reference-detail-field-row">
            <span
              class="reference-detail-field-label"
              v-html="renderInlineText(field.label)"
              @click="handleRichReference"
              @keydown.enter="handleRichReference"
              @keydown.space="handleRichReference"
            ></span>
            <span
              class="reference-detail-field-value"
              v-html="renderInlineText(field.value)"
              @click="handleRichReference"
              @keydown.enter="handleRichReference"
              @keydown.space="handleRichReference"
            ></span>
          </div>
        </div>
      </section>
    </div>
    <Alert v-else show-icon type="info" message="请选择一个引用标签" />
  </Drawer>
</template>

<script setup lang="ts">
import type { ReferenceChainItem, ReferenceDetail, ReferenceItem, ReferencePrefix } from './types';

import { computed, ref, watch } from 'vue';
import { Alert, Descriptions, DescriptionsItem, Drawer, Spin, Tag } from 'ant-design-vue';

import { getReferenceProvider } from './registry';
import { referenceDisplayText, referenceFromDataset, renderReferenceHtml } from './reference-utils';

const props = withDefaults(defineProps<{
  open?: boolean;
  providerKey?: string;
  reference?: null | ReferenceItem;
}>(), {
  open: false,
  providerKey: 'default',
  reference: null,
});

const emit = defineEmits<{
  'update:open': [value: boolean];
}>();

const typeReferenceMap: Record<string, { code: string; prefix: ReferencePrefix }> = {
  feature: { code: 'f', prefix: '#' },
  module: { code: 'f', prefix: '#' },
  product: { code: 'p', prefix: '#' },
  task: { code: 's', prefix: '#' },
  test: { code: 't', prefix: '#' },
  user: { code: 'u', prefix: '@' },
  version: { code: 'v', prefix: '#' },
};

const loading = ref(false);
const error = ref('');
const detail = ref<null | ReferenceDetail>(null);
const activeReference = ref<null | ReferenceItem>(null);
const displayReference = computed(() => activeReference.value ? referenceDisplayText(withResolvedReferenceLabel(activeReference.value)) : '');
const detailTitle = computed(() => displayReference.value || detail.value?.title || '引用详情');
const drawerTitle = computed(() => displayReference.value ? `数据引用 ${displayReference.value}` : '数据引用');

function close() {
  emit('update:open', false);
}

function displayValue(value: unknown) {
  if (value === undefined || value === null || value === '') return '-';
  return String(value);
}

function withResolvedReferenceLabel(reference: ReferenceItem): ReferenceItem {
  if (reference.label) return reference;
  const resolved = detail.value;
  if (!resolved?.title || Number(resolved.id || 0) !== Number(reference.id || 0)) return reference;
  const fallback = typeReferenceMap[String(resolved.type || '').toLowerCase()];
  const detailCode = String(fallback?.code || resolved.type || '').toLowerCase();
  if (detailCode && detailCode !== reference.code) return reference;

  return { ...reference, label: resolved.title };
}

async function loadDetail() {
  if (!props.open || !activeReference.value) return;
  const provider = getReferenceProvider(props.providerKey);
  if (!provider) {
    detail.value = null;
    error.value = '引用数据提供器未注册';
    return;
  }
  const currentReference = activeReference.value;
  loading.value = true;
  error.value = '';
  try {
    const resolved = await provider.resolve(currentReference);
    if (sameReference(activeReference.value, currentReference)) {
      detail.value = resolved;
    }
  } catch (err: any) {
    detail.value = null;
    error.value = err?.message || '引用数据读取失败';
  } finally {
    loading.value = false;
  }
}

function renderSectionHtml(value: string) {
  return renderReferenceHtml(value);
}

function renderInlineText(value: unknown) {
  return renderReferenceHtml(escapeHtml(displayValue(value)));
}

function chainItemReference(item: ReferenceChainItem): null | ReferenceItem {
  const id = Number(item.id || 0);
  const fallback = typeReferenceMap[String(item.type || '').toLowerCase()];
  const code = String(item.code || fallback?.code || '').toLowerCase();
  const prefix = item.prefix === '@' || item.prefix === '#' ? item.prefix : fallback?.prefix;
  if (!code || !prefix || id <= 0) return null;

  return {
    code,
    id,
    label: item.label,
    prefix,
    raw: item.raw || `${prefix}${code}${id}`,
    type: code,
  };
}

function sameReference(left: null | ReferenceItem, right: null | ReferenceItem) {
  return !!left && !!right && left.prefix === right.prefix && left.code === right.code && left.id === right.id;
}

function canOpenChainItem(item: ReferenceChainItem) {
  return chainItemReference(item) !== null;
}

function chainItemDisplayText(item: ReferenceChainItem) {
  const reference = chainItemReference(item);
  return reference ? referenceDisplayText(reference) : (item.label || '-');
}

function isActiveChainItem(item: ReferenceChainItem) {
  return sameReference(activeReference.value, chainItemReference(item));
}

function openChainItem(item: ReferenceChainItem) {
  const reference = chainItemReference(item);
  if (!reference) return;
  activeReference.value = reference;
}

function handleRichReference(event: MouseEvent | KeyboardEvent) {
  const target = event.target instanceof HTMLElement ? event.target.closest<HTMLElement>('[data-reference-token="1"]') : null;
  if (!target) return;
  const reference = referenceFromDataset(target);
  if (!reference) return;
  event.preventDefault();
  event.stopPropagation();
  activeReference.value = reference;
}

function escapeHtml(value: string) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

watch(() => props.reference, (reference) => {
  if (props.open || !activeReference.value) {
    activeReference.value = reference;
  }
}, { immediate: true });

watch(() => props.open, (open) => {
  if (open) {
    activeReference.value = props.reference;
    return;
  }
  error.value = '';
}, { immediate: true });

watch(() => [props.open, activeReference.value?.raw, activeReference.value?.id, activeReference.value?.code, props.providerKey], () => { void loadDetail(); }, { immediate: true });
</script>

<style scoped>
.reference-detail-state { display: flex; min-height: 220px; gap: 10px; align-items: center; justify-content: center; color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground))); }
.reference-detail-panel { display: flex; flex-direction: column; gap: 18px; }
.reference-detail-hero { padding: 20px; border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); border-radius: 18px; background: linear-gradient(135deg, var(--ant-colorPrimaryBg, hsl(var(--primary) / 8%)), var(--ant-colorBgContainer, hsl(var(--card))) 58%); box-shadow: 0 12px 30px rgb(15 23 42 / 6%); }
.reference-detail-head { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
.reference-detail-id { color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground))); font-size: 12px; }
.reference-detail-title { margin: 0; color: var(--ant-colorText, hsl(var(--foreground))); font-weight: 700; font-size: 20px; line-height: 1.45; word-break: break-word; }
.reference-detail-subtitle { margin: 8px 0 0; color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground))); line-height: 1.6; }
.reference-detail-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.reference-detail-section { min-width: 0; }
.reference-detail-card { padding: 16px 18px; border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); border-radius: 16px; background: var(--ant-colorBgContainer, hsl(var(--card))); box-shadow: 0 8px 24px rgb(15 23 42 / 4%); }
.reference-detail-section-title { margin-bottom: 10px; color: var(--ant-colorText, hsl(var(--foreground))); font-weight: 600; font-size: 14px; }
.reference-detail-chain { display: flex; flex-wrap: wrap; gap: 10px; align-items: stretch; padding: 14px; border: 1px dashed var(--ant-colorBorder, hsl(var(--border))); border-radius: 14px; background: var(--ant-colorFillQuaternary, hsl(var(--muted) / 20%)); }
.reference-detail-chain-item { display: inline-flex; max-width: 260px; flex-direction: column; gap: 2px; padding: 9px 12px; border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); border-radius: 12px; appearance: none; background: var(--ant-colorBgElevated, hsl(var(--background))); box-shadow: 0 4px 14px rgb(15 23 42 / 4%); text-align: left; cursor: pointer; }
.reference-detail-chain-item:not(:disabled):hover { border-color: var(--ant-colorPrimary, hsl(var(--primary))); color: var(--ant-colorPrimary, hsl(var(--primary))); }
.reference-detail-chain-item:disabled { cursor: default; }
.reference-detail-chain-item.is-active { border-color: var(--ant-colorPrimary, hsl(var(--primary))); background: var(--ant-colorPrimaryBg, hsl(var(--primary) / 8%)); }
.reference-detail-chain-type { color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground))); font-size: 12px; }
.reference-detail-chain-label { overflow: hidden; color: var(--ant-colorText, hsl(var(--foreground))); font-weight: 600; line-height: 1.45; overflow-wrap: anywhere; }
.reference-detail-chain-arrow { display: inline-flex; align-items: center; color: var(--ant-colorPrimary, hsl(var(--primary))); font-weight: 700; }
.reference-detail-fields { margin-top: 0; }
.reference-detail-description { margin: 0; color: var(--ant-colorText, hsl(var(--foreground))); line-height: 1.8; white-space: pre-wrap; word-break: break-word; }
.reference-detail-rich-content { color: var(--ant-colorText, hsl(var(--foreground))); line-height: 1.75; overflow-wrap: anywhere; }
.reference-detail-rich-content :deep(.reference-token) { display: inline; max-width: 100%; padding: 0; margin: 0; border: 0; background: transparent; color: var(--ant-colorPrimary, hsl(var(--primary))); font: inherit; line-height: inherit; vertical-align: baseline; cursor: pointer; }
.reference-detail-inline-text :deep(.reference-token),
.reference-detail-description :deep(.reference-token),
.reference-detail-subtitle :deep(.reference-token),
.reference-detail-field-label :deep(.reference-token),
.reference-detail-field-label-text :deep(.reference-token),
.reference-detail-field-value :deep(.reference-token) { display: inline; max-width: 100%; padding: 0; margin: 0; border: 0; background: transparent; color: var(--ant-colorPrimary, hsl(var(--primary))); font: inherit; line-height: inherit; vertical-align: baseline; cursor: pointer; }
.reference-detail-rich-content :deep(.reference-token:hover),
.reference-detail-rich-content :deep(.reference-token:focus-visible),
.reference-detail-inline-text :deep(.reference-token:hover),
.reference-detail-inline-text :deep(.reference-token:focus-visible),
.reference-detail-description :deep(.reference-token:hover),
.reference-detail-description :deep(.reference-token:focus-visible),
.reference-detail-subtitle :deep(.reference-token:hover),
.reference-detail-subtitle :deep(.reference-token:focus-visible),
.reference-detail-field-label :deep(.reference-token:hover),
.reference-detail-field-label :deep(.reference-token:focus-visible),
.reference-detail-field-label-text :deep(.reference-token:hover),
.reference-detail-field-label-text :deep(.reference-token:focus-visible),
.reference-detail-field-value :deep(.reference-token:hover),
.reference-detail-field-value :deep(.reference-token:focus-visible) { color: var(--ant-colorPrimaryHover, var(--ant-colorPrimary, hsl(var(--primary)))); text-decoration: underline; text-underline-offset: 2px; }
.reference-detail-rich-content :deep(p) { margin: 0 0 0.75em; }
.reference-detail-rich-content :deep(p:last-child) { margin-bottom: 0; }
.reference-detail-rich-content :deep(img) { max-width: 100%; height: auto; border-radius: 10px; }
.reference-detail-rich-content :deep(a[data-project-file='1']) {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  max-width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 8px;
  background: var(--ant-colorFillQuaternary, hsl(var(--muted)));
  color: var(--ant-colorPrimary, hsl(var(--primary)));
  font-weight: 500;
  line-height: 1.4;
  text-decoration: none;
  overflow-wrap: anywhere;
  white-space: normal;
}
.reference-detail-rich-content :deep(a[data-project-file='1']::before) {
  flex: none;
  content: '📎';
}
.reference-detail-rich-content :deep(a[data-project-file='1']:hover),
.reference-detail-rich-content :deep(a[data-project-file='1']:focus-visible) {
  border-color: var(--ant-colorPrimaryBorder, var(--ant-colorPrimary, hsl(var(--primary))));
  background: var(--ant-colorPrimaryBg, hsl(var(--primary) / 0.12));
  text-decoration: underline;
  text-underline-offset: 2px;
}
.reference-detail-rich-content :deep(blockquote) { margin: 8px 0; padding: 8px 12px; border-left: 3px solid var(--ant-colorPrimary, hsl(var(--primary))); background: var(--ant-colorFillQuaternary, hsl(var(--muted) / 20%)); }
.reference-detail-rich-content :deep(table) { width: 100%; border-collapse: collapse; }
.reference-detail-rich-content :deep(td), .reference-detail-rich-content :deep(th) { padding: 8px; border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); }
.reference-detail-field-list { display: flex; flex-direction: column; overflow: hidden; border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); border-radius: 10px; }
.reference-detail-field-row { display: grid; grid-template-columns: minmax(120px, 28%) minmax(0, 1fr); border-bottom: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); }
.reference-detail-field-row:last-child { border-bottom: 0; }
.reference-detail-field-label { padding: 9px 10px; background: var(--ant-colorFillQuaternary, hsl(var(--muted) / 22%)); color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground))); font-size: 12px; }
.reference-detail-field-value { min-width: 0; padding: 9px 10px; color: var(--ant-colorText, hsl(var(--foreground))); line-height: 1.6; word-break: break-word; }
@media (max-width: 640px) {
  .reference-detail-field-row { grid-template-columns: 1fr; }
  .reference-detail-field-label { border-bottom: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border))); }
}
</style>
