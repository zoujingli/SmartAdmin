function createMemoryStorage(): Storage {
  const store = new Map<string, string>();

  return {
    clear() {
      store.clear();
    },
    getItem(key) {
      return store.has(key) ? store.get(key)! : null;
    },
    key(index) {
      return [...store.keys()][index] ?? null;
    },
    get length() {
      return store.size;
    },
    removeItem(key) {
      store.delete(key);
    },
    setItem(key, value) {
      store.set(String(key), String(value));
    },
  };
}

function ensureStorage(name: 'localStorage' | 'sessionStorage') {
  const current = globalThis[name];
  if (
    typeof current?.clear === 'function' &&
    typeof current?.getItem === 'function' &&
    typeof current?.key === 'function' &&
    typeof current?.removeItem === 'function' &&
    typeof current?.setItem === 'function'
  ) {
    return;
  }

  const storage = createMemoryStorage();

  Object.defineProperty(globalThis, name, {
    configurable: true,
    value: storage,
    writable: true,
  });

  if (typeof window !== 'undefined') {
    Object.defineProperty(window, name, {
      configurable: true,
      value: storage,
      writable: true,
    });
  }
}

ensureStorage('localStorage');
ensureStorage('sessionStorage');
