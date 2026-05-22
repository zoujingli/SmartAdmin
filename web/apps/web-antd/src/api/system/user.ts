/**
 * 用户管理 API。
 */
import { SystemApiService } from '../base';
import { encryptPasswordFields, PASSWORD_PURPOSES } from '../core/password-crypto';
import { createPageParams, createSearchParams } from '../utils';

// 用户相关类型定义
export namespace UserApi {
  /** 用户信息 */
  export interface UserInfo {
    id: number;
    tenant_id: number;
    username: string;
    nickname: string;
    phone: string;
    email: string;
    avatar: string;
    signed: string;
    status: number;
    remark: string;
    login_ip: string;
    login_time: string;
    extra: any[];
    created_by: number;
    updated_by: number;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    roleIds: number[];
    deptId: number | null;
    postIds: number[];
    roleNames?: string[];
    deptName?: string;
    postNames?: string[];
    createdAt?: string;
    roles: Array<{
      id: number;
      name: string;
      scope: number;
      pivot: {
        user_id: number;
        role_id: number;
      };
    }>;
    depts: Array<{
      id: number;
      name: string;
      pivot: {
        user_id: number;
        dept_id: number;
      };
    }>;
    posts: any[];
  }

  /** 用户列表参数 */
  export interface UserListParams {
    page?: number;
    pageSize?: number;
    username?: string;
    nickname?: string;
    email?: string;
    phone?: string;
    deptId?: number;
    status?: number;
  }

  /** 用户表单数据 */
  export interface UserFormData {
    id?: number;
    tenant_id?: number;
    username: string;
    nickname: string;
    email: string;
    phone: string;
    deptId?: number | null;
    roleIds: number[];
    postIds: number[];
    status: number;
    remark?: string;
    password?: string;
  }

  /** 用户搜索表单 */
  export interface UserSearchForm {
    username?: string;
    nickname?: string;
    email?: string;
    phone?: string;
    deptId?: number;
    status?: number;
  }

  /** 用户统计信息 */
  export interface UserStatistics {
    total: number;
    today_created: number;
    active: number;
    inactive: number;
    active_count: number;
    inactive_count: number;
    by_status: Record<string, number>;
  }

  export interface UserOption {
    id: number;
    username: string;
    nickname: string;
    avatar: string;
    label: string;
  }
}

/**
 * 用户管理 API 服务。
 */
class UserApiService extends SystemApiService {
  /**
   * 获取用户列表。
   */
  async getUserList(params: UserApi.UserListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);
    return this.getList<UserApi.UserInfo>('system/user/index', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getRecycleList(params: UserApi.UserListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);
    return this.getList<UserApi.UserInfo>('system/user/recycle', pageParams.page, pageParams.pageSize, searchParams);
  }

  /**
   * 获取用户详情。
   */
  async getUserDetail(id: number) {
    return this.getDetail<UserApi.UserInfo>('system/user/info', id);
  }

  /**
   * 创建用户。
   */
  async createUser(data: UserApi.UserFormData) {
    const payload = await encryptPasswordFields(data, {
      password: PASSWORD_PURPOSES.userCreate,
    });

    return this.create<UserApi.UserInfo>('system/user/create', payload);
  }

  /**
   * 更新用户。
   */
  async updateUser(id: number, data: UserApi.UserFormData) {
    const payload = await encryptPasswordFields(data, {
      password: PASSWORD_PURPOSES.userUpdate,
    });

    return this.update<UserApi.UserInfo>('system/user/update', id, payload);
  }

  /**
   * 删除用户。
   */
  async deleteUser(id: number) {
    return this.remove('system/user/delete', id);
  }

  /**
   * 批量删除用户。
   */
  async batchDeleteUsers(ids: number[]) {
    return this.remove('system/user/delete', ids);
  }

  async recoveryUsers(ids: number[]) {
    return this.put(`system/user/recovery/${ids.join(',')}`);
  }

  async realDeleteUsers(ids: number[]) {
    return this.delete(`system/user/real-delete/${ids.join(',')}`);
  }

  /**
   * 更新用户状态。
   */
  async updateUserStatus(id: number, status: number) {
    return this.updateStatus('system/user/status', id, status);
  }

  /**
   * 重置用户密码。
   */
  async resetUserPassword(id: number, password: string) {
    const payload = await encryptPasswordFields({ password }, {
      password: PASSWORD_PURPOSES.userReset,
    });

    return this.put(`system/user/reset-password/${id}`, payload);
  }

  /**
   * 获取用户统计信息。
   */
  async getUserStatistics() {
    return this.get<UserApi.UserStatistics>('system/user/statistics');
  }

  async getUserOptions(params: { keyword?: string; limit?: number } = {}) {
    return this.getOptions<UserApi.UserOption>('system/user/options', createSearchParams(params));
  }
}

// 导出单例实例
export const userApiService = new UserApiService();
