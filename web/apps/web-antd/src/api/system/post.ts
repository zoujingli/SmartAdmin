/**
 * 岗位管理 API。
 */
import { SystemApiService } from '../base';
import { createPageParams, createSearchParams } from '../utils';

// 岗位相关类型定义
export namespace PostApi {
  /** 岗位信息 */
  export interface PostInfo {
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
    deleted_at?: null | string;
  }

  /** 岗位列表参数 */
  export interface PostListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    status?: number;
  }

  /** 岗位表单数据 */
  export interface PostFormData {
    code: string;
    name: string;
    sort?: number;
    status?: number;
    remark?: string;
  }

  /** 岗位选项 */
  export interface PostOption {
    id: number;
    name: string;
  }

  /** 岗位统计 */
  export interface PostStatistics {
    total: number;
    today_created: number;
    active: number;
    inactive: number;
    active_count: number;
    inactive_count: number;
    by_status: Record<string, number>;
  }

}

/**
 * 岗位管理 API 服务。
 */
export class PostApiService extends SystemApiService {
  constructor() {
    super();
  }

  /**
   * 获取岗位列表
   */
  async getPostList(params: PostApi.PostListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);
    
    return this.getList<PostApi.PostInfo>('system/post/index', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getRecycleList(params: PostApi.PostListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);

    return this.getList<PostApi.PostInfo>('system/post/recycle', pageParams.page, pageParams.pageSize, searchParams);
  }

  /**
   * 获取岗位详情
   */
  async getPostDetail(id: number) {
    return this.getDetail<PostApi.PostInfo>('system/post/info', id);
  }

  /**
   * 创建岗位
   */
  async createPost(data: PostApi.PostFormData) {
    return this.create<PostApi.PostInfo>('system/post/create', data);
  }

  /**
   * 更新岗位
   */
  async updatePost(id: number, data: PostApi.PostFormData) {
    return this.update<PostApi.PostInfo>('system/post/update', id, data);
  }

  /**
   * 删除岗位
   */
  async deletePost(id: number) {
    return this.remove('system/post/delete', id);
  }

  /**
   * 批量删除岗位
   */
  async batchDeletePosts(ids: number[]) {
    return this.remove('system/post/delete', ids);
  }

  async recoveryPosts(ids: number[]) {
    return this.put(`system/post/recovery/${ids.join(',')}`);
  }

  async realDeletePosts(ids: number[]) {
    return this.delete(`system/post/real-delete/${ids.join(',')}`);
  }

  /**
   * 更新岗位状态
   */
  async updatePostStatus(id: number, status: number) {
    return this.updateStatus('system/post/status', id, status);
  }

  /**
   * 获取岗位统计。
   */
  async getStatistics(params: PostApi.PostListParams = {}) {
    return this.get<PostApi.PostStatistics>('system/post/statistics', createSearchParams(params));
  }

  /**
   * 获取岗位选项（用于下拉框）。
   */
  async getPostOptions(params: Record<string, any> = {}) {
    return this.getOptions<PostApi.PostOption>('system/post/options', params);
  }
}

// 导出 API 服务实例
export const postApiService = new PostApiService();
