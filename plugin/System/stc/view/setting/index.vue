<template>
  <Page title="系统参数">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="loadSettings">
          <span class="i-lucide-refresh-cw mr-1" />
          刷新
        </Button>
        <Button
          v-if="canSaveSettings"
          type="primary"
          :loading="saving"
          @click="handleSave"
        >
          <span class="i-lucide-save mr-1" />
          保存参数
        </Button>
      </Space>
    </template>
    <Form ref="formRef" :model="formState" :rules="formRules" layout="vertical">
      <CrudNoticeAlert
        v-if="!canSaveSettings"
        custom-class="mb-4"
        message="当前账号仅可查看系统参数，没有保存权限。"
        type="warning"
      />

      <Row :gutter="[16, 16]">
        <Col :lg="14" :span="24">
          <Card title="基础信息" :loading="loading">
            <Row :gutter="[16, 0]">
              <Col :md="12" :span="24">
                <FormItem label="系统名称" name="app_name">
                  <Input
                    v-model:value="formState.app_name"
                    :disabled="!canSaveSettings"
                    :maxlength="80"
                    allow-clear
                    placeholder="请输入系统名称"
                  />
                </FormItem>
              </Col>
              <Col :md="12" :span="24">
                <FormItem label="系统版本" name="app_version">
                  <Input
                    v-model:value="formState.app_version"
                    :disabled="!canSaveSettings"
                    :maxlength="30"
                    allow-clear
                    placeholder="请输入系统版本"
                  />
                </FormItem>
              </Col>
              <Col :span="24">
                <FormItem label="系统描述" name="app_description">
                  <Textarea
                    v-model:value="formState.app_description"
                    :disabled="!canSaveSettings"
                    :maxlength="255"
                    :rows="3"
                    allow-clear
                    placeholder="请输入系统描述"
                    show-count
                  />
                </FormItem>
              </Col>
              <Col :span="24">
                <FormItem label="系统 Logo" name="logo_url">
                  <AdminImageUpload
                    v-model="logoFieldValue"
                    :allow-select-existing="false"
                    :clearable="true"
                    :disabled="!canSaveSettings"
                    button-text="上传 Logo"
                    scene="image"
                  />
                </FormItem>
              </Col>
            </Row>
          </Card>

          <Card class="mt-4" title="登录展示" :loading="loading">
            <Row :gutter="[16, 0]">
              <Col :span="24">
                <FormItem label="登录页主标题" name="login_title">
                  <Input
                    v-model:value="formState.login_title"
                    :disabled="!canSaveSettings"
                    :maxlength="120"
                    allow-clear
                    placeholder="请输入登录页主标题"
                  />
                </FormItem>
              </Col>
              <Col :span="24">
                <FormItem label="登录页副标题" name="login_description">
                  <Textarea
                    v-model:value="formState.login_description"
                    :disabled="!canSaveSettings"
                    :maxlength="255"
                    :rows="3"
                    allow-clear
                    placeholder="请输入登录页副标题"
                    show-count
                  />
                </FormItem>
              </Col>
            </Row>
          </Card>
        </Col>

        <Col :lg="10" :span="24">
          <Card title="版权备案" :loading="loading">
            <FormItem label="显示版权信息" name="copyright_enable">
              <Switch
                v-model:checked="formState.copyright_enable"
                :disabled="!canSaveSettings"
              />
            </FormItem>
            <FormItem label="公司名称" name="company_name">
              <Input
                v-model:value="formState.company_name"
                :disabled="!canSaveSettings"
                :maxlength="120"
                allow-clear
                placeholder="请输入公司名称"
              />
            </FormItem>
            <FormItem label="公司官网" name="company_site_link">
              <Input
                v-model:value="formState.company_site_link"
                :disabled="!canSaveSettings"
                :maxlength="500"
                allow-clear
                placeholder="请输入公司官网"
              />
            </FormItem>
            <FormItem label="版权年份" name="copyright_date">
              <Input
                v-model:value="formState.copyright_date"
                :disabled="!canSaveSettings"
                :maxlength="20"
                allow-clear
                placeholder="例如 2026"
              />
            </FormItem>
            <FormItem label="备案号" name="icp">
              <Input
                v-model:value="formState.icp"
                :disabled="!canSaveSettings"
                :maxlength="120"
                allow-clear
                placeholder="请输入备案号"
              />
            </FormItem>
            <FormItem label="备案链接" name="icp_link">
              <Input
                v-model:value="formState.icp_link"
                :disabled="!canSaveSettings"
                :maxlength="500"
                allow-clear
                placeholder="请输入备案链接"
              />
            </FormItem>
          </Card>

          <Card class="mt-4" title="展示预览" :loading="loading">
            <div class="system-setting-preview">
              <img
                v-if="formState.logo_url"
                :src="formState.logo_url"
                alt="Logo"
                class="system-setting-preview__logo"
              />
              <div v-else class="system-setting-preview__placeholder">
                <span class="i-lucide-image" />
              </div>
              <div class="min-w-0">
                <div class="system-setting-preview__name">{{ formState.app_name || '-' }}</div>
                <div class="system-setting-preview__desc">{{ formState.login_title || '-' }}</div>
                <div class="system-setting-preview__sub">{{ formState.login_description || '-' }}</div>
              </div>
            </div>
          </Card>
        </Col>
      </Row>
    </Form>
  </Page>
