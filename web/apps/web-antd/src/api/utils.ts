/**
 * API 工具函数。
 */
import { REQUEST_ERROR_HANDLED_FLAG } from '@vben/request';
import { message } from 'ant-design-vue';
import { API_CODES, ERROR_MESSAGES, LOG_CONFIG } from './config';
/**
 * 检查 API 响应是否成功。
 */
export function isApiSuccess(response: any): boolean {
  return response && response.code === API_CODES.SUCCESS;
}

/**
 * 检查是否为数组响应。
 */
export function isArrayResponse(response: any): boolean {
  return Array.isArray(response);
}

/**
 * 检查是否为分页响应。
 */
export function isPageResponse(response: any): boolean {
  return response && typeof response === 'object' && 'items' in response && 'pageInfo' in response;
}

/**
 * 安全获取响应数据。
 */
export function getResponseData<T = any>(response: any): T | null {
  if (isApiSuccess(response)) {
    return response.data;
  }
  return null;
}

/**
 * 安全获取分页数据。
 */
export function getPageData<T = any>(response: any): { items: T[]; total: number; pageInfo?: any; extra?: any } {
  if (isPageResponse(response)) {
    return {
      items: response.items || [],
      total: response.pageInfo?.total || 0,
      pageInfo: response.pageInfo,
      extra: response.extra,
    };
  }
  return { items: [], total: 0 };
}

/**
 * 安全获取数组数据。
 */
export function getArrayData<T = any>(response: any): T[] {
  if (isArrayResponse(response)) {
    return response;
  }
  return [];
}

/**
 * 统一错误处理。
 */
export function handleApiError(error: any, customMessage?: string): void {
  if (LOG_CONFIG.ENABLE_ERROR_LOG) {
    console.error('API Error:', error);
  }

  if (error?.[REQUEST_ERROR_HANDLED_FLAG] && !customMessage) {
    return;
  }

  let errorMessage = customMessage || ERROR_MESSAGES.UNKNOWN_ERROR;

  if (error?.response) {
    const responseData = error.response.data || {};
    const status = Number(responseData.code ?? error.response.status);

    // 优先使用后端返回的错误信息
    errorMessage = responseData.info || responseData.message || responseData.error || errorMessage;

    if (!errorMessage) {
      // 根据状态码设置默认错误信息
      switch (status) {
        case API_CODES.UNAUTHORIZED:
          errorMessage = ERROR_MESSAGES[API_CODES.UNAUTHORIZED];
          break;
        case API_CODES.FORBIDDEN:
          errorMessage = ERROR_MESSAGES[API_CODES.FORBIDDEN];
          break;
        case API_CODES.NOT_FOUND:
          errorMessage = ERROR_MESSAGES[API_CODES.NOT_FOUND];
          break;
        case API_CODES.INTERNAL_SERVER_ERROR:
          errorMessage = ERROR_MESSAGES[API_CODES.INTERNAL_SERVER_ERROR];
          break;
      }
    }
  } else if (error?.code === 'ECONNABORTED') {
    errorMessage = ERROR_MESSAGES.TIMEOUT_ERROR;
  } else if (!navigator.onLine) {
    errorMessage = ERROR_MESSAGES.NETWORK_ERROR;
  }

  message.error(errorMessage);
}

/**
 * 创建分页参数。
 */
export function createPageParams(page: number = 1, pageSize: number = 10, extra: Record<string, any> = {}) {
  return {
    page: Math.max(1, page),
    pageSize: Math.min(Math.max(1, pageSize), 100),
    ...extra,
  };
}

/**
 * 创建搜索参数。
 */
export function createSearchParams(searchForm: Record<string, any> = {}) {
  const params: Record<string, any> = {};

  Object.keys(searchForm).forEach((key) => {
    const value = searchForm[key];
    if (value !== undefined && value !== null && value !== '') {
      params[key] = value;
    }
  });

  return params;
}

/**
 * 格式化日期参数。
 */
export function formatDateParams(startDate?: string, endDate?: string) {
  const params: Record<string, any> = {};

  if (startDate) {
    params.startDate = startDate;
  }

  if (endDate) {
    params.endDate = endDate;
  }

  return params;
}

/**
 * 日志记录。
 */
export function logApiRequest(url: string, params?: any) {
  if (LOG_CONFIG.ENABLE_REQUEST_LOG) {
    console.log(`[API Request] ${url}`, params);
  }
}

export function logApiResponse(url: string, response: any) {
  if (LOG_CONFIG.ENABLE_RESPONSE_LOG) {
    console.log(`[API Response] ${url}`, response);
  }
}

export function logApiError(url: string, error: any) {
  if (LOG_CONFIG.ENABLE_ERROR_LOG) {
    console.error(`[API Error] ${url}`, error);
  }
}

/**
 * 检查响应是否成功。
 */
export function isSuccessResponse(response: any): boolean {
  return response?.code === API_CODES.SUCCESS;
}

/**
 * 检查是否满足重试条件。
 */
export function shouldRetry(error: any): boolean {
  const status = Number(error?.response?.data?.code ?? error?.response?.status);
  return Number.isFinite(status) && [408, 429, 500, 502, 503, 504].includes(status);
}

/**
 * 格式化 API 响应。
 */
export function formatApiResponse<T = any>(response: any): T {
  if (isSuccessResponse(response)) {
    return response.data;
  }
  throw new Error(response?.info || response?.message || ERROR_MESSAGES.UNKNOWN_ERROR);
}

/**
 * 创建请求配置。
 */
export function createRequestConfig(
  options: {
    timeout?: number;
    retry?: boolean;
    headers?: Record<string, string>;
  } = {},
) {
  return {
    timeout: options.timeout || 30000,
    retry: options.retry ?? true,
    retryDelay: 1000,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...options.headers,
    },
  };
}

/**
 * 验证 API 响应格式。
 */
export function validateApiResponse(response: any): boolean {
  return response && typeof response === 'object' && typeof response.code === 'number' && 'data' in response;
}

/**
 * 获取错误状态码。
 */
export function getErrorStatusCode(error: any): number | null {
  const status = Number(error?.response?.data?.code ?? error?.response?.status);
  return Number.isFinite(status) ? status : null;
}

/**
 * 检查是否为网络错误。
 */
export function isNetworkError(error: any): boolean {
  return !error?.response && (error?.code === 'NETWORK_ERROR' || error?.code === 'ECONNABORTED' || !navigator.onLine);
}
