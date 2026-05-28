import type { RouteRecordStringComponent } from '@vben/types';

import pluginAuthEntries from 'virtual:xadmin-plugin-auth-entries';

import { useAccessStore } from '@vben/stores';

import { baseRequestClient, requestClient } from '#/api/request';

import type { DataApi } from '../system/data';
import { filterAuthEntryMenus } from './auth-entry-menus';
import { encryptPasswordFields, PASSWORD_PURPOSES } from './password-crypto';

const AUTH_ENTRY_KEY = 'xadmin-auth-entry';
const AUTH_ENTRY_TOKEN_PREFIX = 'xadmin-auth-token';
export const SYSTEM_ENTRY = 'system';
export const SYSTEM_LOGIN_PATH = '/auth/login';
export const SYSTEM_AUTH_BASE = '/system/auth';

export interface AuthEntryProfileConfig {
  description?: string;
  nicknameLabel?: string;
  signedLabel?: string;
  title?: string;
}

export interface AuthEntryConfig {
  authBase: string;
  entry: string;
  homePath: string;
  loginPath: string;
  menus: RouteRecordStringComponent[];
  name: string;
  permissionPrefixes: string[];
  profile?: AuthEntryProfileConfig;
  profilePath?: string;
  routePrefixes: string[];
  userModel?: string;
  userModelIncludes: string[];
}

const systemAuthEntry: AuthEntryConfig = {
  authBase: SYSTEM_AUTH_BASE,
  entry: SYSTEM_ENTRY,
  homePath: '/dashboard',
  loginPath: SYSTEM_LOGIN_PATH,
  menus: [],
  name: '系统后台',
  permissionPrefixes: ['system.'],
  profilePath: '/account/profile',
  routePrefixes: ['/dashboard', '/system', '/account/profile'],
  userModel: 'System\\Model\\SystemUser',
  userModelIncludes: ['SystemUser'],
};

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
  if (!entry || !authBase || !loginPath || !homePath || entry === SYSTEM_ENTRY) {
    return null;
  }

  return {
    authBase,
    entry,
    homePath,
    loginPath,
    menus: Array.isArray(raw?.menus) ? raw.menus : [],
    name: String(raw?.name || entry),
    permissionPrefixes: normalizeStringArray(raw?.permissionPrefixes),
    profile: raw?.profile && typeof raw.profile === 'object' ? raw.profile : undefined,
    profilePath: raw?.profilePath ? normalizePath(raw.profilePath) : undefined,
    routePrefixes: normalizeStringArray(raw?.routePrefixes).map(normalizePath),
    userModel: raw?.userModel ? String(raw.userModel) : undefined,
    userModelIncludes: normalizeStringArray(raw?.userModelIncludes),
  };
}

const pluginEntryConfigs = (Array.isArray(pluginAuthEntries) ? pluginAuthEntries : [])
  .map(normalizeAuthEntry)
  .filter(Boolean) as AuthEntryConfig[];

const authEntryConfigs: AuthEntryConfig[] = [systemAuthEntry, ...pluginEntryConfigs];

export function getAuthEntryConfigs() {
  return authEntryConfigs;
}

function getStoredAuthEntry() {
  if (typeof window === 'undefined') return SYSTEM_ENTRY;
  return window.localStorage.getItem(AUTH_ENTRY_KEY) || SYSTEM_ENTRY;
}

export function getAuthEntryConfig(entry?: string): AuthEntryConfig {
  const currentEntry = entry || getStoredAuthEntry();
  return authEntryConfigs.find((item) => item.entry === currentEntry) || systemAuthEntry;
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

export function isSystemAuthEntry(entry = getAuthEntry()) {
  return getAuthEntryConfig(entry).entry === SYSTEM_ENTRY;
}

export function isPluginAuthEntry(entry = getAuthEntry()) {
  return !isSystemAuthEntry(entry);
}

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
  return getAuthEntryConfig(entry).profilePath || systemAuthEntry.profilePath || '/account/profile';
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
  return pluginEntryConfigs.find((entry) => {
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
  // 避免插件前台账号和 System 后台账号在同一前端壳里互相污染。
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
  return getLoginEntryByPath(getCurrentClientPath()) || SYSTEM_ENTRY;
}

export namespace AuthApi {
  export interface LoginParams { password?: string; username?: string }
  export interface LoginResult { token: string; user: any; auth_user_model?: string }
  export interface StandardResponse<T = unknown> { path: string; info: string; code: number; data: T }
  export type RefreshTokenResult = StandardResponse<string>;
  export type UiMeta = DataApi.UiMeta;
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
    return requestClient.get<AuthApi.UiMeta>('/system/auth/ui-meta');
  },
  async login(data: AuthApi.LoginParams) {
    const entry = currentLoginEntry();
    const base = getAuthBase(entry);
    const payload = await encryptPasswordFields(
      data as Record<string, any>,
      { password: PASSWORD_PURPOSES.authLogin },
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
