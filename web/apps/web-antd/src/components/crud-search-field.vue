<template>
  <div
    ref="fieldRef"
    class="crud-search-field"
    @click.capture="handleDropdownTrigger"
    @focusin.capture="handleDropdownTrigger"
    @keydown.capture="handleDropdownTrigger"
    @pointerdown.capture="handleDropdownTrigger"
  >
    <span class="crud-search-field__label">{{ label }}</span>
    <div class="crud-search-field__control">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

defineProps<{
  /** 搜索区标签固定使用 4 个汉字，保证列表筛选条件横向对齐。 */
  label: string;
}>();

const fieldRef = ref<HTMLElement>();
const pendingTimers: number[] = [];

function clearPendingTimers() {
  while (pendingTimers.length > 0) {
    window.clearTimeout(pendingTimers.pop()!);
  }
}

function handleDropdownTrigger(event: Event) {
  const target = event.target;
  if (!(target instanceof Element) || !fieldRef.value?.contains(target)) return;
  const trigger = target.closest<HTMLElement>('.ant-select, .ant-tree-select, .ant-cascader-picker, .ant-cascader');
  if (!trigger) return;

  // 下拉弹层挂载在 body 上，不能直接继承搜索框宽度；这里按触发控件宽度做下限，
  // 再按真实选项文本估算目标宽度，上限控制在 2 倍输入宽度内，避免短列表抖动或长名称溢出。
  const triggerWidth = Math.round(trigger.getBoundingClientRect().width || fieldRef.value.getBoundingClientRect().width);
  scheduleDropdownWidth(triggerWidth);
}

function scheduleDropdownWidth(triggerWidth: number) {
  clearPendingTimers();
  queueDropdownWidthApply(triggerWidth);
  [0, 32, 96, 240, 500].forEach((delay) => {
    pendingTimers.push(window.setTimeout(() => queueDropdownWidthApply(triggerWidth), delay));
  });
}

function queueDropdownWidthApply(triggerWidth: number) {
  window.requestAnimationFrame(() => applyDropdownWidth(triggerWidth));
}

function applyDropdownWidth(triggerWidth: number) {
  if (triggerWidth <= 0) return;
  const minWidth = Math.max(160, Math.round(triggerWidth));
  const maxWidth = Math.max(minWidth, Math.min(minWidth * 2, window.innerWidth - 24));
  document
    .querySelectorAll<HTMLElement>('.ant-select-dropdown, .ant-tree-select-dropdown, .ant-cascader-dropdown')
    .forEach((dropdown) => {
      const style = window.getComputedStyle(dropdown);
      if (style.display === 'none' || style.visibility === 'hidden' || dropdown.className.includes('hidden')) return;
      const contentWidth = estimateDropdownContentWidth(dropdown);
      const targetWidth = Math.min(maxWidth, Math.max(minWidth, contentWidth));
      markSearchDropdown(dropdown, targetWidth, minWidth, maxWidth);
      applyDropdownOptionTitles(dropdown);
    });
}

function markSearchDropdown(dropdown: HTMLElement, width: number, minWidth: number, maxWidth: number) {
  // rc-trigger 会在对齐时重写 inline width；改用 class + CSS 变量接管，确保最小宽度、最大宽度和目标宽度一致生效。
  dropdown.classList.add('crud-search-dropdown');
  setDropdownStyle(dropdown, '--crud-search-dropdown-width', `${width}px`);
  setDropdownStyle(dropdown, '--crud-search-dropdown-min-width', `${minWidth}px`);
  setDropdownStyle(dropdown, '--crud-search-dropdown-max-width', `${maxWidth}px`);
}

function setDropdownStyle(dropdown: HTMLElement, property: string, value: string) {
  if (dropdown.style.getPropertyValue(property) === value) return;
  dropdown.style.setProperty(property, value);
}

function applyDropdownOptionTitles(dropdown: HTMLElement) {
  dropdown
    .querySelectorAll<HTMLElement>('.ant-select-item-option-content, .ant-select-tree-title, .ant-cascader-menu-item-content')
    .forEach((node) => {
      const text = (node.textContent || '').replace(/\s+/g, ' ').trim();
      if (!text) return;
      node.setAttribute('title', text);
      node.closest<HTMLElement>('.ant-select-item-option, .ant-select-tree-treenode, .ant-cascader-menu-item')?.setAttribute('title', text);
    });
}

