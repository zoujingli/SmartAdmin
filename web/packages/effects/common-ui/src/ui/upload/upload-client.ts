import { preferences } from '@vben-core/preferences';
import { useAppConfig } from '@vben/hooks';
import { useAccessStore } from '@vben/stores';
import { h } from 'vue';

import { notification } from 'ant-design-vue';

import { md5File } from './md5';
import type { UploadAsset, UploadFileOptions, UploadPreference, UploadRuntimeConfig, UploadTransport } from './types';

type HttpMethod = 'GET' | 'POST' | 'PUT';

export interface UploadEndpointConfig {
  abort: string;
  complete: string;
  list: string;
  partSign: string;
  prepare: string;
  relay: string;
  relayChunk: string;
  runtime: string;
}

type UploadPrepareResponse = {
  asset?: UploadAsset;
  complete_token?: string;
  completed?: boolean;
  driver?: string;
  file_field?: string;
  form_fields?: Record<string, string>;
  headers?: Record<string, string>;
  method?: string;
  part_count?: number;
  part_size?: number;
  transport?: UploadTransport | string;
  upload_session_id?: string;
  upload_url?: string;
};

const uploadTransports = ['instant', 'direct-single', 'direct-multipart', 'relay-single', 'relay-chunk'] as const;

class DirectTransportError extends Error {
  constructor(error: unknown) {
    super(error instanceof Error ? error.message : '直传失败');
    this.name = 'DirectTransportError';
  }
}

let uploadRuntimeCache: null | UploadRuntimeConfig = null;
let uploadEndpoints: null | UploadEndpointConfig = null;

export function configureUploadEndpoints(config: UploadEndpointConfig) {
  uploadEndpoints = { ...config };
  resetUploadRuntimeConfig();
}

function uploadEndpoint(key: keyof UploadEndpointConfig): string {
  const endpoint = uploadEndpoints?.[key];
  if (!endpoint) {
    throw new Error('未配置上传服务端点');
  }

  return endpoint;
}

function buildRequestHeaders() {
  const accessStore = useAccessStore();
  const headers: HeadersInit = {
    Accept: 'application/json',
    'Accept-Language': preferences.app.locale,
  };

  if (accessStore.accessToken) {
    headers.Authorization = `Bearer ${accessStore.accessToken}`;
  }

  return headers;
}

function resolveApiBase(): string {
  const runtimeBase = useAppConfig(import.meta.env, import.meta.env.PROD).apiURL || '/';
  if (!runtimeBase || runtimeBase === '/') {
    return '';
  }

  return String(runtimeBase).replace(/\/$/, '');
}

function buildUrl(path: string) {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  return `${resolveApiBase()}${path.startsWith('/') ? path : `/${path}`}`;
}

async function request<T>(method: HttpMethod, path: string, payload?: FormData | Record<string, any>): Promise<T> {
  const headers = buildRequestHeaders();

  const init: RequestInit = {
    headers,
    method,
  };

  if (method === 'GET' && payload && !(payload instanceof FormData)) {
    const search = new URLSearchParams();
    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') {
        return;
      }
      search.set(key, String(value));
    });
    path += `${path.includes('?') ? '&' : '?'}${search.toString()}`;
  } else if (payload instanceof FormData) {
    init.body = payload;
  } else if (payload) {
    headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(payload);
  }

  const response = await fetch(buildUrl(path), init);
  const text = await response.text();
  const json = text ? JSON.parse(text) : {};
  if (!response.ok || json.code !== 200) {
    throw new Error(json.info || json.message || '请求失败');
  }

  return json.data as T;
}

function requestFormByXhr<T>(
  path: string,
  formData: FormData,
  options: {
    method?: HttpMethod;
    onProgress?: (loaded: number, total: number) => void;
  } = {},
) {
  return new Promise<T>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open(options.method || 'POST', buildUrl(path), true);

    const headers = buildRequestHeaders();
    Object.entries(headers).forEach(([key, value]) => {
      if (value !== undefined) {
        xhr.setRequestHeader(key, String(value));
      }
    });

    if (xhr.upload && options.onProgress) {
      xhr.upload.onprogress = (event) => {
        options.onProgress?.(event.loaded, event.total || 0);
      };
    }

    xhr.onload = () => {
      try {
        const text = xhr.responseText || '';
        const json = text ? JSON.parse(text) : {};
        if (xhr.status >= 200 && xhr.status < 300 && json.code === 200) {
          resolve(json.data as T);
          return;
        }

        reject(new Error(json.info || json.message || `请求失败: ${xhr.status}`));
      } catch (error) {
        reject(error instanceof Error ? error : new Error('请求失败'));
      }
    };

    xhr.onerror = () => reject(new Error('请求失败'));
    xhr.send(formData);
  });
}

