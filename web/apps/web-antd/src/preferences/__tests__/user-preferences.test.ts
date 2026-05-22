import type { UserInfo } from '@vben/types';

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp } from 'vue';

import {
  clearCache,
  preferences,
  preferencesManager,
  resetPreferences,
  updatePreferences,
} from '@vben/preferences';

import {
  applyUiMetaPreferences,
  applySavedUiPreferences,
  buildPersistableUiPreferencesPayload,
  extractSavedUiPreferences,
  systemUiMeta,
} from '../user-preferences';
import { setupI18n } from '../../locales';

vi.mock('#/api', () => ({
  coreAuthApiService: {
    getUiMeta: vi.fn().mockResolvedValue({
      app_description: '',
      app_name: 'SmartAdmin',
      app_version: '1.0.0',
      login_description: '',
      login_title: '',
      logo_file_id: 0,
      logo_url: '',
      copyright: {
        companyName: 'SmartAdmin',
        companySiteLink: '',
        date: '2026',
        enable: true,
        icp: '',
        icpLink: '',
      },
    }),
  },
}));

function createUserInfo(profileExtra: Record<string, unknown>): UserInfo {
  return {
    avatar: '',
    desc: '',
    homePath: '/',
    profile: {
      extra: profileExtra,
    },
    realName: '',
    token: '',
    userId: '1',
    username: 'tester',
  };
}

describe('user ui preferences helpers', () => {
  beforeAll(async () => {
    await setupI18n(createApp({}));
  });

  beforeEach(() => {
    clearCache();
    resetPreferences();
  });

  it('builds diff payload and excludes runtime-only fields', () => {
    updatePreferences({
      app: {
        isMobile: true,
        locale: 'en-US',
        name: 'Ignored Name',
      },
      logo: {
        enable: false,
      },
      theme: {
        mode: 'light',
      },
    });

    expect(buildPersistableUiPreferencesPayload()).toEqual({
      app: {
        locale: 'en-US',
      },
      theme: {
        mode: 'light',
      },
    });
  });

  it('sanitizes saved preferences from user extra', () => {
    const userInfo = createUserInfo({
      ui_preferences: {
        app: {
          defaultAvatar: 'ignored',
          locale: 'en-US',
        },
        theme: {
          mode: 'light',
        },
      },
    });

    expect(extractSavedUiPreferences(userInfo)).toEqual({
      app: {
        locale: 'en-US',
      },
      theme: {
        mode: 'light',
      },
    });
  });

  it('resets local preferences when server has no saved payload', async () => {
    updatePreferences({
      app: {
        locale: 'en-US',
      },
      theme: {
        mode: 'light',
      },
    });

    await applySavedUiPreferences(createUserInfo({}));

    expect(preferences.app.locale).toBe('zh-CN');
    expect(preferences.theme.mode).toBe('dark');
  });

  it('applies saved preferences and overrides stale local cache', async () => {
    updatePreferences({
      app: {
        locale: 'zh-CN',
      },
      theme: {
        mode: 'light',
      },
      widget: {
        refresh: false,
      },
    });

    await applySavedUiPreferences(createUserInfo({
      ui_preferences: {
        app: {
          locale: 'en-US',
        },
        sidebar: {
          collapsed: true,
        },
      },
    }));

    expect(preferences.app.locale).toBe('en-US');
    expect(preferences.sidebar.collapsed).toBe(true);
    expect(preferences.theme.mode).toBe('dark');
    expect(preferences.widget.refresh).toBe(true);
  });

  it('applies system logo and restores builtin logo when cleared', () => {
    const initialLogo = preferencesManager.getInitialPreferences().logo.source;
    const uiMeta = {
      app_description: '',
      app_name: 'SmartAdmin',
      app_version: '1.0.0',
      login_description: '',
      login_title: '',
      logo_file_id: 1,
      logo_url: 'https://example.com/logo.png',
      copyright: {
        companyName: 'SmartAdmin',
        companySiteLink: '',
        date: '2026',
        enable: true,
        icp: '',
        icpLink: '',
      },
    };

    applyUiMetaPreferences(uiMeta);
    expect(preferences.logo.source).toBe('https://example.com/logo.png');
    expect(systemUiMeta.appVersion).toBe('1.0.0');

    applyUiMetaPreferences({
      ...uiMeta,
      logo_file_id: 0,
      logo_url: '',
    });
    expect(preferences.logo.source).toBe(initialLogo);
  });

  it('syncs copyright switch to footer visibility', () => {
    const uiMeta = {
      app_description: '',
      app_name: 'SmartAdmin',
      app_version: '1.0.0',
      login_description: '',
      login_title: '',
      logo_file_id: 0,
      logo_url: '',
      copyright: {
        companyName: 'SmartAdmin',
        companySiteLink: '',
        date: '2026',
        enable: true,
        icp: '',
        icpLink: '',
      },
    };

    applyUiMetaPreferences(uiMeta);
    expect(preferences.copyright.enable).toBe(true);
    expect(preferences.footer.enable).toBe(true);

    applyUiMetaPreferences({
      ...uiMeta,
      copyright: {
        ...uiMeta.copyright,
        enable: false,
      },
    });
    expect(preferences.copyright.enable).toBe(false);
    expect(preferences.footer.enable).toBe(false);
  });
});
