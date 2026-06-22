import { afterEach, describe, expect, it, vi } from 'vitest';

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { __uploadClientTestHooks, resolveUploadAccept } from './upload-client';
import type { UploadRuntimeConfig } from './types';

describe('upload-client transport contract', () => {
  const source = readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), 'upload-client.ts'), 'utf8');

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('re-prepares relay fallback after aborting a failed direct session', () => {
    expect(source).toContain('class DirectTransportError extends Error');
    expect(source).toContain('throw new DirectTransportError(error);');
    expect(source).toContain('error instanceof DirectTransportError');
    expect(source).toContain("await abortUploadSession(prepare.upload_session_id);");
    expect(source).toContain("return await executePreparedUpload(await prepareUpload('relay'));");
    expect(source).toContain("['direct-single', 'direct-multipart'].includes(String(prepare.transport))");
  });

  it('does not reuse direct-single session for whole-file relay fallback', () => {
    const directSingleBlock = source.slice(
      source.indexOf("if (transport === 'direct-single')"),
      source.indexOf("if (transport === 'direct-multipart')"),
    );

    expect(directSingleBlock).not.toContain('uploadRelaySingle(');
    expect(directSingleBlock).not.toContain("uploadEndpoint('relay')");
  });

  it('rejects unknown transport before executing upload instructions', () => {
    expect(__uploadClientTestHooks?.assertUploadTransport('relay-single')).toBe('relay-single');
    expect(() => __uploadClientTestHooks?.assertUploadTransport('legacy-relay')).toThrow('不支持的上传方式: legacy-relay');
    expect(source).toContain("const uploadTransports = ['instant', 'direct-single', 'direct-multipart', 'relay-single', 'relay-chunk'] as const");
  });

  it('keeps safe headers for form direct upload but lets browser own multipart content-type', async () => {
    const sentHeaders: Record<string, string> = {};

    class MockXhr {
      public onload: null | (() => void) = null;
      public upload = {};
      public status = 204;

      getResponseHeader() {
        return null;
      }

      open() {}

      send() {
        this.onload?.();
      }

      setRequestHeader(key: string, value: string) {
        sentHeaders[key] = value;
      }
    }

    vi.stubGlobal('XMLHttpRequest', MockXhr);

    await __uploadClientTestHooks?.uploadByXhr('https://upload.example.test', new Blob(['a']), {
      formFields: { key: 'object-key', token: 'token' },
      headers: {
        'Content-Type': 'multipart/form-data',
        'X-Signed-Header': 'signed-value',
      },
      method: 'POST',
    });

    expect(sentHeaders).toEqual({
      'X-Signed-Header': 'signed-value',
    });
  });

  it('intersects scene accept extensions with backend allow list', () => {
    const runtime = {
      common: {
        allow_exts: 'jpg,pdf,mp4',
      },
    } as UploadRuntimeConfig;

    expect(resolveUploadAccept(runtime, 'image')).toBe('.jpg');
    expect(resolveUploadAccept(runtime, 'video')).toBe('.mp4');
    expect(resolveUploadAccept(runtime, 'file')).toBe('.pdf');
  });
});
