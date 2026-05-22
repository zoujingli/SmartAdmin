/**
 * 数据字典相关类型定义。
 */

export interface DictInfo {
  id: number;
  pid: number;
  code: string;
  name: string;
  value: string;
  extra: Record<string, any>;
  sort: number;
  status: number;
  remark: string;
  created_by: number;
  updated_by: number;
  created_at: string;
  updated_at: string;
  deleted_at?: null | string;
  children?: DictInfo[];
}

export interface DictFormData {
  id?: number;
  pid: number;
  code: string;
  name: string;
  value: string;
  extra: string;
  sort: number;
  status: number;
  remark: string;
}