function estimateDropdownContentWidth(dropdown: HTMLElement) {
  const contentNodes = dropdown.querySelectorAll<HTMLElement>(
    '.ant-select-item-option-content, .ant-select-item-empty, .ant-select-tree-title, .ant-cascader-menu-item-content',
  );
  const maxContentWidth = Array.from(contentNodes).reduce((width, node) => {
    // Ant 选项节点可能被当前弹层宽度撑开；只有真实溢出才采信 scrollWidth，其余按文本测量，避免误判为无限加宽。
    const overflowWidth = node.scrollWidth > node.clientWidth + 2 ? node.scrollWidth : 0;
    const contentWidth = Math.max(overflowWidth, measureDropdownTextWidth(node));

    return Math.max(width, contentWidth);
  }, 0);

  // 左右内边距、选中图标和级联箭头会额外占位，预留 40px 让长名称不贴边。
  return maxContentWidth > 0 ? maxContentWidth + 40 : 0;
}

function measureDropdownTextWidth(node: HTMLElement) {
  const text = (node.textContent || '').replace(/\s+/g, ' ').trim();
  if (!text) return 0;
  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d');
  if (!context) return 0;
  const style = window.getComputedStyle(node);
  context.font = style.font || `${style.fontSize} ${style.fontFamily}`;

  return Math.ceil(context.measureText(text).width);
}

onBeforeUnmount(clearPendingTimers);
</script>

<style scoped>
.crud-search-field {
  display: flex;
  align-items: center;
  width: 100%;
  height: 32px;
  min-height: 32px;
  overflow: hidden;
  color: var(--ant-colorText, hsl(var(--foreground)));
  background: var(--ant-colorBgContainer, hsl(var(--background)));
  border: 1px solid var(--ant-colorBorder, hsl(var(--border)));
  border-radius: var(--ant-borderRadius, 6px);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.crud-search-field:focus-within {
  border-color: var(--ant-colorPrimary, hsl(var(--primary)));
  box-shadow: 0 0 0 2px var(--ant-colorPrimaryBg, hsl(var(--primary) / 12%));
}

.crud-search-field__label {
  display: inline-flex;
  flex: 0 0 5.25em;
  align-items: center;
  align-self: stretch;
  box-sizing: border-box;
  padding: 0 10px;
  color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground)));
  font-size: 13px;
  font-weight: 500;
  line-height: 1;
  white-space: nowrap;
  background: var(--ant-colorFillQuaternary, hsl(var(--muted) / 38%));
  border-right: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
}

.crud-search-field__control {
  display: flex;
  flex: 1 1 auto;
  align-items: center;
  height: 30px;
  min-width: 0;
}

.crud-search-field__control :deep(.ant-cascader-picker),
.crud-search-field__control :deep(.ant-input),
.crud-search-field__control :deep(.ant-input-affix-wrapper),
.crud-search-field__control :deep(.ant-input-number),
.crud-search-field__control :deep(.ant-mentions),
.crud-search-field__control :deep(.ant-picker),
.crud-search-field__control :deep(.ant-select-selector),
.crud-search-field__control :deep(.ant-tree-select-selector) {
  background: transparent !important;
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
}

.crud-search-field__control :deep(.ant-cascader-picker),
.crud-search-field__control :deep(.ant-input),
.crud-search-field__control :deep(.ant-input-affix-wrapper),
.crud-search-field__control :deep(.ant-input-number),
.crud-search-field__control :deep(.ant-mentions),
.crud-search-field__control :deep(.ant-picker) {
  width: 100%;
  height: 30px;
}

.crud-search-field__control :deep(.ant-cascader-picker),
.crud-search-field__control :deep(.ant-input),
.crud-search-field__control :deep(.ant-input-number-input),
.crud-search-field__control :deep(.ant-mentions textarea) {
  padding: 0 10px !important;
  line-height: 30px !important;
}

.crud-search-field__control :deep(.ant-input-affix-wrapper),
.crud-search-field__control :deep(.ant-mentions),
.crud-search-field__control :deep(.ant-picker) {
  align-items: center;
  padding: 0 10px !important;
}

.crud-search-field__control :deep(.ant-input-affix-wrapper .ant-input) {
  height: 30px;
  padding: 0 !important;
  line-height: 30px !important;
}

.crud-search-field__control :deep(.ant-input-number-input-wrap) {
  height: 30px;
}

.crud-search-field__control :deep(.ant-picker-range) {
  min-width: 0;
}

.crud-search-field__control :deep(.ant-picker-range .ant-picker-input) {
  min-width: 0;
}

.crud-search-field__control :deep(.ant-picker-input > input) {
  height: 30px;
  padding: 0 !important;
  line-height: 30px !important;
}

