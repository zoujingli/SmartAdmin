import { SystemApiService } from '../base';
import { createPageParams, createSearchParams, getPageData } from '../utils';

export namespace FileApi {
  export interface FileRecord {
    id: number;
    scene: string;
    driver: string;
    url: string;
    preview_url: string;
    download_url: string;
    hash: null | string;
    suffix: string;
    storage_mode: number;
    origin_name: string;
    object_name: string;
    storage_path: string;
    mime_type: string;
    size_byte: number;
    size_info: string;
    remark: string;
    created_at: string;
    deleted_at: null | string;
  }

  export interface FileListParams {
    page?: number;
    pageSize?: number;
    driver?: string;
    origin_name?: string;
    scene?: string;
    storage_mode?: number;
  }

  export interface FileStatistics {
    total: number;
    today_uploaded: number;
    total_size_byte: number;
    by_driver: Record<string, number>;
    by_scene: Record<string, number>;
    by_storage_mode: Record<string, number>;
  }

  export interface UploadConfig {
    active_mode: string;
    common: Record<string, any>;
    drivers: Record<string, any>;
    driver_meta?: Record<string, UploadDriverMeta>;
  }

  export interface UploadDriverRegion {
    label: string;
    suggested_endpoint?: string;
    value: string;
  }

  export interface UploadDriverMeta {
    direct_upload: boolean;
    fields?: Record<string, { help?: string; placeholder?: string }>;
    multipart_upload: boolean;
    regions: UploadDriverRegion[];
    title: string;
  }

  export interface DedupeTarget {
    driver?: string;
    hash: string;
  }

  export interface DedupeResult {
    deleted_count: number;
    deleted_ids: number[];
    group_count?: number;
    groups?: Array<{
      deleted_count: number;
      deleted_ids: number[];
      driver?: string;
      hash: string;
      kept_id?: number;
    }>;
  }
}

class FileApiService extends SystemApiService {
  async getFileList(params: FileApi.FileListParams = {}) {
    const response = await this.get<any>('system/file/index', {
      ...createPageParams(params.page, params.pageSize),
      ...createSearchParams(params),
    });

    return getPageData<FileApi.FileRecord>(response);
  }

  async getRecycleList(params: FileApi.FileListParams = {}) {
    const response = await this.get<any>('system/file/recycle', {
      ...createPageParams(params.page, params.pageSize),
      ...createSearchParams(params),
    });

    return getPageData<FileApi.FileRecord>(response);
  }

  async getFileDetail(id: number) {
    return this.get<FileApi.FileRecord>(`system/file/info/${id}`);
  }

  async updateFile(id: number, data: Partial<FileApi.FileRecord>) {
    return this.put<FileApi.FileRecord>(`system/file/update/${id}`, data);
  }

  async deleteFile(id: number) {
    return this.delete(`system/file/delete/${id}`);
  }

  async batchDeleteFiles(ids: number[]) {
    return this.delete(`system/file/delete/${ids.join(',')}`);
  }

  async realDeleteFiles(ids: number[]) {
    return this.delete(`system/file/real-delete/${ids.join(',')}`);
  }

  async recoveryFiles(ids: number[]) {
    return this.put(`system/file/recovery/${ids.join(',')}`);
  }

  async getStatistics() {
    return this.get<FileApi.FileStatistics>('system/file/statistics');
  }

  async getUploadConfig() {
    return this.get<FileApi.UploadConfig>('system/file/upload-config');
  }

  async updateUploadConfig(data: FileApi.UploadConfig) {
    return this.put<FileApi.UploadConfig>('system/file/upload-config', data);
  }

  async dedupeByHash(hash: string, driver?: string) {
    return this.post<FileApi.DedupeResult & {
      driver?: string;
      hash: string;
      kept_id?: number;
    }>('system/file/dedupe', { hash, driver });
  }

  async batchDedupe(items: FileApi.DedupeTarget[]) {
    return this.post<FileApi.DedupeResult>('system/file/dedupe', { items });
  }
}

export const fileApiService = new FileApiService();
