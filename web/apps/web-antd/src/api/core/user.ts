import type { UserInfo } from '@vben/types';

import { requestClient } from '#/api/request';

import { getAuthBase } from './auth';

export const coreUserApiService = {
  getUserInfo() {
    return requestClient.post<UserInfo>(`${getAuthBase()}/profile`);
  },
};
