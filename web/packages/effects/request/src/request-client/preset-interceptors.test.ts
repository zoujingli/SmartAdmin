import { describe, expect, it, vi } from 'vitest';

import {
  authenticateResponseInterceptor,
  defaultResponseInterceptor,
} from './preset-interceptors';

describe('defaultResponseInterceptor', () => {
  it('should treat code 200 as success by default', () => {
    const interceptor = defaultResponseInterceptor({});

    const result = interceptor.fulfilled?.({
      config: {
        responseReturn: 'data',
      },
      data: {
        code: 200,
        data: {
          id: 1,
        },
      },
      status: 200,
    } as any);

    expect(result).toEqual({ id: 1 });
  });

  it('should reject non-200 business codes by default', () => {
    const interceptor = defaultResponseInterceptor({});

    expect(() =>
      interceptor.fulfilled?.({
        config: {
          responseReturn: 'data',
        },
        data: {
          code: 0,
          data: {
            id: 1,
          },
        },
        status: 200,
      } as any),
    ).toThrow();
  });
});

describe('authenticateResponseInterceptor', () => {
  it('should treat body code 401 as auth failure even when http status is 200', async () => {
    const doReAuthenticate = vi.fn();
    const interceptor = authenticateResponseInterceptor({
      client: {
        isRefreshing: false,
        refreshTokenQueue: [],
        request: vi.fn(),
      } as any,
      doReAuthenticate,
      doRefreshToken: vi.fn(),
      enableRefreshToken: false,
      formatToken: (token: string) => `Bearer ${token}`,
    });

    const error = {
      config: {},
      response: {
        data: {
          code: 401,
          info: 'Token 已失效',
        },
        status: 200,
      },
    };

    await expect(interceptor.rejected?.(error)).rejects.toBe(error);
    expect(doReAuthenticate).toHaveBeenCalledTimes(1);
  });
});
