/**
 * API 统一配置
 */

// ==================== 环境变量配置 ====================

/**
 * 获取环境变量配置
 */
/**
 * 运行时全局配置（生产环境由 PHP _app.config.js 动态注入）
 */
function getRuntimeConfig(): Record<string, string> {
  if (
    typeof window !== 'undefined' &&
    window._VBEN_ADMIN_PRO_APP_CONF_ &&
    import.meta.env.PROD
  ) {
    return window._VBEN_ADMIN_PRO_APP_CONF_ as unknown as Record<
      string,
      string
    >;
  }
  return {};
}

const getEnvConfig = () => {
  const runtime = getRuntimeConfig();

  const apiUrl =
    runtime.VITE_GLOB_API_URL || import.meta.env.VITE_GLOB_API_URL || '/api';

  return {
    isDev: import.meta.env.DEV,
    isProd: import.meta.env.PROD,
    mode: import.meta.env.MODE,

    apiUrl,

    proxyEnabled: apiUrl.startsWith('/'),
    backendUrl: import.meta.env.VITE_BACKEND_URL || 'http://localhost:9501',

    mockEnabled: import.meta.env.VITE_NITRO_MOCK === 'true',

    port: Number(import.meta.env.VITE_PORT) || 5666,
    base: import.meta.env.VITE_BASE || '/',

    appTitle:
      runtime.APP_TITLE || import.meta.env.VITE_APP_TITLE || 'SmartAdmin',
    appNamespace: import.meta.env.VITE_APP_NAMESPACE || 'smart_admin-web-antd',
  };
};

// 导出环境配置
export const ENV_CONFIG = getEnvConfig();

// ==================== API 基础配置 ====================

/**
 * API 响应状态码
 * 项目后端 HTTP status 固定 200，业务状态只读取 body.code。
 */
export const API_CODES = {
  // 成功状态码
  SUCCESS: 200,
  CREATED: 201,
  ACCEPTED: 202,
  NO_CONTENT: 204,
  
  // 客户端错误
  BAD_REQUEST: 400,
  UNAUTHORIZED: 401,
  FORBIDDEN: 403,
  NOT_FOUND: 404,
  METHOD_NOT_ALLOWED: 405,
  CONFLICT: 409,
  UNPROCESSABLE_ENTITY: 422,
  TOO_MANY_REQUESTS: 429,
  
  // 服务器错误
  INTERNAL_SERVER_ERROR: 500,
  BAD_GATEWAY: 502,
  SERVICE_UNAVAILABLE: 503,
  GATEWAY_TIMEOUT: 504,
} as const;

/**
 * API 响应字段映射
 */
export const API_FIELDS = {
  CODE: 'code',
  DATA: 'data',
  MESSAGE: 'info',
  ERROR: 'error',
} as const;

/**
 * 分页参数配置
 */
export const PAGINATION_CONFIG = {
  DEFAULT_PAGE: 1,
  DEFAULT_PAGE_SIZE: 10,
  MAX_PAGE_SIZE: 100,
  PAGE_SIZE_OPTIONS: ['10', '20', '50', '100'],
} as const;

/**
 * 请求配置
 */
export const REQUEST_CONFIG = {
  // 超时配置
  TIMEOUT: 30000, // 30秒
  UPLOAD_TIMEOUT: 60000, // 上传超时 60秒
  
  // 重试配置
  RETRY_COUNT: 3, // 重试次数
  RETRY_DELAY: 1000, // 重试延迟(ms)
  RETRY_CONDITIONS: [408, 429, 500, 502, 503, 504], // 需要重试的状态码
  
  // 请求头配置
  DEFAULT_HEADERS: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  
  // 缓存配置
  CACHE_ENABLED: false, // 是否启用缓存
  CACHE_TTL: 300000, // 缓存时间 5分钟
  
  // 并发配置
  MAX_CONCURRENT: 10, // 最大并发请求数
} as const;

/**
 * 错误消息配置
 */
