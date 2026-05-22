/**
 * 统一的 API 响应格式
 */
export interface ApiResponse<T = any> {
  code: number;
  info: string;
  data: T;
  path: string;
}

/**
 * 分页响应数据格式
 */
export interface PageResponse<T = any> {
  items: T[];
  pageInfo: {
    total: number;
    totalPage: number;
    currentPage: number;
  };
  extra?: ExtraData; // 扩展数据字段，类型为any
}

/**
 * 统计响应数据格式
 */
export interface StatisticsResponse {
  total: number;
  today_created: number;
  this_month_created: number;
}

/**
 * 扩展数据字段类型定义
 */
export type ExtraData = any;
