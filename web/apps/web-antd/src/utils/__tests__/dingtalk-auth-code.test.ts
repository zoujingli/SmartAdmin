import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
  requestDingTalkAuthCode,
  resolveDingTalkSdk,
} from '../dingtalk-auth-code';

const DINGTALK_JSAPI_SELECTOR =
  'script[src="https://g.alicdn.com/dingding/open-develop/1.9.0/dingtalk.js"]';

let appendSpy: ReturnType<typeof vi.spyOn> | null = null;
let createdScripts: HTMLScriptElement[] = [];

function cleanupDingTalkTestState() {
  vi.useRealTimers();
  appendSpy?.mockRestore();
  appendSpy = null;
  createdScripts = [];
  delete (window as any).dd;
  document.head
    .querySelectorAll(DINGTALK_JSAPI_SELECTOR)
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
      { runtime: { permission } },
      'corp-id',
    );

    expect(code).toBe('auth-code');
    expect(requestContext).toBe(permission);
  });

  it('fails when DingTalk JSAPI does not call back', async () => {
    vi.useFakeTimers();
    const promise = requestDingTalkAuthCode(
      {
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

  it('does not request auth code after ready callback times out', async () => {
    vi.useFakeTimers();
    const readyCallbacks: Array<() => void> = [];
    const requestAuthCode = vi.fn();
    const promise = requestDingTalkAuthCode(
      {
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
});

describe('resolveDingTalkSdk', () => {
  beforeEach(() => {
    createdScripts = [];
    appendSpy = vi.spyOn(document.head, 'append').mockImplementation((...nodes: (Node | string)[]) => {
      for (const node of nodes) {
        if (node instanceof HTMLScriptElement) {
          createdScripts.push(node);
        }
      }
    });
  });

  afterEach(() => {
    cleanupDingTalkTestState();
  });

  it('allows retry after script loading fails', async () => {
    const first = resolveDingTalkSdk({ timeoutMs: 10 });
    const firstScript = createdScripts[0];
    expect(firstScript).toBeTruthy();
    firstScript?.dispatchEvent(new Event('error'));

    await expect(first).rejects.toThrow('钉钉 JSAPI 加载失败');
    expect(firstScript?.isConnected).toBe(false);

    const second = resolveDingTalkSdk({ timeoutMs: 10 });
    const secondScript = createdScripts[1];
    expect(secondScript).toBeTruthy();
    (window as any).dd = { runtime: { permission: {} } };
    secondScript?.dispatchEvent(new Event('load'));

    await expect(second).resolves.toEqual((window as any).dd);
  });
});
