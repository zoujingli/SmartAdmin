import type { RouteRecordStringComponent } from '@vben/types';

import pluginAuthEntries from 'virtual:xadmin-plugin-auth-entries';

import { useAccessStore } from '@vben/stores';

import { baseRequestClient, requestClient } from '#/api/request';

import { filterAuthEntryMenus } from './auth-entry-menus';
import { encryptPasswordFields, type PasswordPurposeKey } from './password-crypto';

const AUTH_ENTRY_KEY = 'xadmin-auth-entry';
const AUTH_ENTRY_TOKEN_PREFIX = 'xadmin-auth-token';

export interface AuthEntryProfileConfig {
  description?: string;
  nicknameLabel?: string;
  signedLabel?: string;
  title?: string;
}

export interface AuthEntryConfig {
  authBase: string;
  default?: boolean;
  entry: string;
  homePath: string;
  loginPath: string;
  menus: RouteRecordStringComponent[];
  name: string;
  permissionPrefixes: string[];
  passwordPurposes?: Partial<Record<PasswordPurposeKey, string>>;
  profile?: AuthEntryProfileConfig;
  profilePath?: string;
  routePrefixes: string[];
  userModel?: string;
  userModelIncludes: string[];
}

export interface AuthUiMeta {
  app_name: string;
  app_version: string;
  app_description: string;
  login_title: string;
  login_description: string;
  logo_url: string;
  logo_file_id: number;
  copyright: {
    enable: boolean;
    companyName: string;
    companySiteLink: string;
    date: string;
    icp: string;
    icpLink: string;
  };
}

function normalizePath(value: unknown): string {
  const path = `/${String(value || '').trim().replace(/^\/+/, '')}`;
  return path === '/' ? '/' : path.replace(/\/+$/, '');
}

function normalizeStringArray(value: unknown): string[] {
  return Array.isArray(value)
    ? value.map((item) => String(item || '').trim()).filter(Boolean)
    : [];
}

function normalizeAuthEntry(raw: any): AuthEntryConfig | null {
  const entry = String(raw?.entry || '').trim();
  const authBase = normalizePath(raw?.authBase);
  const loginPath = normalizePath(raw?.loginPath);
  const homePath = normalizePath(raw?.homePath);
  if (!entry || !authBase || !loginPath || !homePath) {
    return null;
  }

  return {
    authBase,
    default: raw?.default === true,
    entry,
    homePath,
    loginPath,
    menus: Array.isArray(raw?.menus) ? raw.menus : [],
    name: String(raw?.name || entry),
    permissionPrefixes: normalizeStringArray(raw?.permissionPrefixes),
    passwordPurposes: raw?.passwordPurposes && typeof raw.passwordPurposes === 'object'
      ? raw.passwordPurposes
      : undefined,
    profile: raw?.profile && typeof raw.profile === 'object' ? raw.profile : undefined,
    profilePath: raw?.profilePath ? normalizePath(raw.profilePath) : undefined,
    routePrefixes: normalizeStringArray(raw?.routePrefixes).map(normalizePath),
    userModel: raw?.userModel ? String(raw.userModel) : undefined,
    userModelIncludes: normalizeStringArray(raw?.userModelIncludes),
  };
}

const authEntryConfigs = (Array.isArray(pluginAuthEntries) ? pluginAuthEntries : [])
  .map(normalizeAuthEntry)
  .filter(Boolean) as AuthEntryConfig[];

const defaultAuthEntry = authEntryConfigs.find((entry) => entry.default) || authEntryConfigs[0];

export function getDefaultAuthEntry(): AuthEntryConfig {
  if (!defaultAuthEntry) {
    throw new Error('未找到默认认证入口，请检查插件 auth-entry.ts 配置');
  }

  return defaultAuthEntry;
}

export function getAuthEntryConfigs() {
  return authEntryConfigs;
}

function getStoredAuthEntry() {
  const fallbackEntry = getDefaultAuthEntry().entry;
  if (typeof window === 'undefined') return fallbackEntry;
  return window.localStorage.getItem(AUTH_ENTRY_KEY) || fallbackEntry;
}

