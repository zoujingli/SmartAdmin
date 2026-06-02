type DingTalkAuthCodeResult = {
  code?: string;
};

type DingTalkAuthCodeOptions = {
  corpId: string;
  onFail?: (error: unknown) => void;
  onSuccess?: (result: DingTalkAuthCodeResult) => void;
};

type DingTalkSdk = {
  error?: (callback: (error: unknown) => void) => void;
  ready?: (callback: () => void) => void;
  runtime?: {
    permission?: {
      requestAuthCode?: (options: DingTalkAuthCodeOptions) => void;
    };
  };
};

type TimeoutOptions = {
  timeoutMs?: number;
};

const DINGTALK_JSAPI_URL =
  'https://g.alicdn.com/dingding/open-develop/1.9.0/dingtalk.js';
const DINGTALK_TIMEOUT_MS = 15_000;

let scriptLoading: null | Promise<void> = null;

function dingTalkSdk(): DingTalkSdk | null {
  return typeof window === 'undefined' ? null : ((window as any).dd ?? null);
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

function loadDingTalkScript(timeoutMs: number) {
  if (dingTalkSdk()) {
    return Promise.resolve();
  }

  const existed = document.querySelector<HTMLScriptElement>(
    `script[src="${DINGTALK_JSAPI_URL}"]`,
  );
  if (existed) {
    if (scriptLoading) {
      return withTimeout(scriptLoading, timeoutMs, '钉钉 JSAPI 加载超时，请检查网络或钉钉客户端环境');
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
  script.src = DINGTALK_JSAPI_URL;
  script.async = true;

  scriptLoading = new Promise<void>((resolve, reject) => {
    // 钉钉 SDK 加载失败或超时时允许用户重试，避免缓存失败 Promise 后入口永久不可恢复。
    const clearLoading = () => {
      scriptLoading = null;
    };

    script.onload = () => {
      script.dataset.loaded = 'true';
      clearLoading();
      resolve();
    };
    script.onerror = () => {
      script.remove();
      clearLoading();
      reject(new Error('钉钉 JSAPI 加载失败'));
    };
    document.head.append(script);
  });

  return withTimeout(scriptLoading, timeoutMs, '钉钉 JSAPI 加载超时，请检查网络或钉钉客户端环境', () => {
    script?.remove();
    scriptLoading = null;
  });
}

export function isDingTalkClient() {
  if (typeof navigator === 'undefined') {
    return false;
  }

  return /DingTalk/i.test(navigator.userAgent || '');
}

export function hasDingTalkSdk() {
  return Boolean(dingTalkSdk());
}

export async function resolveDingTalkSdk(options: TimeoutOptions = {}) {
  const current = dingTalkSdk();
  if (current) {
    return current;
  }

  await loadDingTalkScript(options.timeoutMs ?? DINGTALK_TIMEOUT_MS);

  return dingTalkSdk();
}

export function requestDingTalkAuthCode(
  dd: DingTalkSdk,
  corpId: string,
  options: TimeoutOptions = {},
) {
  return new Promise<string>((resolve, reject) => {
    const normalizedCorpId = corpId.trim();
    if (!normalizedCorpId) {
      reject(new Error('钉钉 CorpId（组织 ID）未配置，无法自动登录'));
      return;
    }

    const permission = dd.runtime?.permission;
    const requestAuthCode = permission?.requestAuthCode;
    if (typeof requestAuthCode !== 'function') {
      reject(new Error('当前环境不支持钉钉免登授权'));
      return;
    }

    let finished = false;
    let timer: number | undefined;

    const finish = (callback: () => void) => {
      if (finished) {
        return;
      }
      finished = true;
      if (timer) {
        window.clearTimeout(timer);
      }
      callback();
    };

    timer = window.setTimeout(() => {
      finish(() => reject(new Error('获取钉钉免登授权码超时，请确认已在钉钉客户端内打开，且 CorpId 与当前企业一致')));
    }, options.timeoutMs ?? DINGTALK_TIMEOUT_MS);

    const invoke = () => {
      if (finished) {
        return;
      }
      try {
        // 钉钉 JSBridge 方法保留在 permission 对象上调用，避免拆出函数后丢失端内上下文。
        requestAuthCode.call(permission, {
          corpId: normalizedCorpId,
          onFail: (error) => finish(() => reject(createError(error, '钉钉免登授权失败'))),
          onSuccess: (result) => {
            const code = String(result?.code || '').trim();
            finish(() =>
              code
                ? resolve(code)
                : reject(new Error('钉钉未返回免登授权码')),
            );
          },
        });
      } catch (error) {
        finish(() => reject(createError(error, '钉钉免登授权失败')));
      }
    };

    try {
      dd.error?.((error) => finish(() => reject(createError(error, '钉钉 JSAPI 初始化失败'))));
      if (typeof dd.ready === 'function') {
        dd.ready(invoke);
      } else {
        invoke();
      }
    } catch (error) {
      finish(() => reject(createError(error, '钉钉 JSAPI 初始化失败')));
    }
  });
}
