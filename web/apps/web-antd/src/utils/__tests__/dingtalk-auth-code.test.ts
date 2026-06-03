import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
  isDingTalkClient,
  requestDingTalkAuthCode,
  resolveDingTalkSdk,
} from '../dingtalk-auth-code';

const DINGTALK_JSAPI_SELECTOR =
  'script[src="https://g.alicdn.com/dingding/open-develop/1.9.0/dingtalk.js"]';
const DINGTALK_OPEN_JSAPI_SELECTOR =
  'script[src="https://g.alicdn.com/dingding/dingtalk-jsapi/3.2.0/dingtalk.open.js"]';
const DINGTALK_PC_JSAPI_SELECTOR =
  'script[src="https://g.alicdn.com/dingding/dingtalk-pc-api/2.9.0/index.js"]';

let appendSpy: ReturnType<typeof vi.spyOn> | null = null;
let createdScripts: HTMLScriptElement[] = [];
let scriptEventMode: 'manual' | 'auto-error' | 'auto-load' = 'manual';
let userAgentSpy: ReturnType<typeof vi.spyOn> | null = null;

function cleanupDingTalkTestState() {
  vi.useRealTimers();
  appendSpy?.mockRestore();
  appendSpy = null;
  userAgentSpy?.mockRestore();
  userAgentSpy = null;
  createdScripts = [];
  scriptEventMode = 'manual';
  delete (window as any).dd;
  delete (window as any).DingTalkPC;
  document.head
    .querySelectorAll(`${DINGTALK_JSAPI_SELECTOR},${DINGTALK_OPEN_JSAPI_SELECTOR},${DINGTALK_PC_JSAPI_SELECTOR}`)
    .forEach((script) => script.remove());
}

describe('requestDingTalkAuthCode', () => {
  afterEach(() => {
    cleanupDingTalkTestState();
  });

  it('keeps DingTalk permission context when requesting auth code', async () => {
    let requestContext: unknown = null;
    const permission = {
      requestAuthCode(this: unknown, options: any) {
        requestContext = this;
        options.onSuccess({ code: 'auth-code' });
      },
    };

    const code = await requestDingTalkAuthCode(
      { env: { platform: 'ios' }, runtime: { permission } },
      'corp-id',
    );

    expect(code).toBe('auth-code');
    expect(requestContext).toBe(permission);
  });

  it('fails when DingTalk JSAPI does not call back', async () => {
    vi.useFakeTimers();
    const promise = requestDingTalkAuthCode(
      {
        env: { platform: 'android' },
        runtime: {
          permission: {
            requestAuthCode() {},
          },
        },
      },
      'corp-id',
      { timeoutMs: 10 },
    );

    const assertion = expect(promise).rejects.toThrow('获取钉钉免登授权码超时');

    await vi.advanceTimersByTimeAsync(10);
    await assertion;
  });

  it('uses the new getAuthCode API and authCode result field', async () => {
    const code = await requestDingTalkAuthCode(
      {
        env: { platform: 'ios' },
        getAuthCode(options) {
          expect(options.corpId).toBe('corp-id');
          expect(options.corpID).toBe('corp-id');
          options.success?.({ authCode: 'new-auth-code' });
        },
      },
      'corp-id',
    );

    expect(code).toBe('new-auth-code');
  });

  it('resolves promise based auth code APIs', async () => {
    const code = await requestDingTalkAuthCode(
      {
        env: { platform: 'pc' },
        getAuthCode() {
          return Promise.resolve({ code: 'promise-auth-code' });
        },
      },
      'corp-id',
    );

    expect(code).toBe('promise-auth-code');
  });

  it('does not request auth code after ready callback times out', async () => {
    vi.useFakeTimers();
    const readyCallbacks: Array<() => void> = [];
    const requestAuthCode = vi.fn();
    const promise = requestDingTalkAuthCode(
      {
        env: { platform: 'ios' },
        ready(callback) {
          readyCallbacks.push(callback);
        },
        runtime: {
          permission: { requestAuthCode },
        },
      },
      'corp-id',
      { timeoutMs: 10 },
    );
    const assertion = expect(promise).rejects.toThrow('获取钉钉免登授权码超时');

    await vi.advanceTimersByTimeAsync(10);
    const invokeReady = readyCallbacks[0];
    if (!invokeReady) {
      throw new Error('钉钉 ready 回调未注册');
    }
    invokeReady();

    expect(requestAuthCode).not.toHaveBeenCalled();
    await assertion;
  });

  it('falls back to the PC SDK when DingTalk ready never runs on desktop', async () => {
    vi.useFakeTimers();
    userAgentSpy = vi
      .spyOn(window.navigator, 'userAgent', 'get')
      .mockReturnValue('Mozilla/5.0 DingTalk(8.3.20-macOS-arm64) nw DTWKWebView webDt/PC');

    const readyCallbacks: Array<() => void> = [];
    const legacyRequestAuthCode = vi.fn();
    const pcRequestAuthCode = vi.fn((options: any) => {
      options.onSuccess({ code: 'pc-auth-code' });
    });
    (window as any).DingTalkPC = {
      runtime: { permission: { requestAuthCode: pcRequestAuthCode } },
    };

    const promise = requestDingTalkAuthCode(
      {
        env: { platform: 'pc', platformSub: 'mac' },
        ready(callback) {
          readyCallbacks.push(callback);
        },
        runtime: {
          permission: { requestAuthCode: legacyRequestAuthCode },
        },
      },
      'corp-id',
    );

    await expect(promise).resolves.toBe('pc-auth-code');
    expect(pcRequestAuthCode).toHaveBeenCalledTimes(1);
    expect(legacyRequestAuthCode).not.toHaveBeenCalled();
    expect(readyCallbacks).toHaveLength(0);
  });

  it('tries the next PC auth API when the previous API does not call back', async () => {
    vi.useFakeTimers();
    userAgentSpy = vi
      .spyOn(window.navigator, 'userAgent', 'get')
      .mockReturnValue('Mozilla/5.0 DingTalk(8.3.20-macOS-arm64) nw DTWKWebView webDt/PC');

    const pcRequestAuthCode = vi.fn();
    const openRequestAuthCode = vi.fn((options: any) => {
      options.onSuccess({ code: 'open-auth-code' });
    });
    (window as any).DingTalkPC = {
      runtime: { permission: { requestAuthCode: pcRequestAuthCode } },
    };

    const promise = requestDingTalkAuthCode(
      {
        env: { platform: 'pc', platformSub: 'mac' },
        runtime: {
          permission: { requestAuthCode: openRequestAuthCode },
        },
      },
      'corp-id',
      { timeoutMs: 5_000 },
    );

    await vi.advanceTimersByTimeAsync(2_500);

    await expect(promise).resolves.toBe('open-auth-code');
    expect(pcRequestAuthCode).toHaveBeenCalledTimes(1);
    expect(openRequestAuthCode).toHaveBeenCalledTimes(1);
  });

  it('fails immediately outside DingTalk even when a dd object exists', async () => {
    const requestAuthCode = vi.fn();

    await expect(
      requestDingTalkAuthCode(
        { runtime: { permission: { requestAuthCode } } },
        'corp-id',
        { timeoutMs: 10 },
      ),
    ).rejects.toThrow('请在钉钉客户端中打开通知链接以自动登录');

    expect(requestAuthCode).not.toHaveBeenCalled();
  });
});

