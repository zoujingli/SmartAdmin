type DingTalkAuthCodeResult = {
  authCode?: string;
  code?: string;
};

type DingTalkAuthCodeOptions = {
  corpID?: string;
  corpId: string;
  fail?: (error: unknown) => void;
  onFail?: (error: unknown) => void;
  onSuccess?: (result: DingTalkAuthCodeResult) => void;
  success?: (result: DingTalkAuthCodeResult) => void;
};

type DingTalkSdk = {
  env?: {
    platform?: string;
    platformSub?: string;
  };
  error?: (callback: (error: unknown) => void) => void;
  getAuthCode?: (options: DingTalkAuthCodeOptions) => unknown;
  ready?: (callback: () => void) => void;
  requestAuthCode?: (options: DingTalkAuthCodeOptions) => unknown;
  runtime?: {
    permission?: {
      requestAuthCode?: (options: DingTalkAuthCodeOptions) => unknown;
    };
  };
};

type DingTalkScriptName = 'open' | 'pc' | 'legacy';

type AuthCodeRequestApi = {
  call: (options: DingTalkAuthCodeOptions) => unknown;
  name: string;
  needsReady: boolean;
};

type TimeoutOptions = {
  timeoutMs?: number;
};

const DINGTALK_JSAPI_URL =
  'https://g.alicdn.com/dingding/open-develop/1.9.0/dingtalk.js';
const DINGTALK_OPEN_JSAPI_URL =
  'https://g.alicdn.com/dingding/dingtalk-jsapi/3.2.0/dingtalk.open.js';
const DINGTALK_PC_JSAPI_URL =
  'https://g.alicdn.com/dingding/dingtalk-pc-api/2.9.0/index.js';
const DINGTALK_TIMEOUT_MS = 15_000;
const DINGTALK_READY_GRACE_MS = 1_200;
const DINGTALK_PC_API_FALLBACK_MS = 2_500;

let scriptLoading: Partial<Record<DingTalkScriptName, Promise<void> | null>> = {};

function dingTalkSdk(): DingTalkSdk | null {
  return typeof window === 'undefined' ? null : ((window as any).dd ?? null);
}

function dingTalkPcSdk(): DingTalkSdk | null {
  return typeof window === 'undefined'
    ? null
    : ((window as any).DingTalkPC ?? null);
}

function currentUserAgent() {
  return typeof navigator === 'undefined' ? '' : navigator.userAgent || '';
}

function isDingTalkRuntime(dd: DingTalkSdk | null) {
  const platform = String(dd?.env?.platform || '').trim();
  if (platform) {
    return !/^not_?in_?dingtalk$/i.test(platform);
  }

  return /DingTalk/i.test(currentUserAgent());
}

function isDingTalkPcRuntime() {
  const platform = String(dingTalkSdk()?.env?.platform || '').trim().toLowerCase();
  if (platform === 'pc') {
    return true;
  }

  const ua = currentUserAgent();
  return /DingTalk/i.test(ua) && /webDt\/PC|nw|dingtalk-win|macOS/i.test(ua);
}

function createError(error: unknown, fallback: string): Error {
  if (error instanceof Error) {
    return error;
  }

  const source = error as Record<string, unknown> | null | undefined;
  const message = String(
    source?.errorMessage ??
      source?.errmsg ??
      source?.message ??
      source?.error ??
      source?.code ??
      '',
  ).trim();

  return new Error(message ? `${fallback}：${message}` : fallback);
}

function diagnosticSuffix(dd: DingTalkSdk | null, apiNames: string[] = []) {
  if (typeof window === 'undefined') {
    return '';
  }

  const pc = dingTalkPcSdk();
  const platform = String(dd?.env?.platform || '').trim() || 'unknown';
  const platformSub = String(dd?.env?.platformSub || '').trim();
  const features = [
    typeof dd?.getAuthCode === 'function' ? 'dd.getAuthCode' : '',
    typeof dd?.requestAuthCode === 'function' ? 'dd.requestAuthCode' : '',
    typeof dd?.runtime?.permission?.requestAuthCode === 'function'
      ? 'dd.runtime.permission.requestAuthCode'
      : '',
    typeof pc?.runtime?.permission?.requestAuthCode === 'function'
      ? 'DingTalkPC.runtime.permission.requestAuthCode'
      : '',
  ].filter(Boolean);
  const uaType = isDingTalkPcRuntime() ? 'pc' : 'mobile';
  const parts = [
    `环境=${uaType}/${platform}${platformSub ? `-${platformSub}` : ''}`,
    `接口=${apiNames.length > 0 ? apiNames.join('|') : features.join('|') || 'none'}`,
  ];

  return `（${parts.join('，')}）`;
}

function withTimeout<T>(
  promise: Promise<T>,
  timeoutMs: number,
  timeoutMessage: string,
  onTimeout?: () => void,
) {
  let timer: number | undefined;

  const timeout = new Promise<never>((_, reject) => {
    timer = window.setTimeout(() => {
      onTimeout?.();
      reject(new Error(timeoutMessage));
    }, timeoutMs);
  });

  return Promise.race([promise, timeout]).finally(() => {
    if (timer) {
      window.clearTimeout(timer);
    }
  });
}

