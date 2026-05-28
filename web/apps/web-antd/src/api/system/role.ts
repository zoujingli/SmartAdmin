/**
 * 角色管理 API。
 */
import { SystemApiService } from '../base';
import { createPageParams, createSearchParams } from '../utils';

export namespace RoleApi {
  export interface RoleInfo {
    id: number;
    name: string;
    scope: number;
    sort: number;
    status: number;
    remark: string;
    created_by: number;
    updated_by: number;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    menuIds?: number[];
    nodes?: string[];
  }

  export interface RoleOption {
    id: number;
    name: string;
  }

  export interface RoleListParams {
    page?: number;
    pageSize?: number;
    name?: string;
    scope?: number;
    status?: number;
  }

  export interface RoleFormData {
    id?: number;
    name: string;
    scope: number;
    sort: number;
    status: number;
    remark?: string;
    menuIds?: number[];
    nodes?: string[];
  }

  export interface RoleSearchForm {
    name?: string;
    status?: number;
  }

  export interface RoleStatistics {
    total: number;
    today_created: number;
    active: number;
    inactive: number;
    active_count: number;
    inactive_count: number;
    by_status: Record<string, number>;
  }

}

class RoleApiService extends SystemApiService {
  constructor() {
    super();
  }

  async getRoleList(params: RoleApi.RoleListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);

    return this.getList<RoleApi.RoleInfo>(
      'system/role/index',
      pageParams.page,
      pageParams.pageSize,
      searchParams,
    );
  }

  async getRecycleList(params: RoleApi.RoleListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);

    return this.getList<RoleApi.RoleInfo>(
      'system/role/recycle',
      pageParams.page,
      pageParams.pageSize,
      searchParams,
    );
  }

  async getRoleDetail(id: number) {
    return this.getDetail<RoleApi.RoleInfo>('system/role/info', id);
  }

  async createRole(data: RoleApi.RoleFormData) {
    return this.create<RoleApi.RoleInfo>('system/role/create', data);
  }

  async updateRole(id: number, data: RoleApi.RoleFormData) {
    return this.update<RoleApi.RoleInfo>('system/role/update', id, data);
  }

  async deleteRole(id: number) {
    return this.remove('system/role/delete', id);
  }

  async batchDeleteRoles(ids: number[]) {
    return this.remove('system/role/delete', ids);
  }

  async recoveryRoles(ids: number[]) {
    return this.put(`system/role/recovery/${ids.join(',')}`);
  }

  async realDeleteRoles(ids: number[]) {
    return this.delete(`system/role/real-delete/${ids.join(',')}`);
  }

  async updateRoleStatus(id: number, status: number) {
    return this.updateStatus('system/role/status', id, status);
  }

  async getRoleOptions(params: Record<string, any> = {}) {
    return this.getOptions<RoleApi.RoleOption>('system/role/options', params);
  }

  async getRolePermissionTree() {
    return this.getTree<any>('system/role/permission-tree');
  }

  async getRoleStatistics() {
    return this.get<RoleApi.RoleStatistics>('system/role/statistics');
  }

  async getRoleNodes(id: number) {
    return this.get<string[]>(`system/role/nodes/${id}`);
  }

  async assignRoleNodes(id: number, nodes: string[]) {
    return this.put(`system/role/nodes/${id}`, { nodes });
  }
}

export const roleApiService = new RoleApiService();