export function getAuthEntryConfig(entry?: string): AuthEntryConfig {
  const currentEntry = entry || getStoredAuthEntry();
  return authEntryConfigs.find((item) => item.entry === currentEntry) || getDefaultAuthEntry();
}

export function getAuthEntry(): string {
  return getAuthEntryConfig(getStoredAuthEntry()).entry;
}

export function setAuthEntry(entry: string) {
  const normalized = getAuthEntryConfig(entry).entry;
  if (typeof window !== 'undefined') window.localStorage.setItem(AUTH_ENTRY_KEY, normalized);
}

export function clearAuthEntry() {
  if (typeof window !== 'undefined') window.localStorage.removeItem(AUTH_ENTRY_KEY);
}

export function isDefaultAuthEntry(entry = getAuthEntry()) {
  return getAuthEntryConfig(entry).entry === getDefaultAuthEntry().entry;
}

export function isSecondaryAuthEntry(entry = getAuthEntry()) {
  return !isDefaultAuthEntry(entry);
}

export const isPluginAuthEntry = isSecondaryAuthEntry;

export function getAuthLoginPath(entry = getAuthEntry()) {
  return getAuthEntryConfig(entry).loginPath;
}

export function getAuthBase(entry = getAuthEntry()) {
  return getAuthEntryConfig(entry).authBase;
}

export function getAuthHomePath(entry = getAuthEntry()) {
  return getAuthEntryConfig(entry).homePath;
}

export function getAuthProfilePath(entry = getAuthEntry()) {
  return getAuthEntryConfig(entry).profilePath || getDefaultAuthEntry().profilePath || '/account/profile';
}

export function getAuthPasswordPurpose(key: PasswordPurposeKey, entry = getAuthEntry()): string {
  const purpose = getAuthEntryConfig(entry).passwordPurposes?.[key];
  if (!purpose) {
    throw new Error(`当前认证入口未配置密码加密用途: ${key}`);
  }

  return purpose;
}

export function getLoginEntryByPath(path: string) {
  return authEntryConfigs.find((item) => item.loginPath === normalizePath(path))?.entry;
}

export function isAuthLoginPath(path: string) {
  return getLoginEntryByPath(path) !== undefined;
}

export function getCurrentClientPath() {
  if (typeof window === 'undefined') return '/';
  const hashPath = window.location.hash.replace(/^#/, '').split('?')[0] || '';
  // Hash 路由下浏览器 pathname 固定为 /，认证入口必须读取客户端路由路径；
  // 具体插件入口由插件 auth-entry.ts 声明，web 壳只做通用匹配。
  return normalizePath(hashPath.startsWith('/') ? hashPath : window.location.pathname);
}

export function getAuthEntryByRoutePath(path: string) {
  const normalized = normalizePath(path);
  return authEntryConfigs.find((entry) => {
    if (entry.loginPath === normalized || entry.profilePath === normalized) {
      return true;
    }

    return entry.routePrefixes.some((prefix) => normalized === prefix || normalized.startsWith(`${prefix}/`));
  })?.entry;
}

export function getAuthEntryByUserInfo(userInfo: any): string | undefined {
  const userModel = String(userInfo?.auth_user_model || '').trim();
  if (!userModel) return undefined;

  return authEntryConfigs.find((entry) => isUserModelForEntry(userModel, entry.entry))?.entry;
}

export function isUserInfoForAuthEntry(userInfo: any, entry = getAuthEntry()) {
  return getAuthEntryByUserInfo(userInfo) === getAuthEntryConfig(entry).entry;
}

export function routeBelongsToAuthEntry(node: any, entry = getAuthEntry()) {
  const config = getAuthEntryConfig(entry);
  const code = String(node?.code || node?.permission || '').trim();
  const routePath = normalizePath(node?.path || node?.route || '');
  const codeMatched = code && config.permissionPrefixes.some((prefix) => code.startsWith(prefix));
  const pathMatched = config.routePrefixes.some((prefix) => routePath === prefix || routePath.startsWith(`${prefix}/`));

  return Boolean(codeMatched || pathMatched);
}

function isUserModelForEntry(userModel: string, entry: string) {
  const config = getAuthEntryConfig(entry);
  if (config.userModel && userModel === config.userModel) {
    return true;
  }

  return config.userModelIncludes.some((needle) => userModel.includes(needle));
}

function entryTokenKey(entry: string) {
  return `${AUTH_ENTRY_TOKEN_PREFIX}:${entry}`;
}

function getStoredEntryToken(entry: string) {
  if (typeof window === 'undefined') return null;
  return window.localStorage.getItem(entryTokenKey(entry));
}

export function persistAuthToken(entry: string, token: string) {
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(entryTokenKey(getAuthEntryConfig(entry).entry), token);
  }
}