describe('isDingTalkClient', () => {
  afterEach(() => {
    cleanupDingTalkTestState();
  });

  it('does not treat a plain SDK object as DingTalk client environment', () => {
    (window as any).dd = { runtime: { permission: {} } };

    expect(isDingTalkClient()).toBe(false);
  });

  it('detects DingTalk from sdk platform or user agent', () => {
    (window as any).dd = { env: { platform: 'ios' } };
    expect(isDingTalkClient()).toBe(true);

    delete (window as any).dd;
    userAgentSpy = vi
      .spyOn(window.navigator, 'userAgent', 'get')
      .mockReturnValue('Mozilla/5.0 DingTalk');
    expect(isDingTalkClient()).toBe(true);
  });
});

describe('resolveDingTalkSdk', () => {
  beforeEach(() => {
    createdScripts = [];
    appendSpy = vi.spyOn(document.head, 'append').mockImplementation((...nodes: (Node | string)[]) => {
      for (const node of nodes) {
        if (node instanceof HTMLScriptElement) {
          createdScripts.push(node);
          if (scriptEventMode === 'auto-error') {
            window.setTimeout(() => node.dispatchEvent(new window.Event('error')), 0);
          }
          if (scriptEventMode === 'auto-load') {
            window.setTimeout(() => node.dispatchEvent(new window.Event('load')), 0);
          }
        }
      }
    });
  });

  afterEach(() => {
    cleanupDingTalkTestState();
  });

  it('allows retry after script loading fails', async () => {
    scriptEventMode = 'auto-error';
    const first = resolveDingTalkSdk({ timeoutMs: 10 });
    const firstScript = createdScripts[0];
    expect(firstScript).toBeTruthy();
    expect(firstScript?.src).toBe('https://g.alicdn.com/dingding/dingtalk-jsapi/3.2.0/dingtalk.open.js');

    await expect(first).rejects.toThrow('钉钉 JSAPI 加载失败');
    expect(firstScript?.isConnected).toBe(false);
    expect(createdScripts[1]?.src).toBe('https://g.alicdn.com/dingding/open-develop/1.9.0/dingtalk.js');

    scriptEventMode = 'auto-load';
    const second = resolveDingTalkSdk({ timeoutMs: 10 });
    const secondScript = createdScripts[2];
    expect(secondScript).toBeTruthy();
    expect(secondScript?.src).toBe('https://g.alicdn.com/dingding/dingtalk-jsapi/3.2.0/dingtalk.open.js');
    (window as any).dd = { runtime: { permission: {} } };

    await expect(second).resolves.toEqual((window as any).dd);
  });

  it('loads open and PC SDKs for DingTalk desktop clients', async () => {
    userAgentSpy = vi
      .spyOn(window.navigator, 'userAgent', 'get')
      .mockReturnValue('Mozilla/5.0 DingTalk(8.3.20-macOS-arm64) nw DTWKWebView webDt/PC');

    scriptEventMode = 'auto-load';
    const promise = resolveDingTalkSdk({ timeoutMs: 10 });
    const openScript = createdScripts[0];
    expect(openScript?.src).toBe('https://g.alicdn.com/dingding/dingtalk-jsapi/3.2.0/dingtalk.open.js');

    await new Promise((resolve) => window.setTimeout(resolve, 0));
    const pcScript = createdScripts[1];
    expect(pcScript?.src).toBe('https://g.alicdn.com/dingding/dingtalk-pc-api/2.9.0/index.js');
    (window as any).DingTalkPC = { runtime: { permission: {} } };

    await expect(promise).resolves.toEqual((window as any).DingTalkPC);
  });
});
