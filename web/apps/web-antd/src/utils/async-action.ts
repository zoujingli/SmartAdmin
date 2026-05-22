import { computed, readonly, ref } from 'vue';

export type AsyncActionHandler<T = unknown> = () => Promise<T> | T;

interface UseAsyncActionOptions {
  /**
   * 同步操作也保留一个极短 pending 窗口，拦截双击/连点造成的重复弹窗或重复请求。
   */
  minimumDuration?: number;
}

export function isPromiseLike<T = unknown>(value: unknown): value is PromiseLike<T> {
  return Boolean(value && typeof (value as PromiseLike<T>).then === 'function');
}

/**
 * 统一管理按钮/表格操作的异步执行状态：同一作用域内任一操作执行中时，后续点击直接忽略。
 * 适合列表行操作、搜索/导出等需要防重复提交的高频按钮场景。
 */
export function useAsyncAction(options: UseAsyncActionOptions = {}) {
  const pendingKey = ref('');
  const running = computed(() => pendingKey.value !== '');
  const minimumDuration = options.minimumDuration ?? 220;

  function isPending(key: string) {
    return pendingKey.value === key;
  }

  async function run<T>(key: string, handler?: AsyncActionHandler<T>) {
    if (running.value || !handler) {
      return undefined;
    }

    pendingKey.value = key;
    const startedAt = Date.now();
    try {
      return await handler();
    } finally {
      const remaining = minimumDuration - (Date.now() - startedAt);
      if (remaining > 0) {
        await new Promise((resolve) => setTimeout(resolve, remaining));
      }
      pendingKey.value = '';
    }
  }

  return {
    isPending,
    pendingKey: readonly(pendingKey),
    run,
    running,
  };
}
