export const ROLE_SCOPE_DEFAULT = 4;

export const ROLE_SCOPE_OPTIONS: Array<{ label: string; value: number }> = [
  { label: '全部数据', value: 1 },
  { label: '本部门数据', value: 2 },
  { label: '本部门及以下', value: 3 },
  { label: '本人数据', value: 4 },
];

export function roleScopeText(scope?: number) {
  return ROLE_SCOPE_OPTIONS.find((item) => item.value === Number(scope))?.label ?? '未知';
}

export function parseRoleScope(value: unknown, defaultValue = ROLE_SCOPE_DEFAULT) {
  const text = String(value ?? '').trim();
  const matchedOption = ROLE_SCOPE_OPTIONS.find(
    (item) => item.label === text || String(item.value) === text,
  );

  return matchedOption?.value ?? defaultValue;
}

export function roleScopeColor(scope?: number) {
  const map: Record<number, string> = {
    1: 'blue',
    2: 'cyan',
    3: 'orange',
    4: 'green',
  };

  return map[Number(scope)] || 'default';
}
