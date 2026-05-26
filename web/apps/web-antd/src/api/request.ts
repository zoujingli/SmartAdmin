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

const AUTH_EXPIRED_MESSAGE = '登录状态已失效，请重新登录';

function createRequestClient(baseURL: string, options?: RequestClientOptions) {
  const client = new RequestClient({ ...options, baseURL });
  let reAuthenticating: null | Promise<void> = null;
  let reAuthenticateNotified = false;

  /**
   * 重新认证逻辑
   */
  async function doReAuthenticate() {
    if (reAuthenticating) {
      return reAuthenticating;
    }

    reAuthenticating = (async () => {
      console.warn('Access token or refresh token is invalid or expired. ');
      const accessStore = useAccessStore();
      const authStore = useAuthStore();
      accessStore.setAccessToken(null);
      // 同一轮 Token 失效只提示一次，具体跳转/弹层仍沿用当前登录过期模式。
      if (!reAuthenticateNotified) {
        reAuthenticateNotified = true;
        message.warning(AUTH_EXPIRED_MESSAGE);
      }
      if (
        preferences.app.loginExpiredMode === 'modal' &&
        accessStore.isAccessChecked
      ) {
        accessStore.setLoginExpired(true);
      } else {
        await authStore.logout();
      }
    })();

    try {
      await reAuthenticating;
    } finally {
      reAuthenticating = null;
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

      if (accessStore.accessToken) {
        reAuthenticateNotified = false;
      }
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
      const status = Number(responseData?.code ?? error?.response?.status);
      const errorMessage =
        status === 401
          ? AUTH_EXPIRED_MESSAGE
          : responseData?.info ?? responseData?.error ?? responseData?.message ?? '';

      // 401 面向用户只展示简短登录失效文案，后端节点详情仅保留给接口响应和日志定位。
      message.error(errorMessage || msg);
    }),
  );

  return client;
}

export const requestClient = createRequestClient(ENV_CONFIG.apiUrl, {
  responseReturn: 'data',
});

export const baseRequestClient = new RequestClient({ baseURL: ENV_CONFIG.apiUrl });
