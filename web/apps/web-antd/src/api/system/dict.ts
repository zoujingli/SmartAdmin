/**
 * 数据字典 API。
 */
import { SystemApiService } from '../base';
import { createPageParams, createSearchParams } from '../utils';

export namespace DictApi {
  export interface DictInfo {
    id: number;
    pid: number;
    code: string;
    name: string;
    value: string;
    extra: Record<string, any>;
    sort: number;
    status: number;
    remark: string;
    created_by: number;
    updated_by: number;
    created_at: string;
    updated_at: string;
    deleted_at?: null | string;
    children?: DictInfo[];
  }

  export interface DictListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    pid?: number;
    status?: number;
  }

  export interface DictFormData {
    code: string;
    name: string;
    pid?: number;
    value?: string;
    extra?: Record<string, any> | string;
    sort?: number;
    status?: number;
    remark?: string;
  }

  export interface DictOption {
    label: string;
    value: string;
    code: string;
    name: string;
    extra: Record<string, any>;
  }

  export interface DictStatistics {
    total: number;
    today_created: number;
    active: number;
    inactive: number;
    active_count: number;
    inactive_count: number;
  }
}

class DictApiService extends SystemApiService {
  async getDictList(params: DictApi.DictListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);

    return this.getList<DictApi.DictInfo>('system/dict/index', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getRecycleList(params: DictApi.DictListParams = {}) {
    const searchParams = createSearchParams(params);
    const pageParams = createPageParams(params.page, params.pageSize);

    return this.getList<DictApi.DictInfo>('system/dict/recycle', pageParams.page, pageParams.pageSize, searchParams);
  }

  async getDictTree(params: Record<string, any> = {}) {
    return this.getTree<DictApi.DictInfo>('system/dict/tree', createSearchParams(params));
  }

  async getDictDetail(id: number) {
    return this.getDetail<DictApi.DictInfo>('system/dict/info', id);
  }

  async createDict(data: DictApi.DictFormData) {
    return this.create<DictApi.DictInfo>('system/dict/create', data);
  }

  async updateDict(id: number, data: DictApi.DictFormData) {
    return this.update<DictApi.DictInfo>('system/dict/update', id, data);
  }

  async deleteDict(id: number) {
    return this.remove('system/dict/delete', id);
  }

  async batchDeleteDicts(ids: number[]) {
    return this.remove('system/dict/delete', ids);
  }

  async recoveryDicts(ids: number[]) {
    return this.put(`system/dict/recovery/${ids.join(',')}`);
  }

  async realDeleteDicts(ids: number[]) {
    return this.delete(`system/dict/real-delete/${ids.join(',')}`);
  }

  async updateDictStatus(id: number, status: number) {
    return this.updateStatus('system/dict/status', id, status);
  }

  async updateDictSort(id: number, sort: number) {
    return this.put(`system/dict/sort/${id}`, { sort });
  }

  async getDictOptions(code: string, params: Record<string, any> = {}) {
    return this.getOptions<DictApi.DictOption>('system/dict/options', createSearchParams({ ...params, code }));
  }

  async getStatistics(params: DictApi.DictListParams = {}) {
    return this.get<DictApi.DictStatistics>('system/dict/statistics', createSearchParams(params));
  }
}

export const dictApiService = new DictApiService();