function scriptUrl(name: DingTalkScriptName) {
  if (name === 'open') {
    return DINGTALK_OPEN_JSAPI_URL;
  }
  if (name === 'pc') {
    return DINGTALK_PC_JSAPI_URL;
  }
  return DINGTALK_JSAPI_URL;
}

function isScriptAvailable(name: DingTalkScriptName) {
  if (name === 'pc') {
    return Boolean(dingTalkPcSdk());
  }
  return Boolean(dingTalkSdk());
}

function loadDingTalkScript(name: DingTalkScriptName, timeoutMs: number) {
  if (isScriptAvailable(name)) {
    return Promise.resolve();
  }

  const url = scriptUrl(name);
  const existed = document.querySelector<HTMLScriptElement>(
    `script[src="${url}"]`,
  );
  if (existed) {
    if (scriptLoading[name]) {
      return withTimeout(scriptLoading[name], timeoutMs, '钉钉 JSAPI 加载超时，请检查网络或钉钉客户端环境');
    }
    const promise =
      existed.dataset.loaded === 'true'
        ? Promise.resolve()
        : new Promise<void>((resolve, reject) => {
            existed.addEventListener('load', () => resolve(), { once: true });
            existed.addEventListener(
              'error',
              () => reject(new Error('钉钉 JSAPI 加载失败')),
              { once: true },
            );
          });

    return withTimeout(promise, timeoutMs, '钉钉 JSAPI 加载超时，请检查网络或钉钉客户端环境');
  }

  const script = document.createElement('script');
  script.src = url;
  script.async = true;

  scriptLoading[name] = new Promise<void>((resolve, reject) => {
    // 钉钉 SDK 加载失败或超时时允许用户重试，避免缓存失败 Promise 后入口永久不可恢复。
    const clearLoading = () => {
      scriptLoading[name] = null;
    };

    script.addEventListener('load', () => {
      script.dataset.loaded = 'true';
      clearLoading();
      resolve();
    }, { once: true });
    script.addEventListener('error', () => {
      script.remove();
      clearLoading();
      reject(new Error('钉钉 JSAPI 加载失败'));
    }, { once: true });
    document.head.append(script);
  });

  return withTimeout(scriptLoading[name], timeoutMs, '钉钉 JSAPI 加载超时，请检查网络或钉钉客户端环境', () => {
    script?.remove();
    scriptLoading[name] = null;
  });
}

export function isDingTalkClient() {
  return isDingTalkRuntime(dingTalkSdk());
}

export function hasDingTalkSdk() {
  return Boolean(dingTalkSdk() || dingTalkPcSdk());
}

export async function resolveDingTalkSdk(options: TimeoutOptions = {}) {
  const current = dingTalkSdk() || dingTalkPcSdk();
  if (current) {
    return current;
  }

  const timeoutMs = options.timeoutMs ?? DINGTALK_TIMEOUT_MS;
  if (isDingTalkPcRuntime()) {
    let loadError: unknown = null;
    await loadDingTalkScript('open', timeoutMs).catch((error) => {
      loadError = error;
    });
    await loadDingTalkScript('pc', timeoutMs).catch((error) => {
      loadError = error;
    });
    if (!dingTalkSdk() && !dingTalkPcSdk() && loadError) {
      throw loadError;
    }
  } else {
    await loadDingTalkScript('open', timeoutMs).catch(() =>
      loadDingTalkScript('legacy', timeoutMs),
    );
  }

  return dingTalkSdk() || dingTalkPcSdk();
}

function buildAuthCodeApis(dd: DingTalkSdk): AuthCodeRequestApi[] {
  const apis: AuthCodeRequestApi[] = [];
  const usedSources = new Set<unknown>();
  const addApi = (
    name: string,
    source: unknown,
    context: unknown,
    needsReady: boolean,
  ) => {
    if (typeof source !== 'function') {
      return;
    }
    if (apis.some((api) => api.name === name) || usedSources.has(source)) {
      return;
    }
    usedSources.add(source);
    apis.push({
      name,
      needsReady,
      call: (options) => (source as (options: DingTalkAuthCodeOptions) => unknown).call(context, options),
    });
  };

  const pc = dingTalkPcSdk();
  const permission = dd.runtime?.permission;
  const pcPermission = pc?.runtime?.permission;

  if (isDingTalkPcRuntime()) {
    addApi(
      'DingTalkPC.runtime.permission.requestAuthCode',
      pcPermission?.requestAuthCode,
      pcPermission,
      false,
    );
  }

  // 新版开放 JSAPI 将 getAuthCode 映射为 runtime.permission.requestAuthCode，明确支持 PC 和移动端。
  addApi('dd.getAuthCode', dd.getAuthCode, dd, true);
  addApi('dd.requestAuthCode', dd.requestAuthCode, dd, true);
  addApi('dd.runtime.permission.requestAuthCode', permission?.requestAuthCode, permission, !isDingTalkPcRuntime());
  if (!isDingTalkPcRuntime()) {
    addApi(
      'DingTalkPC.runtime.permission.requestAuthCode',
      pcPermission?.requestAuthCode,
      pcPermission,
      false,
    );
  }

  return apis;
}

