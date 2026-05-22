import { SystemApiService } from '../base';

export namespace SettingApi {
  export interface SystemSetting {
    app_name: string;
    app_version: string;
    app_description: string;
    login_title: string;
    login_description: string;
    logo_url: string;
    logo_file_id: number;
    copyright_enable: boolean;
    company_name: string;
    company_site_link: string;
    copyright_date: string;
    icp: string;
    icp_link: string;
  }
}

class SettingApiService extends SystemApiService {
  async getInfo() {
    return this.get<SettingApi.SystemSetting>('system/setting/info');
  }

  async updateInfo(data: SettingApi.SystemSetting) {
    return this.put<SettingApi.SystemSetting>('system/setting/info', data);
  }
}

export const settingApiService = new SettingApiService();