</template>

<script setup lang="ts">
import type { UploadAsset, UploadFieldValue } from '@vben/common-ui';

import { AdminImageUpload, CrudNoticeAlert, Page } from '@vben/common-ui';
import { useAccess } from '@vben/access';

import {
  Button,
  Card,
  Col,
  Form,
  FormItem,
  Input,
  Row,
  Space,
  Switch,
  Textarea,
  message,
} from 'ant-design-vue';
import { computed, onMounted, reactive, ref } from 'vue';

import { dataApiService, settingApiService } from '#/api';
import type { SettingApi } from '#/api';
import { applyUiMetaPreferences } from '#/preferences/user-preferences';

const { hasAccessByCodes } = useAccess();
const canSaveSettings = computed(() => hasAccessByCodes(['system.setting.save']));
const loading = ref(false);
const saving = ref(false);
const formRef = ref();
const logoAsset = ref<null | UploadAsset>(null);

const defaultSystemSetting: SettingApi.SystemSetting = {
  app_name: 'SmartAdmin',
  app_version: '1.0.0',
  app_description: '',
  login_title: '新一代企业级数字化运营平台',
  login_description: '融合权限治理、组织协同、数据安全与全链路审计能力',
  logo_url: '',
  logo_file_id: 0,
  copyright_enable: true,
  company_name: 'SmartAdmin',
  company_site_link: '',
  copyright_date: String(new Date().getFullYear()),
  icp: '',
  icp_link: '',
};

const formState = reactive<SettingApi.SystemSetting>({ ...defaultSystemSetting });

const formRules: any = {
  app_name: [{ message: '请输入系统名称', required: true, trigger: 'blur' }],
  app_version: [{ message: '请输入系统版本', required: true, trigger: 'blur' }],
  login_title: [{ message: '请输入登录页主标题', required: true, trigger: 'blur' }],
};

const logoFieldValue = computed<UploadFieldValue>({
  get: () => logoAsset.value,
  set: (value) => {
    const asset = Array.isArray(value) ? (value[0] ?? null) : value;
    logoAsset.value = asset;
    formState.logo_url = asset?.url ?? '';
    formState.logo_file_id = asset?.id ?? 0;
  },
});

function getLogoName(url: string) {
  const normalized = String(url || '').trim();
  if (!normalized) {
    return 'logo';
  }

  try {
    const pathname = new URL(normalized).pathname;
    return pathname.split('/').filter(Boolean).pop() || 'logo';
  } catch {
    return normalized.split('/').filter(Boolean).pop() || 'logo';
  }
}

function createLogoAsset(url: string, id = 0): UploadAsset {
  const name = getLogoName(url);

  return {
    id,
    url,
    preview_url: url,
    download_url: url,
    hash: null,
    suffix: name.includes('.') ? name.split('.').pop() || '' : '',
    origin_name: name,
    object_name: name,
    storage_mode: 0,
    storage_path: '',
    mime_type: '',
    size_byte: 0,
    size_info: '',
  };
}