function uploadByXhr(
  url: string,
  file: Blob,
  options: {
    fileField?: string;
    formFields?: Record<string, string>;
    headers?: Record<string, string>;
    method?: string;
    onProgress?: (loaded: number, total: number) => void;
  } = {},
) {
  return new Promise<{ etag: null | string }>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open(options.method || 'PUT', url, true);

    const formFields = options.formFields || {};
    const hasFormFields = Object.keys(formFields).length > 0;
    Object.entries(options.headers || {}).forEach(([key, value]) => {
      if (hasFormFields && key.toLowerCase() === 'content-type') {
        return;
      }
      xhr.setRequestHeader(key, value);
    });

    if (xhr.upload && options.onProgress) {
      xhr.upload.onprogress = (event) => {
        options.onProgress?.(event.loaded, event.total || file.size);
      };
    }

    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve({
          etag: xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag'),
        });
        return;
      }

      reject(new Error(`上传失败: ${xhr.status}`));
    };
    xhr.onerror = () => reject(new Error('上传失败'));

    if (hasFormFields) {
      const formData = new FormData();
      Object.entries(formFields).forEach(([key, value]) => {
        formData.append(key, value);
      });
      formData.append(options.fileField || 'file', file);
      xhr.send(formData);
      return;
    }

    xhr.send(file);
  });
}