export function requestDingTalkAuthCode(
  dd: DingTalkSdk,
  corpId: string,
  options: TimeoutOptions = {},
) {
  return new Promise<string>((resolve, reject) => {
    if (!isDingTalkRuntime(dd)) {
      reject(new Error('请在钉钉客户端中打开通知链接以自动登录'));
      return;
    }

    const normalizedCorpId = corpId.trim();
    if (!normalizedCorpId) {
      reject(new Error('钉钉 CorpId（组织 ID）未配置，无法自动登录'));
      return;
    }

    const apis = buildAuthCodeApis(dd);
    if (apis.length === 0) {
      reject(new Error(`当前环境不支持钉钉免登授权${diagnosticSuffix(dd)}`));
      return;
    }

    let finished = false;
    let timer: number | undefined;
    let readyTimer: number | undefined;
    let apiFallbackTimer: number | undefined;
    let nextIndex = 0;
    const timeoutMs = options.timeoutMs ?? DINGTALK_TIMEOUT_MS;
    const apiNames = apis.map((api) => api.name);

    const finish = (callback: () => void) => {
      if (finished) {
        return;
      }
      finished = true;
      if (timer) {
        window.clearTimeout(timer);
      }
      if (readyTimer) {
        window.clearTimeout(readyTimer);
      }
      if (apiFallbackTimer) {
        window.clearTimeout(apiFallbackTimer);
      }
      callback();
    };

    timer = window.setTimeout(() => {
      finish(() => reject(new Error(`获取钉钉免登授权码超时，请确认已在钉钉客户端内打开，且 CorpId 与当前企业一致${diagnosticSuffix(dd, apiNames)}`)));
    }, timeoutMs);

    const failOrFallback = (error: unknown) => {
      if (nextIndex < apis.length) {
        invokeNext();
        return;
      }
      finish(() => reject(createError(error, '钉钉免登授权失败')));
    };

    const invoke = (api: AuthCodeRequestApi) => {
      if (finished) {
        return;
      }
      if (apiFallbackTimer) {
        window.clearTimeout(apiFallbackTimer);
      }
      if (isDingTalkPcRuntime() && nextIndex < apis.length) {
        apiFallbackTimer = window.setTimeout(invokeNext, DINGTALK_PC_API_FALLBACK_MS);
      }
      try {
        // 钉钉 JSBridge 方法保留原对象上下文调用，避免拆出函数后丢失端内桥接上下文。
        const result = api.call({
          corpID: normalizedCorpId,
          corpId: normalizedCorpId,
          fail: failOrFallback,
          onFail: failOrFallback,
          success: (result) => {
            const code = String(result?.code || result?.authCode || '').trim();
            finish(() =>
              code
                ? resolve(code)
                : reject(new Error(`钉钉未返回免登授权码${diagnosticSuffix(dd, [api.name])}`)),
            );
          },
          onSuccess: (result) => {
            const code = String(result?.code || result?.authCode || '').trim();
            finish(() =>
              code
                ? resolve(code)
                : reject(new Error(`钉钉未返回免登授权码${diagnosticSuffix(dd, [api.name])}`)),
            );
          },
        });
        if (result && typeof (result as PromiseLike<DingTalkAuthCodeResult>).then === 'function') {
          (result as PromiseLike<DingTalkAuthCodeResult>).then(
            (payload) => {
              const code = String(payload?.code || payload?.authCode || '').trim();
              finish(() =>
                code
                  ? resolve(code)
                  : reject(new Error(`钉钉未返回免登授权码${diagnosticSuffix(dd, [api.name])}`)),
              );
            },
            failOrFallback,
          );
        }
      } catch (error) {
        failOrFallback(error);
      }
    };

    const invokeNext = () => {
      if (finished || nextIndex >= apis.length) {
        return;
      }
      const api = apis[nextIndex++];
      if (!api) {
        return;
      }
      invoke(api);
    };

    try {
      dd.error?.((error) => finish(() => reject(createError(error, '钉钉 JSAPI 初始化失败'))));
      if (!apis[0]?.needsReady) {
        invokeNext();
      } else if (typeof dd.ready === 'function' && apis.some((api) => api.needsReady)) {
        dd.ready(invokeNext);
        if (apis.some((api) => !api.needsReady) || isDingTalkPcRuntime()) {
          readyTimer = window.setTimeout(invokeNext, DINGTALK_READY_GRACE_MS);
        }
      } else {
        invokeNext();
      }
    } catch (error) {
      if (isDingTalkPcRuntime() && nextIndex < apis.length) {
        invokeNext();
        return;
      }
      finish(() => reject(createError(error, '钉钉 JSAPI 初始化失败')));
    }
  });
}