export function clearAuthToken(entry = getAuthEntry()) {
  if (typeof window !== 'undefined') {
    window.localStorage.removeItem(entryTokenKey(getAuthEntryConfig(entry).entry));
  }
}

export function activateAuthEntry(entry: string) {
  const previousEntry = getAuthEntry();
  const currentEntry = getAuthEntryConfig(entry).entry;
  setAuthEntry(currentEntry);

  // 每个认证入口独立保存 Token。入口切换时只恢复当前入口 Token，
  // 避免多个认证入口的账号在同一前端壳里互相污染。
  const accessStore = useAccessStore();
  const token = getStoredEntryToken(currentEntry);
  if (previousEntry !== currentEntry || accessStore.accessToken !== token) {
    accessStore.setAccessToken(token);
    accessStore.setAccessCodes([]);
    accessStore.setAccessMenus([]);
    accessStore.setAccessRoutes([]);
    accessStore.setIsAccessChecked(false);
  }
}

export function getAuthEntryMenus(entry = getAuthEntry()): RouteRecordStringComponent[] {
  const config = getAuthEntryConfig(entry);
  return filterAuthEntryMenus(config.menus, useAccessStore().accessCodes);
}

function currentLoginEntry() {
  return getLoginEntryByPath(getCurrentClientPath()) || getDefaultAuthEntry().entry;
}

export namespace AuthApi {
  export interface LoginParams { keep_login?: 0 | 1; password?: string; username?: string }
  export interface LoginResult { token: string; user: any; auth_user_model?: string }
  export interface StandardResponse<T = unknown> { path: string; info: string; code: number; data: T }
  export type RefreshTokenResult = StandardResponse<string>;
  export type UiMeta = AuthUiMeta;
}

function getAuthHeaders() {
  const accessToken = useAccessStore().accessToken;
  return accessToken ? { Authorization: `Bearer ${accessToken}` } : {};
}

export const coreAuthApiService = {
  getAccessCodes() {
    return requestClient.get<string[]>(`${getAuthBase()}/codes`);
  },
  getUiMeta() {
    return requestClient.get<AuthApi.UiMeta>(`${getAuthBase()}/ui-meta`);
  },
  async login(data: AuthApi.LoginParams) {
    const entry = currentLoginEntry();
    const base = getAuthBase(entry);
    const payload = await encryptPasswordFields(
      data as Record<string, any>,
      { password: getAuthPasswordPurpose('authLogin', entry) },
      { parametersUrl: `${base}/password-crypto` },
    );
    const result = await requestClient.post<AuthApi.LoginResult>(`${base}/login`, payload);
    setAuthEntry(entry);
    if (result?.token) persistAuthToken(entry, result.token);
    return result;
  },
  logout() {
    const entry = getAuthEntry();
    const base = getAuthBase(entry);
    clearAuthToken(entry);
    return baseRequestClient.post(`${base}/logout`, undefined, { headers: getAuthHeaders() });
  },
  refreshToken() {
    return baseRequestClient.post<AuthApi.RefreshTokenResult>(`${getAuthBase()}/refresh`, undefined, { headers: getAuthHeaders() });
  },
};
