/**
 * 租户管理 API。
 */
import { SystemApiService } from '../base';
import { encryptPasswordFields, PASSWORD_PURPOSES } from '../core/password-crypto';
import { createPageParams, createSearchParams } from '../utils';

export namespace TenantApi {
  export interface TenantInfo {
    id: number;
    code: string;
    name: string;
    contact_name: string;
    contact_phone: string;
    contact_email: string;
    package_code: string;
    expired_at: null | string;
    status: number;
    remark: string;
    created_by: number;
    updated_by: number;
    created_at: string;
    updated_at: string;
  }

  export interface TenantListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    status?: number;
  }

  export interface TenantFormData {
    code: string;
    name: string;
    contact_name?: string;
    contact_phone?: string;
    contact_email?: string;
    package_code?: string;
    expired_at?: null | string;
    status?: number;
    remark?: string;
    admin_username?: string;
    admin_password?: string;
    admin_nickname?: string;
    admin_phone?: string;
    admin_email?: string;
  }

  export interface TenantStatistics {
    total: number;
    active: number;
    inactive: number;
    today_created: number;
  }

}

export class TenantApiService extends SystemApiService {
  async getTenantList(params: TenantApi.TenantListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);

    return this.getList<TenantApi.TenantInfo>('system/tenant/index', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getRecycleList(params: TenantApi.TenantListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);

    return this.getList<TenantApi.TenantInfo>('system/tenant/recycle', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getTenantDetail(id: number) {
    return this.getDetail<TenantApi.TenantInfo>('system/tenant/info', id);
  }

  async createTenant(data: TenantApi.TenantFormData) {
    const payload = await encryptPasswordFields(data, {
      admin_password: PASSWORD_PURPOSES.userCreate,
    });

    return this.create<TenantApi.TenantInfo>('system/tenant/create', payload);
  }

  async updateTenant(id: number, data: TenantApi.TenantFormData) {
    return this.update<TenantApi.TenantInfo>('system/tenant/update', id, data);
  }

  async deleteTenant(id: number) {
    return this.remove('system/tenant/delete', id);
  }

  async batchDeleteTenants(ids: number[]) {
    return this.remove('system/tenant/delete', ids);
  }

  async recoveryTenants(ids: number[]) {
    return this.put(`system/tenant/recovery/${ids.join(',')}`);
  }

  async realDeleteTenants(ids: number[]) {
    return this.delete(`system/tenant/real-delete/${ids.join(',')}`);
  }

  async updateTenantStatus(id: number, status: number) {
    return this.updateStatus('system/tenant/status', id, status);
  }

  async getStatistics(params: TenantApi.TenantListParams = {}) {
    return this.get<TenantApi.TenantStatistics>('system/tenant/statistics', createSearchParams(params));
  }

  async getTenantOptions(params: { keyword?: string; limit?: number } = {}) {
    return this.getOptions<{ code: string; id: number; label: string; name: string }>('system/tenant/options', createSearchParams(params));
  }

}

export const tenantApiService = new TenantApiService();
