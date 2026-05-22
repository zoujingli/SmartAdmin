/**
 * 统一 API 导出 - 重构版本
 */

// 导出配置和工具
export * from './config';
export * from './utils';
export * from './base';

// 重新导出类型定义以避免歧义
export type { ApiResponse, PageResponse } from './types';

// 导出系统管理 API 服务
export * from './system/user';
export * from './system/role';
export * from './system/dept';
export * from './system/dict';
export * from './system/menu';
export * from './system/logs-shared';
export * from './system/logs-action';
export * from './system/logs-change';
export * from './system/post';
export * from './system/data';
export * from './system/file';
export * from './system/notice';
export * from './system/setting';
export * from './system/tenant';

// 导出核心 API（保持原有结构）
export * from './core';

// 导出类型定义
export * from './types';
