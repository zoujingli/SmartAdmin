/**
 * 部门管理 API - 重构版本
 */
import { SystemApiService } from '../base';

// 部门相关类型定义
export namespace DeptApi {
  /** 部门信息 */
  export interface DeptInfo {
    id: number;
    pid: number;
    name: string;
    code?: string;
    phone: string;
    level: string;
    leader: string;
    email?: string;
    sort: number;
    status: number;
    remark: string;
    created_by: number;
    updated_by: number;
    created_at: string;
    updated_at: string;
    deleted_at?: null | string;
    children?: DeptInfo[];
  }

  /** 部门选项 */
  export interface DeptOption {
    id: number;
    name: string;
    children?: DeptOption[];
  }

  /** 部门表单数据 */
  export interface DeptFormData {
    id?: number;
    pid: number;
    code: string;
    name: string;
    phone: string;
    email?: string;
    level: string;
    leader: string;
    sort: number;
    status: number;
    remark?: string;
  }

  /** 部门统计信息 */
  export interface DeptStatistics {
    total: number;
    today_created: number;
    active_count: number;
    inactive_count: number;
    by_status: Record<string, number>;
  }

}

/**
 * 部门管理 API 服务
 */
class DeptApiService extends SystemApiService {
  constructor() {
    super();
  }

  /**
   * 获取部门列表
   */
  async getDeptList(params: any) {
    return this.getList<DeptApi.DeptInfo>('system/dept/index', params.page, params.pageSize, params);
  }

  async getRecycleList(params: any = {}) {
    return this.getList<DeptApi.DeptInfo>('system/dept/recycle', params.page, params.pageSize, params);
  }

  /**
   * 获取部门树
   */
  async getDeptTree(params: Record<string, any> = {}) {
    return this.getTree<DeptApi.DeptInfo>('system/dept/tree', params);
  }

  /**
   * 获取部门详情
   */
  async getDeptDetail(id: number) {
    return this.getDetail<DeptApi.DeptInfo>('system/dept/info', id);
  }

  /**
   * 创建部门
   */
  async createDept(data: DeptApi.DeptFormData) {
    return this.create<DeptApi.DeptInfo>('system/dept/create', data);
  }

  /**
   * 更新部门
   */
  async updateDept(id: number, data: DeptApi.DeptFormData) {
    return this.update<DeptApi.DeptInfo>('system/dept/update', id, data);
  }

  /**
   * 删除部门
   */
  async deleteDept(id: number) {
    return this.remove('system/dept/delete', id);
  }

  async recoveryDepts(ids: number[]) {
    return this.put(`system/dept/recovery/${ids.join(',')}`);
  }

  async realDeleteDepts(ids: number[]) {
    return this.delete(`system/dept/real-delete/${ids.join(',')}`);
  }

  /**
   * 更新部门状态
   */
  async updateDeptStatus(id: number, status: number) {
    return this.updateStatus('system/dept/status', id, status);
  }

  /**
   * 获取部门选项（树形结构）
   */
  async getDeptOptions(params: Record<string, any> = {}) {
    return this.getOptions<DeptApi.DeptOption>('system/dept/options', params);
  }

  /**
   * 获取部门统计信息
   */
  async getDeptStatistics() {
    return this.get<DeptApi.DeptStatistics>('system/dept/statistics');
  }

}

// 导出单例实例
export const deptApiService = new DeptApiService();
