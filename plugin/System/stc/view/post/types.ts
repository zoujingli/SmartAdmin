/**
 * 岗位管理相关类型定义
 */

export interface PostType {
  id: number;
  code: string;
  name: string;
  sort: number;
  status: number;
  remark: string;
  created_by: number;
  updated_by: number;
  created_at: string;
  updated_at: string;
}

export interface PostFormData {
  id?: number;
  code: string;
  name: string;
  sort?: number;
  status?: number;
  remark?: string;
}

export interface PostListParams {
  page?: number;
  pageSize?: number;
  keyword?: string;
  status?: number;
  created_at?: [string, string];
}

export interface PostOption {
  id: number;
  name: string;
}
