export interface LogChangeValue {
  field: string;
  label: string;
  old?: unknown;
  new?: unknown;
  old_text?: string;
  new_text?: string;
  unit?: string;
}

export interface LogChangeRow {
  id: number;
  tenant_id?: number;
  action_id: number;
  username?: string;
  model?: string;
  table_name?: string;
  model_name?: string;
  record_id?: number | string;
  record_label?: string;
  event?: string;
  change_values?: LogChangeValue[] | string;
  change_remark?: string;
  created_by?: number;
  updated_by?: number;
  created_at?: string;
  updated_at?: string;
  deleted_at?: null | string;
}

type UnknownRecord = Record<string, unknown>;

export function asRecord(value: unknown): UnknownRecord {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as UnknownRecord) : {};
}

export function toFiniteNumber(value: unknown): number {
  const numberValue = Number(value);
  return Number.isFinite(numberValue) ? numberValue : 0;
}

export function toNumberRecord(value: unknown): Record<string, number> {
  const record = asRecord(value);
  return Object.fromEntries(
    Object.entries(record).map(([key, count]) => [key.trim() || '未记录', toFiniteNumber(count)]),
  );
}

export function fillNumberRange(value: unknown, start: number, end: number): Record<string, number> {
  const source = toNumberRecord(value);
  const result: Record<string, number> = {};
  for (let index = start; index <= end; index += 1) {
    result[String(index)] = source[String(index)] ?? 0;
  }
  return result;
}
