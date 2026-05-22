/**
 * API 服务基类
 */
import { requestClient } from './request';
import type { PageResponse } from './types';
import {
  getPageData,
  getArrayData,
  handleApiError,
  createPageParams,
  createSearchParams,
  logApiRequest,
  logApiResponse,
  logApiError,
  shouldRetry,
  formatApiResponse,
  createRequestConfig,
  validateApiResponse,
  getErrorStatusCode,
  isNetworkError,
} from './utils';

/**
 * API 服务基类
 */
export class BaseApiService {
  protected baseURL: string;

  constructor(baseURL: string = '') {
    this.baseURL = baseURL;
  }

  /**
   * GET 请求
   */
  protected async get<T = any>(url: string, params?: any): Promise<T> {
    const fullUrl = this.baseURL + url;
    logApiRequest(fullUrl, params);
    
    try {
      const response = await requestClient.get<T>(fullUrl, { params });
      logApiResponse(fullUrl, response);
      return response;
    } catch (error) {
      logApiError(fullUrl, error);
      handleApiError(error);
      throw error;
    }
  }

  /**
   * POST 请求
   */
  protected async post<T = any>(url: string, data?: any): Promise<T> {
    const fullUrl = this.baseURL + url;
    logApiRequest(fullUrl, data);
    
    try {
      const response = await requestClient.post<T>(fullUrl, data);
      logApiResponse(fullUrl, response);
      return response;
    } catch (error) {
      logApiError(fullUrl, error);
      handleApiError(error);
      throw error;
    }
  }

  /**
   * PUT 请求
   */
  protected async put<T = any>(url: string, data?: any): Promise<T> {
    const fullUrl = this.baseURL + url;
    logApiRequest(fullUrl, data);
    
    try {
      const response = await requestClient.put<T>(fullUrl, data);
      logApiResponse(fullUrl, response);
      return response;
    } catch (error) {
      logApiError(fullUrl, error);
      handleApiError(error);
      throw error;
    }
  }

  /**
   * DELETE 请求
   */
  protected async delete<T = any>(url: string, params?: any): Promise<T> {
    const fullUrl = this.baseURL + url;
    logApiRequest(fullUrl, params);
    
    try {
      const response = await requestClient.delete<T>(fullUrl, { params });
      logApiResponse(fullUrl, response);
      return response;
    } catch (error) {
      logApiError(fullUrl, error);
      handleApiError(error);
      throw error;
    }
  }

  /**
   * 获取列表数据（分页）
   */
  protected async getList<T = any>(
    url: string,
    page: number = 1,
    pageSize: number = 10,
    searchParams: Record<string, any> = {},
  ): Promise<{ items: T[]; total: number; pageInfo?: any; extra?: any }> {
    const params = {
      ...createPageParams(page, pageSize),
      ...createSearchParams(searchParams),
    };
    
    const response = await this.get<PageResponse<T>>(url, params);
    return getPageData(response);
  }

  /**
   * 获取树形数据
   */
  protected async getTree<T = any>(url: string, params?: any): Promise<T[]> {
    const response = await this.get<T[]>(url, params);
    return getArrayData(response);
  }

  /**
   * 获取选项数据
   */
  protected async getOptions<T = any>(url: string, params?: any): Promise<T[]> {
    const response = await this.get<T[]>(url, params);
    return getArrayData(response);
  }

  /**
   * 获取详情
   */
  protected async getDetail<T = any>(url: string, id: number | string): Promise<T> {
    return this.get<T>(`${url}/${id}`);
  }

  /**
   * 创建数据
   */
  protected async create<T = any>(url: string, data: any): Promise<T> {
    return this.post<T>(url, data);
  }

  /**
   * 更新数据
   */
  protected async update<T = any>(url: string, id: number | string, data: any): Promise<T> {
    return this.put<T>(`${url}/${id}`, data);
  }

  /**
   * 删除数据（支持单个或批量删除）
   */
  protected async remove<T = any>(url: string, ids: number | string | (number | string)[]): Promise<T> {
    if (Array.isArray(ids)) {
      // 批量删除 - 后端使用 DELETE /delete/{ids} 格式
      const idsString = ids.join(',');
      return this.delete<T>(`${url}/${idsString}`);
    } else {
      // 单个删除
      return this.delete<T>(`${url}/${ids}`);
    }
  }

  /**
   * 更新状态
   */
  protected async updateStatus<T = any>(url: string, id: number | string, status: number): Promise<T> {
    return this.put<T>(`${url}/${id}`, { status });
  }

  /**
   * 带重试的请求方法
   */
  protected async requestWithRetry<T = any>(
    method: 'get' | 'post' | 'put' | 'delete',
    url: string,
    data?: any,
    options?: any
  ): Promise<T> {
    const fullUrl = this.baseURL + url;
    const config = createRequestConfig(options);
    
    let lastError: any;
    
    for (let attempt = 0; attempt <= (config.retry ? 3 : 0); attempt++) {
      try {
        logApiRequest(fullUrl, data);
        
        let response: T;
        switch (method) {
          case 'get':
            response = await requestClient.get<T>(fullUrl, { params: data, ...config });
            break;
          case 'post':
            response = await requestClient.post<T>(fullUrl, data, config);
            break;
          case 'put':
            response = await requestClient.put<T>(fullUrl, data, config);
            break;
          case 'delete':
            response = await requestClient.delete<T>(fullUrl, { params: data, ...config });
            break;
          default:
            throw new Error(`Unsupported method: ${method}`);
        }
        
        logApiResponse(fullUrl, response);
        return response;
      } catch (error) {
        lastError = error;
        
        // 如果不是重试条件或已达到最大重试次数，直接抛出错误
        if (!shouldRetry(error) || attempt >= (config.retry ? 3 : 0)) {
          break;
        }
        
        // 等待后重试
        await new Promise(resolve => setTimeout(resolve, config.retryDelay || 1000));
      }
    }
    
    logApiError(fullUrl, lastError);
    handleApiError(lastError);
    throw lastError;
  }

  /**
   * 验证响应格式
   */
  protected validateResponse(response: any): boolean {
    return validateApiResponse(response);
  }

  /**
   * 获取错误状态码
   */
  protected getErrorCode(error: any): number | null {
    return getErrorStatusCode(error);
  }

  /**
   * 检查是否为网络错误
   */
  protected isNetworkError(error: any): boolean {
    return isNetworkError(error);
  }

  /**
   * 格式化响应数据
   */
  protected formatResponse<T = any>(response: any): T {
    return formatApiResponse<T>(response);
  }
}

/**
 * 系统管理API服务基类
 */
export class SystemApiService extends BaseApiService {
  constructor() {
    super(''); // 不设置基础路径，让子类直接使用完整路径
  }
}
