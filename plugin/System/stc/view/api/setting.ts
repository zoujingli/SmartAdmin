import { AdminApiService } from '#/api/base';

export namespace SettingApi {
  export interface ModuleGuideOption {
    code: string;
    description: string;
    icon: string;
    name: string;
  }

  export interface SystemSettingPayload {
    app_name: string;
    app_version: string;
    app_description: string;
    login_title: string;
    login_description: string;
    logo_url: string;
    logo_file_id: number;
    module_guide_enable: boolean;
    module_guide_visibility: Record<string, boolean>;
    copyright_enable: boolean;
    company_name: string;
    company_site_link: string;
    copyright_date: string;
    icp: string;
    icp_link: string;
  }

  export interface SystemSetting extends SystemSettingPayload {
    module_guide_options: ModuleGuideOption[];
  }
}

class SettingApiService extends AdminApiService {
  async getInfo() {
    return this.get<SettingApi.SystemSetting>('system/setting/info');
  }

  async updateInfo(data: SettingApi.SystemSettingPayload) {
    return this.put<SettingApi.SystemSetting>('system/setting/info', data);
  }
}

export const settingApiService = new SettingApiService();
