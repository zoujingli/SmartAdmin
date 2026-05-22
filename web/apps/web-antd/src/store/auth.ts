import type { Recordable, UserInfo } from '@vben/types';

import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { preferences } from '@vben/preferences';
import { resetAllStores, useAccessStore, useUserStore } from '@vben/stores';

import { message, notification } from 'ant-design-vue';
import { defineStore } from 'pinia';

import {
  coreAuthApiService,
  coreUserApiService,
  getAuthLoginPath,
  isAuthLoginPath,
} from '#/api';
import { $t } from '#/locales';
import { applySavedUiPreferences } from '#/preferences/user-preferences';

export const useAuthStore = defineStore('auth', () => {
  const accessStore = useAccessStore();
  const userStore = useUserStore();
  const router = useRouter();

  const loginLoading = ref(false);

  /**
   * 异步处理登录操作
   * Asynchronously handle the login process
   * @param params 登录表单数据
   */
  async function authLogin(
    params: Recordable<any>,
    onSuccess?: () => Promise<void> | void,
  ) {
    // 异步处理用户登录操作并获取 accessToken
    let userInfo: null | UserInfo = null;
    try {
      loginLoading.value = true;
      const loginResult = await coreAuthApiService.login(params);
      const token = loginResult?.token;

      // 如果成功获取到 token
      if (token) {
        accessStore.setAccessToken(token);
        // 多认证入口共用前端壳，但菜单和路由必须按当前入口重新生成；
        // 否则入口切换时会沿用内存中的上一套菜单，出现跨入口菜单污染。
        accessStore.setAccessMenus([]);
        accessStore.setAccessRoutes([]);
        accessStore.setAccessCodes([]);
        accessStore.setIsAccessChecked(false);

        // 先获取用户信息，确保 token 生效
        const fetchUserInfoResult = await fetchUserInfo();

        // 然后获取权限代码
        const accessCodes = await coreAuthApiService.getAccessCodes();

        userInfo = fetchUserInfoResult;

        userStore.setUserInfo(userInfo);
        accessStore.setAccessCodes(Array.isArray(accessCodes) ? accessCodes : []);

        if (accessStore.loginExpired) {
          accessStore.setLoginExpired(false);
        } else {
          onSuccess
            ? await onSuccess?.()
            : await router.push(
                userInfo.homePath || preferences.app.defaultHomePath,
              );
        }

        if (userInfo?.realName) {
          notification.success({
            description: `${$t('authentication.loginSuccessDesc')}:${userInfo?.realName}`,
            duration: 3,
            message: $t('authentication.loginSuccess'),
          });
        }
      }
    } catch (error: any) {
      // 接口错误由全局拦截器提示；密码本地加密失败不会进入拦截器，需要在登录页直接提示部署/浏览器约束。
      if (error?.name === 'PasswordCryptoClientError' && error?.message) {
        message.error(error.message);
      }
      console.error('Login error:', error);
    } finally {
      loginLoading.value = false;
    }

    return {
      userInfo,
    };
  }

  async function logout(redirect: boolean = true) {
    const loginPath = getAuthLoginPath();
    const currentRoute = router.currentRoute.value;
    const shouldCarryRedirect = redirect
      && currentRoute.path !== loginPath
      && !isAuthLoginPath(currentRoute.path)
      && !currentRoute.path.startsWith('/auth/');
    try {
      await coreAuthApiService.logout();
    } catch {
      // 不做任何处理
    }
    resetAllStores();
    accessStore.setLoginExpired(false);

    // 回登录页带上当前路由地址
    await router.replace({
      path: loginPath,
      query: shouldCarryRedirect
        ? {
            // 登录页和认证页不能再次作为 redirect 目标，否则入口切换或登录失效时会形成多层嵌套跳转参数。
            redirect: encodeURIComponent(currentRoute.fullPath),
          }
        : {},
    });
  }

  async function fetchUserInfo() {
    let userInfo: null | UserInfo = null;

    // 添加重试机制，最多重试3次
    let retryCount = 0;
    const maxRetries = 3;

    while (retryCount < maxRetries) {
      try {
        userInfo = await coreUserApiService.getUserInfo();
        if (userInfo) {
          userStore.setUserInfo(userInfo);
          await applySavedUiPreferences(userInfo);
          return userInfo;
        }
      } catch (error) {
        console.warn(`获取用户信息失败，重试 ${retryCount + 1}/${maxRetries}:`, error);
      }

      retryCount++;
      if (retryCount < maxRetries) {
        // 等待 100ms 后重试
        await new Promise(resolve => setTimeout(resolve, 100));
      }
    }

    throw new Error('获取用户信息失败，请重新登录');
  }

  function $reset() {
    loginLoading.value = false;
  }

  return {
    $reset,
    authLogin,
    fetchUserInfo,
    loginLoading,
    logout,
  };
});
