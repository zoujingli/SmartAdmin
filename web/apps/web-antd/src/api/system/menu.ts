/**
 * 菜单管理 API - 重构版本
 */
import { SystemApiService } from '../base';
import { createSearchParams } from '../utils';

// 菜单相关类型定义
export namespace MenuApi {
  /** 菜单信息 */
  export interface MenuInfo {
    id: number;
    pid: number;
    level: string;
    name: string;
    code: string;
    icon: string;
    type: string;
    route: string;
    redirect: string;
    sort: number;
    status: number;
    remark: string;
    created_by: number;
    updated_by: number;
    created_at: string;
    updated_at: string;
    deleted_at?: null | string;
    children?: MenuInfo[];
  }

  /** 菜单选项 */
  export interface MenuOption {
    id: number;
    name: string;
    children?: MenuOption[];
  }

  /** 后台注解权限节点建议 */
  export interface NodeOption {
    value: string;
    label: string;
    node: string;
    name: string;
  }

  /** 菜单表单数据 */
  export interface MenuFormData {
    id?: number;
    pid: number;
    level: string;
    name: string;
    code: string;
    icon: string;
    type: string;
    route: string;
    redirect: string;
    sort: number;
    status: number;
    remark?: string;
  }

  /** 菜单统计信息 */
  export interface MenuStatistics {
    total: number;
    today_created: number;
    active_count: number;
    inactive_count: number;
    by_status: Record<string, number>;
  }

}

/**
 * 菜单管理 API 服务
 */
class MenuApiService extends SystemApiService {
  constructor() {
    super();
  }

  /**
   * 获取菜单列表
   */
  async getMenuList(params: any) {
    return this.getList<MenuApi.MenuInfo>('system/menu/index', params.page, params.pageSize, params);
  }

  async getRecycleList(params: any = {}) {
    return this.getList<MenuApi.MenuInfo>('system/menu/recycle', params.page, params.pageSize, params);
  }

  /**
   * 获取菜单树
   */
  async getMenuTree() {
    return this.getTree<MenuApi.MenuInfo>('system/menu/tree');
  }

  /**
   * 获取菜单详情
   */
  async getMenuDetail(id: number) {
    return this.getDetail<MenuApi.MenuInfo>('system/menu/info', id);
  }

  /**
   * 创建菜单
   */
  async createMenu(data: MenuApi.MenuFormData) {
    return this.create<MenuApi.MenuInfo>('system/menu/create', data);
  }

  /**
   * 更新菜单
   */
  async updateMenu(id: number, data: MenuApi.MenuFormData) {
    return this.update<MenuApi.MenuInfo>('system/menu/update', id, data);
  }

  /**
   * 删除菜单
   */
  async deleteMenu(id: number) {
    return this.remove('system/menu/delete', id);
  }

  async recoveryMenus(ids: number[]) {
    return this.put(`system/menu/recovery/${ids.join(',')}`);
  }

  async realDeleteMenus(ids: number[]) {
    return this.delete(`system/menu/real-delete/${ids.join(',')}`);
  }

  /**
   * 更新菜单状态
   */
  async updateMenuStatus(id: number, status: number) {
    return this.updateStatus('system/menu/status', id, status);
  }

  /**
   * 获取菜单选项（树形结构）
   */
  async getMenuOptions() {
    return this.getOptions<MenuApi.MenuOption>('system/menu/options');
  }

  /**
   * 获取后台注解权限节点建议
   */
  async getNodeOptions(params: { keyword?: string; limit?: number } = {}) {
    return this.getOptions<MenuApi.NodeOption>('system/menu/node-options', createSearchParams(params));
  }

  /**
   * 获取菜单统计信息
   */
  async getMenuStatistics() {
    return this.get<MenuApi.MenuStatistics>('system/menu/statistics');
  }

}

// 导出单例实例
export const menuApiService = new MenuApiService();
