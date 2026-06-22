import { describe, expect, it } from 'vitest';

import { hideInMenuColor, hideInMenuText, normalizeHideInMenu } from './visibility';

describe('menu visibility helpers', () => {
  it('normalizes hidden menu flags from form and import values', () => {
    expect(normalizeHideInMenu(1)).toBe(1);
    expect(normalizeHideInMenu(true)).toBe(1);
    expect(normalizeHideInMenu('隐藏')).toBe(1);
    expect(normalizeHideInMenu('on')).toBe(1);
    expect(normalizeHideInMenu(0)).toBe(0);
    expect(normalizeHideInMenu(false)).toBe(0);
    expect(normalizeHideInMenu('显示')).toBe(0);
    expect(normalizeHideInMenu('')).toBe(0);
  });

  it('formats visibility text and tag color', () => {
    expect(hideInMenuText(1)).toBe('隐藏');
    expect(hideInMenuText(0)).toBe('显示');
    expect(hideInMenuColor(1)).toBe('default');
    expect(hideInMenuColor(0)).toBe('success');
  });
});
