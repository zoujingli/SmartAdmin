export function normalizeHideInMenu(value: unknown): 0 | 1 {
  const normalized = String(value ?? '').trim().toLowerCase();
  if (['1', 'true', 'yes', 'on', '隐藏'].includes(normalized)) {
    return 1;
  }

  return 0;
}

export function hideInMenuText(value: unknown): string {
  return normalizeHideInMenu(value) === 1 ? '隐藏' : '显示';
}

export function hideInMenuColor(value: unknown): 'default' | 'success' {
  return normalizeHideInMenu(value) === 1 ? 'default' : 'success';
}
