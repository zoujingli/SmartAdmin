import { describe, expect, it } from 'vitest';

import { md5Bytes } from './md5';

describe('md5Bytes', () => {
  it('hashes hello correctly', () => {
    const bytes = new TextEncoder().encode('hello');

    expect(md5Bytes(bytes)).toBe('5d41402abc4b2a76b9719d911017c592');
  });
});
