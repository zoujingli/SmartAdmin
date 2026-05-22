/**
 * 该文件可自行根据业务逻辑进行调整
 */
import type { RequestClientOptions } from '@vben/request';

import { preferences } from '@vben/preferences';
import {
  authenticateResponseInterceptor,
  defaultResponseInterceptor,
  errorMessageResponseInterceptor,
  RequestClient,
} from '@vben/request';
import { useAccessStore } from '@vben/stores';

import { message } from 'ant-design-vue';

import { useAuthStore } from '#/store';

import { coreAuthApiService, getAuthEntry, persistAuthToken } from './core';
import { ENV_CONFIG, REQUEST_CONFIG_FINAL } from './config';

function createRequestClient(baseURL: string, options?: RequestClientOptions) {
  const client = new RequestClient({ ...options, baseURL });

  /**
   * 重新认证逻辑
   */
  async function doReAuthenticate() {
    console.warn('Access token or refresh token is invalid or expired. ');
    const accessStore = useAccessStore();
    const authStore = useAuthStore();
    accessStore.setAccessToken(null);
    if (
      preferences.app.loginExpiredMode === 'modal' &&
      accessStore.isAccessChecked
    ) {
      accessStore.setLoginExpired(true);
    } else {
      await authStore.logout();
    }
  }

  /**
   * 刷新token逻辑
   */
  async function doRefreshToken() {
    const accessStore = useAccessStore();
    const resp = await coreAuthApiService.refreshToken();
    const responseData = (resp as any)?.data ?? resp;
    // 刷新接口也返回标准 body.code；只有业务码 200 才允许替换本地 Token。
    if (Number(responseData?.code) !== 200) {
      throw new Error(responseData?.info || '刷新令牌失败');
    }
    const newToken = responseData.data;
    if (!newToken) {
      throw new Error('刷新令牌响应无效');
    }

    accessStore.setAccessToken(newToken);
    // 不同认证入口使用独立 Token 缓存；刷新令牌后必须写回当前入口，
    // 否则入口切换或页面刷新会重新装载旧 Token，profile 接口会被判定为未登录。
    persistAuthToken(getAuthEntry(), newToken);
    return newToken;
  }

  function formatToken(token: null | string) {
    return token ? `Bearer ${token}` : null;
  }

  // 请求头处理
  client.addRequestInterceptor({
    fulfilled: async (config) => {
      const accessStore = useAccessStore();

      config.headers.Authorization = formatToken(accessStore.accessToken);
      config.headers['Accept-Language'] = preferences.app.locale;
      return config;
    },
  });

  // 处理返回的响应数据格式
  client.addResponseInterceptor(
    defaultResponseInterceptor({
      codeField: REQUEST_CONFIG_FINAL.response.codeField,
      dataField: REQUEST_CONFIG_FINAL.response.dataField,
      successCode: REQUEST_CONFIG_FINAL.response.successCode,
    }),
  );

  // token过期的处理
  client.addResponseInterceptor(
    authenticateResponseInterceptor({
      client,
      doReAuthenticate,
      doRefreshToken,
      enableRefreshToken: preferences.app.enableRefreshToken,
      formatToken,
    }),
  );

  // 通用的错误处理,如果没有进入上面的错误处理逻辑，就会进入这里
  client.addResponseInterceptor(
    errorMessageResponseInterceptor((msg: string, error) => {
      const responseData = error?.response?.data ?? {};
      const errorMessage =
        responseData?.info ?? responseData?.error ?? responseData?.message ?? '';

      // body.code=401 由 authenticateResponseInterceptor 统一处理，这里只负责展示后端错误信息
      message.error(errorMessage || msg);
    }),
  );

  return client;
}

export const requestClient = createRequestClient(ENV_CONFIG.apiUrl, {
  responseReturn: 'data',
});

export const baseRequestClient = new RequestClient({ baseURL: ENV_CONFIG.apiUrl });
