export interface UploadAsset {
  id: number;
  scene?: string;
  driver?: string;
  url: string;
  preview_url: string;
  download_url: string;
  hash: null | string;
  suffix: string;
  origin_name: string;
  object_name: string;
  storage_mode: number;
  storage_path: string;
  mime_type: string;
  size_byte: number;
  size_info: string;
  remark?: string;
  created_at?: null | string;
  deleted_at?: null | string;
}

export type UploadFieldMode = 'file' | 'image' | 'video';

/** 上传偏好只表达 direct/relay 倾向，最终 transport 必须以后端 prepare 返回为准。 */
export type UploadPreference = 'direct' | 'relay';

/** 后端 prepare 返回的标准上传执行通道。 */
export type UploadTransport = 'direct-multipart' | 'direct-single' | 'instant' | 'relay-chunk' | 'relay-single';

export type UploadFieldValue = null | UploadAsset | UploadAsset[];

/** 公共上传器参数；业务页面不得在该层之外自行判断存储通道细节。 */
export interface UploadFileOptions {
  driver?: string;
  mode?: string;
  onProgress?: (percent: number) => void;
  uploadType?: UploadPreference;
}

export interface UploadFieldProps {
  allowSelectExisting?: boolean;
  buttonText?: string;
  clearable?: boolean;
  disabled?: boolean;
  driver?: string;
  limit?: number;
  mode?: UploadFieldMode;
  modelValue: UploadFieldValue;
  multiple?: boolean;
  placeholder?: string;
  readonly?: boolean;
  scene?: string;
  sortable?: boolean;
  uploadType?: UploadPreference;
}

export interface UploadDriverRuntimeItem {
  direct_upload: boolean;
  enabled: boolean;
  multipart_upload: boolean;
  relay_upload: boolean;
  title: string;
}

export interface UploadRuntimeConfig {
  active_mode: string;
  common: {
    allow_exts: string;
    chunk_threshold_mb: number;
    link_type: string;
    max_size_mb: number;
    multipart_threshold_mb: number;
    name_type: string;
    part_size_mb: number;
    protocol: string;
  };
  drivers: Record<string, UploadDriverRuntimeItem>;
  active_driver: UploadDriverRuntimeItem;
}
