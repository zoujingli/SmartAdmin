import { requestClient } from '#/api/request';

import forge from 'node-forge';

export interface PasswordCipherPayload {
  kid: string;
  nonce: string;
  ciphertext: string;
}

interface PasswordCryptoParameters {
  kid: string;
  alg: 'RSA-OAEP';
  hash: 'SHA-1';
  public_key: string;
  nonce_ttl: number;
  nonces: string[];
}

const PASSWORD_CRYPTO_CLIENT_ERROR_NAME = 'PasswordCryptoClientError';

export const PASSWORD_PURPOSES = {
  authLogin: 'system.auth.login.password',
  authChangeOld: 'system.auth.password.old_password',
  authChangeNew: 'system.auth.password.new_password',
  userCreate: 'system.user.create.password',
  userUpdate: 'system.user.update.password',
  userReset: 'system.user.reset_password.password',
} as const;

/**
 * 对请求体中指定密码字段执行服务端 RSA-OAEP 传输加密。
 *
 * 表单层仍保留用户输入的真实密码；该 helper 只在发送前替换请求体，后端解密后仍会走 password_hash()。
 */
export async function encryptPasswordFields<T extends Record<string, any>>(
  data: T,
  purposes: Partial<Record<keyof T & string, string>>,
  options: { parametersUrl?: string } = {},
): Promise<T> {
  const fields = Object.entries(purposes).filter(([field, purpose]) => {
    return typeof purpose === 'string' && typeof data[field] === 'string';
  }) as Array<[keyof T & string, string]>;
  if (fields.length === 0) {
    return data;
  }

  const params = await requestClient.get<PasswordCryptoParameters>(options.parametersUrl || '/system/auth/password-crypto', {
    params: { count: fields.length },
  });
  if (!Array.isArray(params.nonces) || params.nonces.length < fields.length) {
    throw createPasswordCryptoClientError('密码加密参数不足，请刷新页面后重试');
  }

  const publicKey = importPublicKey(params.public_key);
  const encrypted = { ...data };
  for (const [index, [field, purpose]] of fields.entries()) {
    const nonce = params.nonces[index];
    if (!nonce) {
      throw createPasswordCryptoClientError('密码加密 nonce 不足，请刷新页面后重试');
    }

    (encrypted as Record<string, any>)[field] = encryptPasswordValue({
      kid: params.kid,
      nonce,
      publicKey,
      purpose,
      value: String(data[field]),
    });
  }

  return encrypted;
}

function encryptPasswordValue(input: {
  kid: string;
  nonce: string;
  publicKey: forge.pki.rsa.PublicKey;
  purpose: string;
  value: string;
}): PasswordCipherPayload {
  const plain = JSON.stringify({
    v: 1,
    purpose: input.purpose,
    nonce: input.nonce,
    ts: Math.floor(Date.now() / 1000),
    value: input.value,
  });

  let ciphertext: string;
  try {
    // 使用纯 JS RSA-OAEP，避免局域网 HTTP 开发访问时浏览器禁用 Web Crypto；
    // 后端 OpenSSL OAEP 默认是 SHA-1/MGF1(SHA-1)，这里必须保持一致。
    ciphertext = input.publicKey.encrypt(
      forge.util.encodeUtf8(plain),
      'RSA-OAEP',
      {
        md: forge.md.sha1.create(),
        mgf1: {
          md: forge.md.sha1.create(),
        },
      },
    );
  } catch {
    // RSA-OAEP 是单块加密，密码或扩展载荷过长、运行时算法异常都会在本地失败。
    throw createPasswordCryptoClientError('密码本地加密失败，请检查密码长度或刷新页面后重试');
  }

  return {
    kid: input.kid,
    nonce: input.nonce,
    ciphertext: forge.util.encode64(ciphertext),
  };
}

function importPublicKey(pem: string): forge.pki.rsa.PublicKey {
  try {
    return forge.pki.publicKeyFromPem(pem);
  } catch {
    // 公钥由服务端运行时导出；导入失败通常表示缓存旧页面或密钥响应被破坏。
    throw createPasswordCryptoClientError('密码加密公钥导入失败，请刷新页面后重试');
  }
}

function createPasswordCryptoClientError(message: string): Error {
  const error = new Error(message);
  // 登录页会据此区分“本地加密前置失败”和普通接口错误，避免只在控制台输出。
  error.name = PASSWORD_CRYPTO_CLIENT_ERROR_NAME;

  return error;
}
