import type { SupportedLanguagesType } from '@vben/locales';
import type { Preferences } from '@vben/preferences';
import type { DeepPartial, UserInfo } from '@vben/types';
import type { DataApi } from '#/api';

import { reactive, readonly } from 'vue';

import { loadLocaleMessages } from '@vben/locales';
import {
  preferences,
  preferencesManager,
  resetPreferences,
  updatePreferences,
} from '@vben/preferences';
import { SUPPORT_LANGUAGES } from '@vben/constants';
import { diff } from '@vben/utils';

import { coreAuthApiService } from '#/api';

interface PreferenceSchema {
  [key: string]: PreferenceSchema | true;
}

interface SystemUiMetaState {
  appDescription: string;
  appVersion: string;
  loginDescription: string;
  loginTitle: string;
}

const systemUiMetaState = reactive<SystemUiMetaState>({
  appDescription: '',
  appVersion: '',
  loginDescription: '',
  loginTitle: '',
});

/**
 * 后台系统参数中的运行期元信息，不写入用户偏好缓存。
 *
 * app_version 只用于 Logo 等品牌展示，避免和 app.name 混在一起后影响浏览器标题、
 * 本地偏好差异计算和用户可保存的界面配置。
 */
const systemUiMeta = readonly(systemUiMetaState);

const PERSISTABLE_UI_PREFERENCE_SCHEMA = {
  app: {
    locale: true,
    dynamicTitle: true,
    layout: true,
    colorGrayMode: true,
    colorWeakMode: true,
    contentCompact: true,
    watermark: true,
    watermarkContent: true,
    enableStickyPreferencesNavigationBar: true,
    preferencesButtonPosition: true,
  },
  breadcrumb: {
    enable: true,
    hideOnlyOne: true,
    showHome: true,
    showIcon: true,
    styleType: true,
  },
  footer: {
    enable: true,
    fixed: true,
  },
  header: {
    enable: true,
    menuAlign: true,
    mode: true,
  },
  navigation: {
    accordion: true,
    split: true,
    styleType: true,
  },
  shortcutKeys: {
    enable: true,
    globalLockScreen: true,
    globalLogout: true,
    globalSearch: true,
  },
  sidebar: {
    autoActivateChild: true,
    collapsed: true,
    collapsedButton: true,
    collapsedShowTitle: true,
    enable: true,
    expandOnHover: true,
    fixedButton: true,
    width: true,
  },
  tabbar: {
    draggable: true,
    enable: true,
    maxCount: true,
    middleClickToClose: true,
    persist: true,
    showIcon: true,
    showMaximize: true,
    showMore: true,
    styleType: true,
    visitHistory: true,
    wheelable: true,
  },
  theme: {
    builtinType: true,
    colorPrimary: true,
    fontSize: true,
    mode: true,
    radius: true,
    semiDarkHeader: true,
    semiDarkSidebar: true,
  },
  transition: {
    enable: true,
    loading: true,
    name: true,
    progress: true,
  },
  widget: {
    fullscreen: true,
    globalSearch: true,
    languageToggle: true,
    lockScreen: true,
    notification: true,
    refresh: true,
    sidebarToggle: true,
    themeToggle: true,
  },
} as const satisfies PreferenceSchema;

const SUPPORTED_LOCALE_VALUES = new Set(
  SUPPORT_LANGUAGES.map((item) => item.value),
);

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return Object.prototype.toString.call(value) === '[object Object]';
}

function sanitizeLeafValue(value: unknown, template: unknown) {
  switch (typeof template) {
    case 'boolean': {
      return typeof value === 'boolean' ? value : undefined;
    }
    case 'number': {
      return typeof value === 'number' && Number.isFinite(value)
        ? value
        : undefined;
    }
    case 'string': {
      return typeof value === 'string' ? value : undefined;
    }
    default: {
      return undefined;
    }
  }
}

function sanitizeBySchema(
  value: unknown,
  template: unknown,
  schema: PreferenceSchema,
): Record<string, unknown> | undefined {
  if (!isPlainObject(value) || !isPlainObject(template)) {
    return undefined;
  }

  const result: Record<string, unknown> = {};

  for (const [key, definition] of Object.entries(schema)) {
    const nextValue = value[key];
    if (nextValue === undefined) {
      continue;
    }

    const templateValue = template[key];
    if (definition === true) {
      const leafValue = sanitizeLeafValue(nextValue, templateValue);
      if (leafValue !== undefined) {
        result[key] = leafValue;
      }
      continue;
    }

    const nestedValue = sanitizeBySchema(nextValue, templateValue, definition);
    if (nestedValue && Object.keys(nestedValue).length > 0) {
      result[key] = nestedValue;
    }
  }

  return Object.keys(result).length > 0 ? result : undefined;
}

