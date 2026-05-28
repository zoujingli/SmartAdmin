<template>
  <Drawer
    v-bind="forwardedAttrs"
    :body-style="mergedBodyStyle"
    :class="panelClassName"
    :closable="closable"
    :destroy-on-close="destroyOnClose"
    :height="height"
    :keyboard="canClose"
    :mask-closable="canClose && maskClosable"
    :open="open"
    :placement="placement"
    :root-class-name="drawerRootClassName"
    :title="title"
    :width="drawerWidth"
    @close="handleClose"
  >
    <template v-if="$slots.extra" #extra>
      <slot name="extra" />
    </template>

    <Spin :spinning="loading">
      <div class="app-drawer__content" :class="bodyClass">
        <slot />
      </div>
    </Spin>

    <template v-if="showFooter" #footer>
      <div class="app-drawer__footer">
        <slot v-if="$slots.footer" name="footer" />
        <template v-else>
          <div class="app-drawer__footer-left">
            <slot name="footer-left" />
          </div>
          <div class="app-drawer__footer-actions">
            <Button :disabled="confirmLoading" @click="handleCancel">{{ cancelText }}</Button>
            <slot name="footer-extra" />
            <Button
              v-if="okVisible"
              :danger="okDanger"
              :disabled="okDisabled"
              :loading="confirmLoading"
              type="primary"
              @click="handleOk"
            >
              {{ okText }}
            </Button>
          </div>
        </template>
      </div>
    </template>
  </Drawer>
</template>

<script setup lang="ts">
import type { CSSProperties } from 'vue';
import type { PopupWidthSize } from './types';

import { computed, useAttrs } from 'vue';

import { Button, Drawer, Spin } from 'ant-design-vue';

const POPUP_VIEWPORT_GAP = 32;
const POPUP_WIDTH_PX: Record<PopupWidthSize, number> = {
  xs: 560,
  sm: 720,
  md: 760,
  lg: 860,
  xl: 960,
  xxl: 1080,
  wide: 1180,
  full: 1560,
};

function buildPopupWidth(size: PopupWidthSize) {
  return `min(${POPUP_WIDTH_PX[size]}px, calc(100vw - ${POPUP_VIEWPORT_GAP}px))`;
}

const popupWidth: Record<PopupWidthSize, string> = {
  xs: buildPopupWidth('xs'),
  sm: buildPopupWidth('sm'),
  md: buildPopupWidth('md'),
  lg: buildPopupWidth('lg'),
  xl: buildPopupWidth('xl'),
  xxl: buildPopupWidth('xxl'),
  wide: buildPopupWidth('wide'),
  full: buildPopupWidth('full'),
};

defineOptions({
  inheritAttrs: false,
  name: 'AppDrawer',
});

const props = withDefaults(defineProps<{
  /** 右侧弹层正文额外类名，用于保留复杂详情页或富文本页的滚动布局。 */
  bodyClass?: Record<string, boolean> | string | string[];
  /** 少量特殊页面可追加滚动、背景或内边距；常规页面不再散落声明 padding。 */
  bodyStyle?: CSSProperties;
  cancelText?: string;
  closable?: boolean;
  confirmLoading?: boolean;
  destroyOnClose?: boolean;
  height?: number | string;
  loading?: boolean;
  maskClosable?: boolean;
  okDanger?: boolean;
  okDisabled?: boolean;
  okText?: string;
  okVisible?: boolean;
  open?: boolean;
  placement?: 'bottom' | 'left' | 'right' | 'top';
  showFooter?: boolean;
  title?: string;
  width?: number | string;
  widthSize?: PopupWidthSize;
}>(), {
  bodyClass: undefined,
  bodyStyle: undefined,
  cancelText: '取消',
  closable: true,
  confirmLoading: false,
  destroyOnClose: false,
  height: undefined,
  loading: false,
  maskClosable: true,
  okDanger: false,
  okDisabled: false,
  okText: '保存',
  okVisible: true,
  open: false,
  placement: 'right',
  showFooter: true,
  title: '',
  width: undefined,
  widthSize: 'md',
});

const emit = defineEmits<{
  cancel: [];
  close: [];
  ok: [];
  'update:open': [value: boolean];
}>();

const attrs = useAttrs();
const canClose = computed(() => !props.confirmLoading);
const drawerWidth = computed(() => props.width || popupWidth[props.widthSize]);
const panelClassName = computed(() => attrs.class as any);
const drawerRootClassName = computed(() => ['app-drawer', attrs.rootClassName].filter(Boolean).join(' '));
const forwardedAttrs = computed(() => {
  const { class: _class, rootClassName: _rootClassName, style: _style, ...rest } = attrs;

  return rest;
});
const mergedBodyStyle = computed<CSSProperties>(() => ({
  padding: props.showFooter ? '20px 24px 8px' : '20px 24px',
  ...props.bodyStyle,
}));

function closeDrawer() {
  if (!canClose.value) return;
  emit('update:open', false);
  emit('close');
}

function handleClose() {
  closeDrawer();
}

function handleCancel() {
  if (!canClose.value) return;
  emit('cancel');
  closeDrawer();
}

function handleOk() {
  if (props.okDisabled || props.confirmLoading) return;
  emit('ok');
}
</script>

<style>
.app-drawer .ant-drawer-header {
  padding: 16px 24px;
  background: var(--ant-colorBgElevated, var(--ant-colorBgContainer));
  border-bottom: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
}

.app-drawer .ant-drawer-title {
  color: var(--ant-colorText, hsl(var(--foreground)));
  font-size: 16px;
  font-weight: 600;
}

.app-drawer .ant-drawer-body {
  background: var(--ant-colorBgContainer, hsl(var(--background)));
}

.app-drawer .ant-drawer-footer {
  padding: 12px 24px;
  background: var(--ant-colorBgElevated, var(--ant-colorBgContainer));
  border-top: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
}

@media (max-width: 640px) {
  .app-drawer .ant-drawer-header,
  .app-drawer .ant-drawer-footer {
    padding-right: 16px;
    padding-left: 16px;
  }

  .app-drawer .ant-drawer-body {
    padding-right: 16px !important;
    padding-left: 16px !important;
  }
}
</style>

<style scoped>
.app-drawer__content {
  min-height: 100%;
}

.app-drawer__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 32px;
}

.app-drawer__footer-left,
.app-drawer__footer-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.app-drawer__footer-left {
  min-width: 0;
}

.app-drawer__footer-actions {
  margin-left: auto;
  justify-content: flex-end;
}

@media (max-width: 640px) {
  .app-drawer__footer {
    align-items: stretch;
    flex-direction: column;
  }

  .app-drawer__footer-actions {
    width: 100%;
  }
}
</style>
