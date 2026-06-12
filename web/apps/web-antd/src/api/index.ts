/**
 * 统一 API 导出 - 重构版本
 */

// 导出配置和工具
export * from './config';
export * from './utils';
export * from './base';

// 重新导出类型定义以避免歧义
export type { ApiResponse, PageResponse } from './types';

// 导出核心 API（保持原有结构）
export * from './core';

// 导出类型定义
export * from './types';