.crud-search-field__control :deep(.ant-picker-range .ant-picker-range-separator) {
  padding: 0 4px;
  line-height: 30px;
}

.crud-search-field__control :deep(.ant-cascader-picker),
.crud-search-field__control :deep(.ant-input-affix-wrapper),
.crud-search-field__control :deep(.ant-input-number),
.crud-search-field__control :deep(.ant-mentions),
.crud-search-field__control :deep(.ant-picker),
.crud-search-field__control :deep(.ant-select),
.crud-search-field__control :deep(.ant-tree-select) {
  width: 100%;
  height: 30px;
}

.crud-search-field__control :deep(.ant-select-selector),
.crud-search-field__control :deep(.ant-tree-select-selector) {
  display: flex;
  align-items: center;
  height: 30px !important;
  min-height: 30px !important;
  padding: 0 10px !important;
}

.crud-search-field__control :deep(.ant-select-selection-search),
.crud-search-field__control :deep(.ant-tree-select-selection-search) {
  inset-inline-start: 10px !important;
  inset-inline-end: 10px !important;
}

.crud-search-field__control :deep(.ant-select-selection-search-input),
.crud-search-field__control :deep(.ant-tree-select-selection-search-input) {
  height: 30px !important;
  line-height: 30px !important;
}

.crud-search-field__control :deep(.ant-select-selection-item),
.crud-search-field__control :deep(.ant-select-selection-placeholder),
.crud-search-field__control :deep(.ant-tree-select-selection-item),
.crud-search-field__control :deep(.ant-tree-select-selection-placeholder) {
  display: inline-flex;
  align-items: center;
  line-height: 30px !important;
}

:global(.crud-search-grid.ant-row) {
  display: flex;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px 14px;
  margin-left: 0 !important;
  margin-right: 0 !important;
  margin-inline: 0 !important;
  row-gap: 12px !important;
}

:global(.crud-search-grid.ant-row > .ant-col) {
  box-sizing: border-box;
  flex: 0 0 var(--crud-search-item-width, 260px) !important;
  max-width: var(--crud-search-item-width, 260px) !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
  padding-inline: 0 !important;
}

:global(.crud-search-grid.ant-row > .crud-search-grid__actions) {
  display: inline-flex !important;
  flex: 0 0 auto !important;
  align-items: center;
  align-self: flex-start;
  justify-content: flex-start;
  height: 32px;
  min-height: 32px;
  width: auto !important;
  max-width: none !important;
}

:global(.crud-search-grid__actions .ant-space) {
  display: inline-flex !important;
  align-items: center;
  width: auto !important;
  margin-bottom: 0 !important;
}

:global(.crud-search-grid__actions .ant-space-item) {
  flex: 0 0 auto !important;
  line-height: 1;
  margin-bottom: 0 !important;
}

:global(.crud-search-grid__actions .ant-btn) {
  width: auto !important;
  height: 32px;
  min-width: 88px;
}

:global(.crud-search-grid__actions .ant-btn-block) {
  width: auto;
}

/* 下拉提示最小等于输入控件宽度，按内容自适应展开，最大不超过 2 倍输入宽度。 */
:global(.ant-select-dropdown),
:global(.ant-tree-select-dropdown),
:global(.ant-cascader-dropdown) {
  box-sizing: border-box;
}

:global(.ant-select-dropdown.crud-search-dropdown),
:global(.ant-tree-select-dropdown.crud-search-dropdown),
:global(.ant-cascader-dropdown.crud-search-dropdown),
:global(.ant-select-dropdown[style*='--crud-search-dropdown-width']),
:global(.ant-tree-select-dropdown[style*='--crud-search-dropdown-width']),
:global(.ant-cascader-dropdown[style*='--crud-search-dropdown-width']) {
  width: var(--crud-search-dropdown-width) !important;
  min-width: var(--crud-search-dropdown-min-width) !important;
  max-width: var(--crud-search-dropdown-max-width) !important;
}

:global(.ant-select-dropdown .ant-select-item-option-content),
:global(.ant-tree-select-dropdown .ant-select-tree-title),
:global(.ant-cascader-dropdown .ant-cascader-menu-item-content) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 767.98px) {
  :global(.crud-search-grid.ant-row > .ant-col) {
    flex: 1 1 100% !important;
    max-width: 100% !important;
  }

  :global(.crud-search-grid.ant-row > .crud-search-grid__actions) {
    width: auto !important;
  }
}
</style>
