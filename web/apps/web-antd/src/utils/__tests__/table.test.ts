import { describe, expect, it } from 'vitest';

import {
  buildTableScrollX,
  estimateActionColumnWidth,
  estimateVisibleActionColumnWidth,
} from '../table';

describe('table width helpers', () => {
  it('builds scroll.x from explicit, nested, and fallback column widths', () => {
    expect(buildTableScrollX([
      { width: 80 },
      { minWidth: '120px' },
      {
        children: [
          { width: 90 },
          { width: 'calc(100% - 20px)' },
        ],
      },
    ], {
      defaultColumnWidth: 160,
      extraWidth: 20,
      minWidth: 0,
      selectionWidth: 40,
    })).toEqual({ x: 510 });
  });

  it('keeps scroll.x above the configured minimum width', () => {
    expect(buildTableScrollX([{ width: 80 }], { minWidth: 960 })).toEqual({ x: 960 });
  });

  it('estimates collapsed visible action width with the more label', () => {
    expect(estimateVisibleActionColumnWidth([
      { label: '查看' },
      { label: '编辑' },
      { label: '同步' },
      { label: '删除' },
    ], {
      charWidth: 10,
      gapWidth: 5,
      horizontalPadding: 10,
      inlineBeforeMore: 2,
      maxInline: 3,
      maxWidth: 999,
      minWidth: 0,
      safetyWidth: 0,
    })).toBe(100);
  });

  it('ignores hidden action objects when estimating visible width', () => {
    expect(estimateVisibleActionColumnWidth([
      { label: '查看' },
      { label: '删除', visible: false },
    ], {
      charWidth: 10,
      horizontalPadding: 10,
      maxWidth: 999,
      minWidth: 0,
      safetyWidth: 0,
    })).toBe(30);
  });

  it('estimates explicit inline actions plus the more menu', () => {
    expect(estimateVisibleActionColumnWidth([
      { inline: true, label: '查看' },
      { inline: true, label: '编辑' },
      { label: '验收标准' },
      { label: '取消', visible: false },
    ], {
      charWidth: 10,
      explicitInline: true,
      gapWidth: 5,
      horizontalPadding: 10,
      maxWidth: 999,
      minWidth: 0,
      safetyWidth: 0,
    })).toBe(100);
  });

  it('respects min and max width bounds for raw action labels', () => {
    expect(estimateActionColumnWidth(['查看', '编辑'], {
      charWidth: 10,
      maxWidth: 70,
      minWidth: 60,
      safetyWidth: 0,
    })).toBe(70);
  });
});
