import type { BasicUserInfo } from '@vben-core/typings';

/** 用户信息 */
interface UserInfo extends BasicUserInfo {
  /**
   * 用户描述
   */
  desc: string;
  /**
   * 首页地址
   */
  homePath: string;

  /**
   * accessToken
   */
  token: string;

  /**
   * 后端 profile 接口返回的完整用户资料（含邮箱、手机等）
   */
  profile?: {
    avatar?: string;
    email?: string;
    extra?: Record<string, unknown>;
    nickname?: string;
    phone?: string;
    signed?: string;
  };
}

export type { UserInfo };