function toBool(value: unknown, defaultValue = true) {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value === 1;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (['1', 'true', 'yes', 'on'].includes(normalized)) return true;
    if (['0', 'false', 'no', 'off'].includes(normalized)) return false;
  }
  return defaultValue;
}

function toPlainObject(value: unknown): Record<string, any> | null {
  if (typeof value === 'string') {
    const text = value.trim();
    if (!text) {
      return null;
    }

    try {
      return toPlainObject(JSON.parse(text));
    } catch {
      return null;
    }
  }

  return value && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, any>)
    : null;
}

function normalizeSettingResponse(data: unknown): SettingApi.SystemSetting {
  const payload = toPlainObject((data as any)?.data ?? data) ?? {};
  const nestedAppMeta = toPlainObject(payload.app_meta);
  // 兼容接口返回 { app_meta: "{...}" } 或 { app_meta: {...} }，外层同名字段用于局部覆盖。
  const normalizedPayload = nestedAppMeta ? { ...nestedAppMeta, ...payload } : { ...payload };
  delete normalizedPayload.app_meta;
  const source = normalizedPayload as Partial<SettingApi.SystemSetting>;

  return {
    ...defaultSystemSetting,
    ...source,
    copyright_enable: toBool(source.copyright_enable, defaultSystemSetting.copyright_enable),
    logo_file_id: Number(source.logo_file_id || 0),
  };
}

function applySetting(data: unknown) {
  Object.assign(formState, normalizeSettingResponse(data));
  logoAsset.value = formState.logo_url ? createLogoAsset(formState.logo_url, formState.logo_file_id) : null;
}

async function loadSettings() {
  try {
    loading.value = true;
    applySetting(await settingApiService.getInfo());
  } catch (error) {
    console.error('load system setting failed', error);
    message.error('加载系统参数失败');
  } finally {
    loading.value = false;
  }
}

async function handleSave() {
  if (!canSaveSettings.value) {
    return;
  }

  try {
    await formRef.value?.validate();
    saving.value = true;
    applySetting(await settingApiService.updateInfo({ ...formState }));
    applyUiMetaPreferences(await dataApiService.getUiMeta());
    message.success('系统参数已保存');
  } catch (error) {
    if (error && typeof error === 'object' && 'errorFields' in error) {
      return;
    }

    console.error('save system setting failed', error);
    message.error('保存系统参数失败');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  loadSettings();
});
</script>

<style scoped>
.system-setting-toolbar {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.system-setting-toolbar__title {
  color: var(--ant-colorText);
  font-size: 18px;
  font-weight: 600;
}

.system-setting-toolbar__desc {
  margin-top: 0.25rem;
  color: var(--ant-colorTextSecondary);
  font-size: 14px;
}

.system-setting-preview {
  display: flex;
  gap: 1rem;
  align-items: center;
  min-height: 112px;
  padding: 1rem;
  border: 1px solid var(--ant-colorBorderSecondary);
  border-radius: 8px;
  background: var(--ant-colorFillQuaternary);
}

.system-setting-preview__logo,
.system-setting-preview__placeholder {
  width: 64px;
  height: 64px;
  flex: 0 0 auto;
  border-radius: 8px;
}

.system-setting-preview__logo {
  object-fit: contain;
  background: var(--ant-colorBgContainer);
}

.system-setting-preview__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ant-colorTextTertiary);
  background: var(--ant-colorFillSecondary);
  font-size: 24px;
}

.system-setting-preview__name {
  color: var(--ant-colorText);
  font-size: 16px;
  font-weight: 600;
}

.system-setting-preview__desc {
  margin-top: 0.25rem;
  color: var(--ant-colorText);
  font-size: 14px;
}

.system-setting-preview__sub {
  margin-top: 0.25rem;
  color: var(--ant-colorTextSecondary);
  font-size: 13px;
  line-height: 1.6;
}

@media (max-width: 640px) {
  .system-setting-toolbar {
    flex-direction: column;
  }
}
</style>