export const ERROR_MESSAGES = {
  // 网络相关错误
  NETWORK_ERROR: '网络连接失败，请检查网络设置',
  TIMEOUT_ERROR: '请求超时，请稍后重试',
  CONNECTION_ERROR: '连接失败，请检查网络连接',
  
  // HTTP 状态码错误
  [API_CODES.BAD_REQUEST]: '请求参数错误',
  [API_CODES.UNAUTHORIZED]: '登录已过期，请重新登录',
  [API_CODES.FORBIDDEN]: '没有权限访问此资源',
  [API_CODES.NOT_FOUND]: '页面或接口不存在',
  [API_CODES.METHOD_NOT_ALLOWED]: '请求方法不被允许',
  [API_CODES.CONFLICT]: '数据冲突，请检查后重试',
  [API_CODES.UNPROCESSABLE_ENTITY]: '请求数据格式错误',
  [API_CODES.TOO_MANY_REQUESTS]: '请求过于频繁，请稍后重试',
  
  // 服务器错误
  [API_CODES.INTERNAL_SERVER_ERROR]: '业务处理失败或数据不存在',
  [API_CODES.BAD_GATEWAY]: '网关错误',
  [API_CODES.SERVICE_UNAVAILABLE]: '服务暂时不可用',
  [API_CODES.GATEWAY_TIMEOUT]: '网关超时',
  
  // 通用错误
  UNKNOWN_ERROR: '未知错误，请联系管理员',
  VALIDATION_ERROR: '数据验证失败',
  BUSINESS_ERROR: '业务处理失败',
} as const;

/**
 * 日志配置
 */
export const LOG_CONFIG = {
  ENABLE_REQUEST_LOG: ENV_CONFIG.isDev, // 开发环境启用请求日志
  ENABLE_RESPONSE_LOG: ENV_CONFIG.isDev, // 开发环境启用响应日志
  ENABLE_ERROR_LOG: true, // 始终启用错误日志
} as const;

// ==================== 代理配置 ====================

/**
 * 代理配置
 */
export const PROXY_CONFIG = {
  // 是否启用代理模式
  enabled: ENV_CONFIG.proxyEnabled,
  
  // 代理目标地址
  target: ENV_CONFIG.backendUrl,
  
  // 代理路径重写规则
  rewrite: (path: string) => {
    if (ENV_CONFIG.proxyEnabled) {
      // 代理模式：移除 /api 前缀
      return path.replace(/^\/api/, '');
    }
    return path;
  },
  
  // 代理配置选项
  options: {
    changeOrigin: true,
    ws: true,
    secure: false,
  },
} as const;

// ==================== API 路径配置 ====================

/**
 * API 路径配置 - 只管理基础路径
 */
export const API_PATHS = {
  // 基础路径
  BASE: ENV_CONFIG.apiUrl,
} as const;

// ==================== 请求配置 ====================

/**
 * 请求配置
 */
export const REQUEST_CONFIG_FINAL = {
  // 基础URL
  baseURL: ENV_CONFIG.apiUrl,
  
  // 超时时间
  timeout: REQUEST_CONFIG.TIMEOUT,
  
  // 重试配置
  retry: {
    count: REQUEST_CONFIG.RETRY_COUNT,
    delay: REQUEST_CONFIG.RETRY_DELAY,
  },
  
  // 响应配置
  response: {
    returnData: true, // 是否直接返回data字段
    successCode: API_CODES.SUCCESS,
    codeField: API_FIELDS.CODE,
    dataField: API_FIELDS.DATA,
    messageField: API_FIELDS.MESSAGE,
  },
} as const;

// ==================== 调试配置 ====================

/**
 * 调试配置
 */
export const DEBUG_CONFIG = {
  // 是否启用调试模式
  enabled: ENV_CONFIG.isDev,
  
  // 是否显示API请求日志
  showApiLogs: LOG_CONFIG.ENABLE_REQUEST_LOG,
  
  // 是否显示代理日志
  showProxyLogs: ENV_CONFIG.isDev,
  
  // 是否显示错误详情
  showErrorDetails: ENV_CONFIG.isDev,
} as const;

// ==================== 导出配置对象 ====================

/**
 * 完整的API配置对象
 */
export const API_CONFIG = {
  env: ENV_CONFIG,
  codes: API_CODES,
  fields: API_FIELDS,
  pagination: PAGINATION_CONFIG,
  request: REQUEST_CONFIG_FINAL,
  errors: ERROR_MESSAGES,
  logs: LOG_CONFIG,
  proxy: PROXY_CONFIG,
  debug: DEBUG_CONFIG,
} as const;

// 默认导出
export default API_CONFIG;