function sanitizeUiPreferences(value: unknown): DeepPartial<Preferences> {
  const initialPreferences = preferencesManager.getInitialPreferences();

  const sanitized = (sanitizeBySchema(
    value,
    initialPreferences,
    PERSISTABLE_UI_PREFERENCE_SCHEMA,
  ) ?? {}) as DeepPartial<Preferences>;

  // locale 会影响语言包动态加载，保存和回放时必须限定在已注册语言内。
  if (
    sanitized.app?.locale &&
    !SUPPORTED_LOCALE_VALUES.has(sanitized.app.locale)
  ) {
    delete sanitized.app.locale;
  }

  return sanitized;
}

function extractSavedUiPreferences(
  userInfo: null | UserInfo,
): DeepPartial<Preferences> {
  const extra = userInfo?.profile?.extra;
  if (!isPlainObject(extra)) {
    return {};
  }

  return sanitizeUiPreferences(extra.ui_preferences);
}

function buildPersistableUiPreferencesPayload(): DeepPartial<Preferences> {
  const initialPreferences = sanitizeUiPreferences(
    preferencesManager.getInitialPreferences(),
  );
  const currentPreferences = sanitizeUiPreferences(preferences);

  return (diff(
    initialPreferences as Record<string, unknown>,
    currentPreferences as Record<string, unknown>,
  ) ?? {}) as DeepPartial<Preferences>;
}

function normalizeMetaText(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

function applyUiMetaPreferences(uiMeta: null | undefined | DataApi.UiMeta) {
  if (!uiMeta) {
    return;
  }

  systemUiMetaState.appDescription = normalizeMetaText(uiMeta.app_description);
  systemUiMetaState.appVersion = normalizeMetaText(uiMeta.app_version);
  systemUiMetaState.loginDescription = normalizeMetaText(
    uiMeta.login_description,
  );
  systemUiMetaState.loginTitle = normalizeMetaText(uiMeta.login_title);

  const copyrightEnable = uiMeta.copyright?.enable ?? true;
  const payload: DeepPartial<Preferences> = {
    app: {
      name: uiMeta.app_name || preferences.app.name,
    },
    copyright: {
      companyName: uiMeta.copyright?.companyName || '',
      companySiteLink: uiMeta.copyright?.companySiteLink || '',
      date: uiMeta.copyright?.date || '',
      enable: copyrightEnable,
      icp: uiMeta.copyright?.icp || '',
      icpLink: uiMeta.copyright?.icpLink || '',
    },
    footer: {
      // 系统参数里的版权开关是全局展示开关；同步页脚可见性，避免主后台启用版权但页脚默认隐藏。
      enable: copyrightEnable,
    },
    logo: {
      source: uiMeta.logo_url || preferencesManager.getInitialPreferences().logo.source,
    },
  };

  updatePreferences(payload);
}

async function applySavedUiPreferences(userInfo: null | UserInfo) {
  resetPreferences();
  await loadLocaleMessages(preferences.app.locale as SupportedLanguagesType);

  try {
    // UI 元信息属于登录前也需要读取的品牌配置；多认证入口共用前端壳，
    // 登录后仍必须走公开认证入口，不能调用只面向特定后台鉴权链路的系统数据接口。
    applyUiMetaPreferences(await coreAuthApiService.getUiMeta());
  } catch {
    // 后台元信息读取失败时使用内置配置兜底。
  }

  const savedPreferences = extractSavedUiPreferences(userInfo);
  if (Object.keys(savedPreferences).length === 0) {
    return savedPreferences;
  }

  updatePreferences(savedPreferences);

  const locale = savedPreferences.app?.locale;
  if (locale) {
    await loadLocaleMessages(locale as SupportedLanguagesType);
  }

  return savedPreferences;
}

export {
  applyUiMetaPreferences,
  applySavedUiPreferences,
  buildPersistableUiPreferencesPayload,
  extractSavedUiPreferences,
  systemUiMeta,
};