function resolveAcceptExtensions(runtime: UploadRuntimeConfig, scene?: string) {
  const allExtensions = (runtime.common?.allow_exts || '')
    .split(',')
    .map((item) => item.trim().toLowerCase())
    .filter(Boolean);

  const filterAllowed = (extensions: string[]) => {
    if (allExtensions.length === 0) {
      return extensions;
    }

    return extensions.filter((item) => allExtensions.includes(item));
  };

  if (scene === 'image') {
    return filterAllowed(['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico']);
  }
  if (scene === 'video') {
    return filterAllowed(['mp4', 'mov', 'webm', 'm4v', 'avi']);
  }
  if (scene === 'file') {
    return allExtensions.filter((item) => !['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'mp4', 'mov', 'webm', 'm4v', 'avi'].includes(item));
  }

  return allExtensions;
}

function buildUploadNotificationDescription(title: string, subtitle: string) {
  return h('div', { style: 'display:flex;flex-direction:column;gap:4px;max-width:320px;' }, [
    h('div', { style: 'font-weight:500;' }, title),
    h(
      'div',
      {
        style: 'font-size:12px;line-height:1.5;opacity:0.72;word-break:break-all;',
      },
      subtitle,
    ),
  ]);
}

function openUploadProgressNotification(key: string, fileName: string, percent: number) {
  notification.open({
    key,
    duration: 0,
    message: '上传中',
    placement: 'topRight',
    description: buildUploadNotificationDescription(fileName, `${percent}%`),
  });
}

function openUploadSuccessNotification(key: string, fileName: string, url: string, isInstant = false) {
  notification.success({
    key,
    duration: 2.5,
    message: isInstant ? '秒传完成' : '上传成功',
    placement: 'topRight',
    description: buildUploadNotificationDescription(fileName, url),
  });
}

function openUploadErrorNotification(key: string, fileName: string, error: unknown) {
  const messageText = error instanceof Error ? error.message : '上传失败';
  notification.error({
    key,
    duration: 4,
    message: '上传失败',
    placement: 'topRight',
    description: buildUploadNotificationDescription(fileName, messageText),
  });
}

function openUploadFallbackNotification(key: string, fileName: string) {
  notification.open({
    key,
    duration: 0,
    message: '上传中',
    placement: 'topRight',
    description: buildUploadNotificationDescription(fileName, '直传不可用，已切换为服务端中转上传'),
  });
}

function assertUploadTransport(transport?: string): UploadTransport {
  if (uploadTransports.includes(transport as UploadTransport)) {
    return transport as UploadTransport;
  }

  throw new Error(`不支持的上传方式: ${transport || 'unknown'}`);
}

async function uploadRelaySingle(
  uploadSessionId: string,
  file: File,
  reportProgress: (percent: number) => void,
): Promise<UploadAsset> {
  const form = new FormData();
  form.append('upload_session_id', uploadSessionId);
  form.append('file', file);

  return requestFormByXhr<UploadAsset>(uploadEndpoint('relay'), form, {
    onProgress: (loaded, total) => {
      if (total > 0) {
        reportProgress(Math.min(99, Math.round((loaded / total) * 100)));
      }
    },
  });
}

async function abortUploadSession(uploadSessionId?: string) {
  if (!uploadSessionId) {
    return;
  }

  await request('POST', uploadEndpoint('abort'), {
    upload_session_id: uploadSessionId,
  }).catch(() => undefined);
}

export async function getUploadRuntimeConfig(force = false) {
  if (!force && uploadRuntimeCache) {
    return uploadRuntimeCache;
  }

  uploadRuntimeCache = await request<UploadRuntimeConfig>('GET', uploadEndpoint('runtime'));
  return uploadRuntimeCache;
}

export function resetUploadRuntimeConfig() {
  uploadRuntimeCache = null;
}

export async function listUploadAssets(params: Record<string, any> = {}) {
  return request<{ items: UploadAsset[]; pageInfo: { total: number } }>('GET', uploadEndpoint('list'), params);
}

export async function uploadFile(
  file: File,
  options: UploadFileOptions = {},
): Promise<UploadAsset> {
  const noticeKey = `upload:${file.name}:${Date.now()}:${Math.random().toString(36).slice(2, 8)}`;
  let lastProgress = -1;
  let currentUploadSessionId: string | undefined;
  const reportProgress = (percent: number) => {
    const nextPercent = Math.max(0, Math.min(100, percent));
    options.onProgress?.(nextPercent);
    if (nextPercent >= 100 || nextPercent === lastProgress) {
      lastProgress = nextPercent;
      return;
    }
    lastProgress = nextPercent;
    openUploadProgressNotification(noticeKey, file.name, nextPercent);
  };

  reportProgress(0);
  const hash = await md5File(file);
  const prepareUpload = async (uploadType?: UploadPreference) => {
    const prepare = await request<UploadPrepareResponse>('POST', uploadEndpoint('prepare'), {
      hash,
      driver: options.driver,
      mime_type: file.type || 'application/octet-stream',
      mode: options.mode,
      name: file.name,
      size: file.size,
      upload_type: uploadType,
    });
    currentUploadSessionId = prepare.upload_session_id;

    return prepare;
  };

  const executePreparedUpload = async (prepare: UploadPrepareResponse): Promise<UploadAsset> => {
    if (prepare.completed && prepare.asset) {
      options.onProgress?.(100);
      openUploadSuccessNotification(noticeKey, file.name, prepare.asset.url, true);
      return prepare.asset;
    }

    const transport = assertUploadTransport(prepare.transport);
    if (!prepare.upload_session_id || !transport) {
      throw new Error('上传会话初始化失败');
    }

    if (transport === 'relay-single') {
      const asset = await uploadRelaySingle(prepare.upload_session_id, file, reportProgress);
      options.onProgress?.(100);
      openUploadSuccessNotification(noticeKey, file.name, asset.url);
      return asset;
    }

    if (transport === 'relay-chunk') {
      const partSize = prepare.part_size || 5 * 1024 * 1024;
      const totalChunks = Math.max(1, Math.ceil(file.size / partSize));
      let completedAsset: null | UploadAsset = null;

      for (let chunkIndex = 1; chunkIndex <= totalChunks; chunkIndex += 1) {
        const start = (chunkIndex - 1) * partSize;
        const end = Math.min(start + partSize, file.size);
        const chunk = file.slice(start, end);
        const form = new FormData();
        form.append('upload_session_id', prepare.upload_session_id);
        form.append('chunk_index', String(chunkIndex));
        form.append('total_chunks', String(totalChunks));
        form.append('file', chunk, `${file.name}.part`);

        const response = await requestFormByXhr<any>(uploadEndpoint('relayChunk'), form, {
          onProgress: (loaded, total) => {
            const effectiveTotal = total || chunk.size;
            const currentUploaded = start + Math.min(loaded, effectiveTotal);
            reportProgress(Math.min(99, Math.round((currentUploaded / file.size) * 100)));
          },
        });
        if (response?.completed && response?.asset) {
          completedAsset = response.asset as UploadAsset;
        }
      }

      if (!completedAsset) {
        throw new Error('分块上传未完成');
      }

      options.onProgress?.(100);
      openUploadSuccessNotification(noticeKey, file.name, completedAsset.url);
      return completedAsset;
    }

    if (transport === 'direct-single') {
      if (!prepare.upload_url) {
        throw new Error('缺少直传地址');
      }

      try {
        await uploadByXhr(prepare.upload_url, file, {
          fileField: prepare.file_field,
          formFields: prepare.form_fields,
          headers: prepare.headers,
          method: prepare.method || 'PUT',
          onProgress: (loaded, total) => {
            if (total > 0) {
              reportProgress(Math.min(99, Math.round((loaded / total) * 100)));
            }
          },
        });
      } catch (error) {
        throw new DirectTransportError(error);
      }

      const asset = await request<UploadAsset>('POST', uploadEndpoint('complete'), {
        complete_token: prepare.complete_token,
        upload_session_id: prepare.upload_session_id,
      });
      options.onProgress?.(100);
      openUploadSuccessNotification(noticeKey, file.name, asset.url);
      return asset;
    }

    if (transport === 'direct-multipart') {
      const partSize = prepare.part_size || 5 * 1024 * 1024;
      const partCount = Math.max(1, prepare.part_count || Math.ceil(file.size / partSize));
      const parts: Array<{ etag: string; part_number: number }> = [];
      let uploadedBytes = 0;

      try {
        // 分片直传逐片签名，避免一次性暴露整批长期有效签名；完成时再把 ETag 列表交给后端闭合会话。
        for (let partNumber = 1; partNumber <= partCount; partNumber += 1) {
          const start = (partNumber - 1) * partSize;
          const end = Math.min(start + partSize, file.size);
          const chunk = file.slice(start, end);
          const uploadedBefore = uploadedBytes;
          const signResult = await request<{
            file_field?: string;
            form_fields?: Record<string, string>;
            headers?: Record<string, string>;
            method?: string;
            upload_url: string;
          }>('POST', uploadEndpoint('partSign'), {
            part_number: partNumber,
            upload_session_id: prepare.upload_session_id,
          });
          const uploadResult = await uploadByXhr(signResult.upload_url, chunk, {
            fileField: signResult.file_field,
            formFields: signResult.form_fields,
            headers: signResult.headers,
            method: signResult.method || 'PUT',
            onProgress: (loaded, total) => {
              const effectiveTotal = total || chunk.size;
              const currentUploaded = uploadedBefore + Math.min(loaded, effectiveTotal);
              reportProgress(Math.min(99, Math.round((currentUploaded / file.size) * 100)));
            },
          });
          uploadedBytes = end;
          parts.push({
            etag: (uploadResult.etag || '').replace(/"/g, ''),
            part_number: partNumber,
          });
        }
      } catch (error) {
        throw new DirectTransportError(error);
      }

      const asset = await request<UploadAsset>('POST', uploadEndpoint('complete'), {
        complete_token: prepare.complete_token,
        parts,
        upload_session_id: prepare.upload_session_id,
      });
      options.onProgress?.(100);
      openUploadSuccessNotification(noticeKey, file.name, asset.url);
      return asset;
    }

    throw new Error(`不支持的上传方式: ${transport}`);
  };

  try {
    const prepare = await prepareUpload(options.uploadType);
    try {
      return await executePreparedUpload(prepare);
    } catch (error) {
      if (
        error instanceof DirectTransportError
        && options.uploadType !== 'relay'
        && ['direct-single', 'direct-multipart'].includes(String(prepare.transport))
        && prepare.upload_session_id
      ) {
        // 直传失败不能复用 direct 会话整包中转；必须先终止旧会话，再重新 prepare 让后端按通道能力和请求体上限调度。
        await abortUploadSession(prepare.upload_session_id);
        openUploadFallbackNotification(noticeKey, file.name);
        return await executePreparedUpload(await prepareUpload('relay'));
      }

      throw error;
    }
  } catch (error) {
    // 失败时通知后端清理上传会话和可能存在的远端分片，清理失败不覆盖真正的上传错误。
    await abortUploadSession(currentUploadSessionId);
    openUploadErrorNotification(noticeKey, file.name, error);
    throw error;
  }
}

export async function uploadSceneFile(
  scene: string,
  file: File,
  options: Omit<UploadFileOptions, 'mode'> = {},
): Promise<UploadAsset> {
  return uploadFile(file, { ...options, mode: scene });
}

export function resolveUploadAccept(runtime: UploadRuntimeConfig, scene?: string) {
  return resolveAcceptExtensions(runtime, scene).map((ext) => `.${ext}`).join(',');
}

export const __uploadClientTestHooks = import.meta.env.MODE === 'test'
  ? {
      assertUploadTransport,
      resolveAcceptExtensions,
      uploadByXhr,
    }
  : undefined;
