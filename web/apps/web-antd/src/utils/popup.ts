export type PopupWidthSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'xxl' | 'wide' | 'full';

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

/**
 * 统一页面 Modal / Drawer 宽度，保留桌面端业务内容宽度，同时在小屏按视口自动收缩。
 *
 * 所有页面弹层优先使用这里的标准档位，避免散落 `720px`、`calc(100vw)` 等写法后
 * 在后续主题、移动端和插件页面维护时出现宽度口径不一致。
 */
export function buildPopupWidth(size: PopupWidthSize) {
  return `min(${POPUP_WIDTH_PX[size]}px, calc(100vw - ${POPUP_VIEWPORT_GAP}px))`;
}

export const popupWidth: Record<PopupWidthSize, string> = {
  xs: buildPopupWidth('xs'),
  sm: buildPopupWidth('sm'),
  md: buildPopupWidth('md'),
  lg: buildPopupWidth('lg'),
  xl: buildPopupWidth('xl'),
  xxl: buildPopupWidth('xxl'),
  wide: buildPopupWidth('wide'),
  full: buildPopupWidth('full'),
};
