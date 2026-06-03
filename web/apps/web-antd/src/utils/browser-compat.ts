const arrayPrototype = Array.prototype as unknown as Record<string, unknown>;

function defineArrayMethod(name: string, value: Function) {
  if (typeof arrayPrototype[name] === 'function') {
    return;
  }

  Object.defineProperty(Array.prototype, name, {
    configurable: true,
    value,
    writable: true,
  });
}

function normalizeArrayIndex(index: number, length: number): number {
  const offset = index < 0 ? length + index : index;
  if (offset < 0 || offset >= length) {
    throw new RangeError('Array index out of range');
  }

  return offset;
}

// 钉钉内置浏览器可能落后于系统 Chrome，启动阶段先补齐 ES2022/ES2023 数组方法。
defineArrayMethod('toSorted', function toSorted<T>(this: T[], compareFn?: (a: T, b: T) => number): T[] {
  return this.slice().sort(compareFn);
});

defineArrayMethod('toReversed', function toReversed<T>(this: T[]): T[] {
  return this.slice().reverse();
});

defineArrayMethod('toSpliced', function toSpliced<T>(this: T[], start: number, deleteCount?: number, ...items: T[]): T[] {
  const result = this.slice();
  if (arguments.length === 1) {
    result.splice(start);
  } else {
    result.splice(start, deleteCount ?? result.length, ...items);
  }

  return result;
});

defineArrayMethod('with', function arrayWith<T>(this: T[], index: number, value: T): T[] {
  const result = this.slice();
  result[normalizeArrayIndex(Number(index), result.length)] = value;

  return result;
});

defineArrayMethod('findLast', function findLast<T>(
  this: T[],
  predicate: (value: T, index: number, array: T[]) => boolean,
  thisArg?: unknown,
): T | undefined {
  for (let index = this.length - 1; index >= 0; index -= 1) {
    if (predicate.call(thisArg, this[index] as T, index, this)) {
      return this[index];
    }
  }

  return undefined;
});

defineArrayMethod('findLastIndex', function findLastIndex<T>(
  this: T[],
  predicate: (value: T, index: number, array: T[]) => boolean,
  thisArg?: unknown,
): number {
  for (let index = this.length - 1; index >= 0; index -= 1) {
    if (predicate.call(thisArg, this[index] as T, index, this)) {
      return index;
    }
  }

  return -1;
});
