import { SystemApiService } from '../base';

export namespace DataApi {
  export interface Statistics {
    user_count: number;
    role_count: number;
    menu_count: number;
    dept_count: number;
    post_count: number;
    node_count: number;
    log_count: number;
    online_count: number;
    online_session_count: number;
    today_logs: number;
    recent_users: Array<{
      id: number;
      username: string;
      nickname: string;
      created_at: string;
    }>;
  }

  export interface SystemInfo {
    name: string;
    version: string;
    php_version: string;
    hyperf_version: string;
    swoole_version: string;
    server_time: string;
    timezone: string;
    memory_limit: string;
    max_execution_time: string;
  }

  export interface UiMeta {
    app_name: string;
    app_version: string;
    app_description: string;
    login_title: string;
    login_description: string;
    logo_url: string;
    logo_file_id: number;
    copyright: {
      enable: boolean;
      companyName: string;
      companySiteLink: string;
      date: string;
      icp: string;
      icpLink: string;
    };
  }

  export interface CapabilityItem {
    key: string;
    name: string;
    description: string;
  }

  export interface ModuleItem {
    key: string;
    name: string;
    path: string;
    icon: string;
    summary: string;
    features: string[];
    page_count: number;
    action_count: number;
    hidden_page_count: number;
  }

  export interface OnlineUserItem {
    user_id: number;
    username: string;
    nickname: string;
    user_model: string;
    last_active_at: string;
    expires_at: number;
  }

  export interface WorkbenchTodoItem {
    action_text?: string;
    completed: boolean;
    content: string;
    date: string;
    level?: 'danger' | 'info' | 'success' | 'warning';
    title: string;
    url?: string;
  }

  export interface CapabilityOverview {
    summary: {
      module_count: number;
      common_capability_count: number;
      cache_driver: string;
      cache_dynamic: boolean;
      permission_strategy: string;
      menu_source: string;
      online_user_count: number;
      online_session_count: number;
    };
    common_features: CapabilityItem[];
    modules: ModuleItem[];
    online_users: OnlineUserItem[];
  }
}

class DataApiService extends SystemApiService {
  async getStatistics() {
    return this.get<DataApi.Statistics>('system/data/index');
  }

  async getSystemInfo() {
    return this.get<DataApi.SystemInfo>('system/data/info');
  }

  async getConfig() {
    return this.get<Record<string, any>>('system/data/config');
  }

  async getCapabilities() {
    return this.get<DataApi.CapabilityOverview>('system/data/capabilities');
  }

  async getWorkbenchTodos() {
    return this.get<DataApi.WorkbenchTodoItem[]>('system/data/todos');
  }

  async getUiMeta() {
    return this.get<DataApi.UiMeta>('system/data/ui-meta');
  }

  async clearCache() {
    return this.post<{
      items: string[];
      message: string;
      scope: 'global' | 'self';
    }>('system/data/clear-cache');
  }

  async updateConfig(data: Record<string, any>) {
    return this.put<Record<string, any>>('system/data/config', data);
  }
}

export const dataApiService = new DataApiService();
