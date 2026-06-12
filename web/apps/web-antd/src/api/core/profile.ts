import type { Preferences } from '@vben/preferences';
import type { DeepPartial, UserInfo } from '@vben/types';

import { requestClient } from '#/api/request';

import { getAuthBase, getAuthPasswordPurpose } from './auth';
import { encryptPasswordFields } from './password-crypto';

export interface UpdateProfileBody {
  nickname?: string;
  email?: string;
  phone?: string;
  avatar?: string;
  signed?: string;
}

export interface UpdatePreferencesBody {
  ui_preferences: DeepPartial<Preferences>;
}

export const profileApiService = {
  async changePassword(data: {
    new_password: string;
    old_password: string;
  }) {
    const payload = await encryptPasswordFields(data, {
      old_password: getAuthPasswordPurpose('authChangeOld'),
      new_password: getAuthPasswordPurpose('authChangeNew'),
    }, { parametersUrl: `${authProfileBase()}/password-crypto` });

    return requestClient.put(`${authProfileBase()}/password`, payload);
  },
  updateProfile(data: UpdateProfileBody) {
    return requestClient.put<UserInfo>(`${authProfileBase()}/profile`, data);
  },
  savePreferences(data: UpdatePreferencesBody) {
    return requestClient.put<UserInfo>(`${authProfileBase()}/preferences`, data);
  },
};

function authProfileBase() {
  return getAuthBase();
}
