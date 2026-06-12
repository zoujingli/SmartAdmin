import type { RouteRecordStringComponent } from '@vben/types';

const menus: RouteRecordStringComponent[] = [];

/**
 * System 是默认后台认证入口，但入口定义由插件提供，web 壳只负责扫描和消费。
 */
export default {
  authBase: '/system/auth',
  default: true,
  entry: 'system',
  homePath: '/dashboard/workspace',
  loginPath: '/system/login',
  menus,
  name: '系统后台',
  passwordPurposes: {
    authChangeNew: 'system.auth.password.new_password',
    authChangeOld: 'system.auth.password.old_password',
    authLogin: 'system.auth.login.password',
    userCreate: 'system.user.create.password',
    userReset: 'system.user.reset_password.password',
    userUpdate: 'system.user.update.password',
  },
  permissionPrefixes: ['system.', 'dashboard.'],
  profilePath: '/account/profile',
  routePrefixes: ['/dashboard', '/system', '/account/profile'],
  userModel: 'System\\Model\\SystemUser',
  userModelIncludes: ['SystemUser'],
};
