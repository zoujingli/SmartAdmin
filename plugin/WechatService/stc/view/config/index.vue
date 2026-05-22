<template>
  <Page title="开放平台配置">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="canCreateAuthUrl" :loading="authCreating" @click="createAuthUrl"><span class="i-lucide-link" />授权链接</Button>
        <Button v-if="canSaveConfig" type="primary" :loading="saving" @click="save"><span class="i-lucide-save" />保存配置</Button>
      </Space>
    </template>

    <Form :model="form" layout="vertical">
      <CrudNoticeAlert v-if="!canSaveConfig" custom-class="mb-4" message="当前账号仅可查看开放平台配置，没有保存权限。" type="warning" />

      <Row :gutter="[16, 16]">
        <Col :lg="15" :span="24">
          <Card title="平台参数" :loading="loading">
            <Row :gutter="[16, 0]">
              <Col :md="12" :span="24">
                <FormItem label="配置名称">
                  <Input v-model:value="form.name" :disabled="!canSaveConfig" :maxlength="100" allow-clear placeholder="请输入配置名称" />
                </FormItem>
              </Col>
              <Col :md="12" :span="24">
                <FormItem label="Component AppID">
                  <Input v-model:value="form.component_appid" :disabled="!canSaveConfig" :maxlength="64" allow-clear placeholder="请输入第三方平台 AppID" />
                </FormItem>
              </Col>
              <Col :md="12" :span="24">
                <FormItem label="Component AppSecret">
                  <InputPassword v-model:value="form.component_appsecret" :disabled="!canSaveConfig" placeholder="请输入第三方平台 AppSecret" />
                </FormItem>
              </Col>
              <Col :md="12" :span="24">
                <FormItem label="消息 Token">
                  <InputPassword v-model:value="form.component_token" :disabled="!canSaveConfig" placeholder="请输入消息 Token" />
                </FormItem>
              </Col>
              <Col :md="12" :span="24">
                <FormItem label="EncodingAESKey">
                  <InputPassword v-model:value="form.component_encodingaeskey" :disabled="!canSaveConfig" placeholder="请输入 EncodingAESKey" />
                </FormItem>
              </Col>
              <Col :md="12" :span="24">
                <FormItem label="状态">
                  <Switch v-model:checked="enabled" :disabled="!canSaveConfig" checked-children="启用" un-checked-children="禁用" />
                </FormItem>
              </Col>
              <Col :span="24">
                <FormItem label="备注">
                  <Textarea v-model:value="form.remark" :disabled="!canSaveConfig" :maxlength="255" :rows="3" allow-clear placeholder="请输入备注" show-count />
                </FormItem>
              </Col>
            </Row>
          </Card>

          <Card class="mt-4" title="回调地址" :loading="loading">
            <Descriptions :column="1" bordered size="small">
              <DescriptionsItem v-for="item in absoluteCallbackUrls" :key="item.label" :label="callbackLabel(item.label)">
                <TypographyText copyable>{{ item.value }}</TypographyText>
              </DescriptionsItem>
            </Descriptions>
          </Card>
        </Col>

        <Col :lg="9" :span="24">
          <Card title="配置状态" :loading="loading">
            <div class="wechat-config-status">
              <div class="wechat-config-status__hero" :class="overallStatus.className">
                <div class="wechat-config-status__hero-icon">
                  <span :class="overallStatus.icon" />
                </div>
                <div class="wechat-config-status__hero-content">
                  <div class="wechat-config-status__hero-title">{{ overallStatus.title }}</div>
                  <div class="wechat-config-status__hero-desc">{{ overallStatus.desc }}</div>
                </div>
                <Tag :color="overallStatus.tagColor">{{ overallStatus.tagText }}</Tag>
              </div>

              <div class="wechat-config-progress">
                <div class="wechat-config-progress__header">
                  <span>配置完整度</span>
                  <strong>{{ readyCount }}/{{ statusItems.length }}</strong>
                </div>
                <Progress :percent="readyPercent" :show-info="false" :status="readyPercent >= 100 ? 'success' : 'active'" />
              </div>

              <div class="wechat-config-status__list">
                <div v-for="item in statusItems" :key="item.key" class="wechat-config-status__item">
                  <div class="wechat-config-status__item-icon" :class="item.ready ? 'is-ready' : 'is-pending'">
                    <span :class="item.ready ? 'i-lucide-check' : 'i-lucide-clock-3'" />
                  </div>
                  <div class="wechat-config-status__item-content">
                    <div class="wechat-config-status__item-title">{{ item.label }}</div>
                    <div class="wechat-config-status__item-desc">{{ item.desc }}</div>
                  </div>
                  <Tag :color="item.ready ? 'success' : 'default'">{{ item.ready ? '已完成' : '待处理' }}</Tag>
                </div>
              </div>
            </div>
          </Card>

          <Alert v-if="authUrl" class="mt-4" type="success" show-icon>
            <template #message>授权链接已生成</template>
            <template #description>
              <TypographyText copyable>{{ authUrl }}</TypographyText>
            </template>
          </Alert>

          <Card class="mt-4" title="接入说明">
            <div class="wechat-config-tips">
              <div>1. 在微信开放平台后台配置 Ticket 和消息回调地址。</div>
              <div>2. 消息回调必须使用带 $APPID$ 的地址，微信会把占位符替换为授权方 AppID。</div>
              <div>3. 保存 AppID、AppSecret、Token 和 EncodingAESKey 后等待 Ticket 推送。</div>
              <div>4. Ticket 到达后即可生成授权链接并绑定租户授权账号。</div>
            </div>
            <FormItem v-if="canCreateAuthUrl" class="mt-4 mb-0" label="授权租户 ID" extra="平台管理员生成授权链接时请填写目标租户 ID；租户空间可留空使用当前租户。">
              <InputNumber v-model:value="authTenantId" :min="1" class="w-full" placeholder="目标租户 ID" />
            </FormItem>
          </Card>
        </Col>
      </Row>
    </Form>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import { CrudNoticeAlert, Page } from '@vben/common-ui';
import { Alert, Button, Card, Col, Descriptions, DescriptionsItem, Form, FormItem, Input, InputNumber, InputPassword, message, Progress, Row, Space, Switch, Tag, Textarea, TypographyText } from 'ant-design-vue';

import { requestClient } from '#/api/request';

const { hasAccessByCodes } = useAccess();
const canSaveConfig = computed(() => hasAccessByCodes(['wechat.service.config.save']));
const canCreateAuthUrl = computed(() => hasAccessByCodes(['wechat.service.config.auth-url']));

const loading = ref(false);
const saving = ref(false);
const authCreating = ref(false);
const authUrl = ref('');
const authTenantId = ref<number>();
const callbackUrls = ref<Array<{ label: string; value: string }>>([]);
const form = reactive<Record<string, any>>({ name: '', component_appid: '', component_appsecret: '', component_token: '', component_encodingaeskey: '', status: 1, remark: '' });
const enabled = computed({ get: () => Number(form.status) === 1, set: (value: boolean) => { form.status = value ? 1 : 0; } });
const absoluteCallbackUrls = computed(() => callbackUrls.value.map((item) => ({ label: item.label, value: item.value.startsWith('http') ? item.value : `${window.location.origin}${item.value}` })));
// 配置状态面板只展示“是否可用”的聚合结果，敏感配置字段由后端返回掩码或 configured 标识，前端不能展示明文。
const statusItems = computed(() => [
  { key: 'appid', label: 'Component AppID', ready: Boolean(form.component_appid), desc: form.component_appid ? `当前：${maskText(String(form.component_appid))}` : '请填写第三方平台 AppID' },
  { key: 'secret', label: 'AppSecret', ready: Boolean(form.component_appsecret_configured || form.component_appsecret), desc: '用于获取开放平台调用凭证，保存后仅展示配置状态' },
  { key: 'token', label: '消息 Token', ready: Boolean(form.component_token_configured || form.component_token), desc: '用于校验微信开放平台回调签名' },
  { key: 'aes', label: 'EncodingAESKey', ready: Boolean(form.component_encodingaeskey_configured || form.component_encodingaeskey), desc: '用于解密 Ticket 和消息回调内容' },
  { key: 'ticket', label: 'Verify Ticket', ready: Boolean(form.component_verify_ticket_configured), desc: form.component_verify_ticket_configured ? '已收到微信开放平台 Ticket 推送' : '等待微信开放平台推送 Ticket' },
  { key: 'auth', label: '授权入口', ready: Boolean(authUrl.value), desc: authUrl.value ? '授权链接已生成，可复制给租户绑定授权账号' : '收到 Ticket 后可生成授权链接' },
]);
const readyCount = computed(() => statusItems.value.filter((item) => item.ready).length);
const readyPercent = computed(() => statusItems.value.length === 0 ? 0 : Math.round((readyCount.value / statusItems.value.length) * 100));
const overallStatus = computed(() => {
  if (!enabled.value) {
    return {
      className: 'is-disabled',
      desc: '当前配置已禁用，Ticket 回调、授权入口和消息网关不会对业务生效。',
      icon: 'i-lucide-circle-off',
      tagColor: 'warning',
      tagText: '禁用',
      title: '开放平台未启用',
    };
  }
  if (readyPercent.value >= 100) {
    return {
      className: 'is-ready',
      desc: '核心参数、Ticket 与授权入口均已就绪，可以为公众号或小程序发起授权。',
      icon: 'i-lucide-circle-check',
      tagColor: 'success',
      tagText: '就绪',
      title: '开放平台已就绪',
    };
  }

  return {
    className: 'is-pending',
    desc: '配置已启用，但仍有参数、Ticket 或授权入口待完成，请按下方清单逐项处理。',
    icon: 'i-lucide-loader-circle',
    tagColor: 'processing',
    tagText: '配置中',
    title: '开放平台配置中',
  };
});

function callbackLabel(label: string) {
  return { ticket: 'Ticket 回调', message: '消息回调', jsonrpc: 'JSON-RPC 网关' }[label] || label;
}

function maskText(value: string) {
  if (value.length <= 8) {
    return value;
  }

  return `${value.slice(0, 4)}****${value.slice(-4)}`;
}

async function load() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-service/config/index');
    Object.assign(form, data || {});
    callbackUrls.value = Object.entries(data?.callback_urls || {}).map(([label, value]) => ({ label, value: String(value) }));
  } finally { loading.value = false; }
}

async function save() {
  if (saving.value) return;
  saving.value = true;
  try {
    await requestClient.post('wechat-service/config/save', form);
    message.success('保存成功');
    await load();
  } finally { saving.value = false; }
}

async function createAuthUrl() {
  if (authCreating.value) return;
  const redirect = `${window.location.origin}/wechat-service/api/callback/auth`;
  authCreating.value = true;
  try {
    const data = await requestClient.post<any>('wechat-service/config/auth-url', { redirect_uri: redirect, tenant_id: authTenantId.value });
    authUrl.value = data?.url || '';
  } finally { authCreating.value = false; }
}

onMounted(load);
</script>

<style scoped>
.wechat-config-status {
  display: grid;
  gap: 18px;
}

.wechat-config-status__hero {
  align-items: center;
  border: 1px solid var(--ant-color-border-secondary);
  border-radius: 16px;
  display: flex;
  gap: 14px;
  overflow: hidden;
  padding: 16px;
  position: relative;
}

.wechat-config-status__hero::before {
  content: '';
  inset: 0;
  opacity: 0.12;
  pointer-events: none;
  position: absolute;
}

.wechat-config-status__hero.is-ready::before {
  background: linear-gradient(135deg, var(--ant-color-success) 0%, transparent 72%);
}

.wechat-config-status__hero.is-pending::before {
  background: linear-gradient(135deg, var(--ant-color-info) 0%, transparent 72%);
}

.wechat-config-status__hero.is-disabled::before {
  background: linear-gradient(135deg, var(--ant-color-warning) 0%, transparent 72%);
}

.wechat-config-status__hero-icon {
  align-items: center;
  background: var(--ant-color-bg-container);
  border: 1px solid var(--ant-color-border-secondary);
  border-radius: 14px;
  box-shadow: 0 8px 20px rgb(15 23 42 / 8%);
  display: flex;
  flex: 0 0 44px;
  font-size: 24px;
  height: 44px;
  justify-content: center;
  position: relative;
  width: 44px;
}

.wechat-config-status__hero.is-ready .wechat-config-status__hero-icon {
  color: var(--ant-color-success);
}

.wechat-config-status__hero.is-pending .wechat-config-status__hero-icon {
  color: var(--ant-color-info);
}

.wechat-config-status__hero.is-disabled .wechat-config-status__hero-icon {
  color: var(--ant-color-warning);
}

.wechat-config-status__hero-content {
  flex: 1;
  min-width: 0;
  position: relative;
}

.wechat-config-status__hero-title {
  color: var(--ant-color-text);
  font-size: 16px;
  font-weight: 600;
  line-height: 1.4;
}

.wechat-config-status__hero-desc {
  color: var(--ant-color-text-secondary);
  font-size: 13px;
  line-height: 1.7;
  margin-top: 4px;
}

.wechat-config-progress {
  background: var(--ant-color-fill-quaternary);
  border-radius: 14px;
  padding: 14px 16px;
}

.wechat-config-progress__header {
  align-items: center;
  color: var(--ant-color-text-secondary);
  display: flex;
  font-size: 13px;
  justify-content: space-between;
  margin-bottom: 8px;
}

.wechat-config-progress__header strong {
  color: var(--ant-color-text);
}

.wechat-config-status__list {
  display: grid;
  gap: 10px;
}

.wechat-config-status__item {
  align-items: center;
  border: 1px solid var(--ant-color-border-secondary);
  border-radius: 14px;
  display: flex;
  gap: 12px;
  padding: 12px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.wechat-config-status__item:hover {
  border-color: var(--ant-color-primary-border);
  box-shadow: 0 8px 24px rgb(15 23 42 / 8%);
  transform: translateY(-1px);
}

.wechat-config-status__item-icon {
  align-items: center;
  border-radius: 999px;
  display: flex;
  flex: 0 0 28px;
  font-size: 16px;
  height: 28px;
  justify-content: center;
  width: 28px;
}

.wechat-config-status__item-icon.is-ready {
  background: var(--ant-color-success-bg);
  color: var(--ant-color-success);
}

.wechat-config-status__item-icon.is-pending {
  background: var(--ant-color-fill-secondary);
  color: var(--ant-color-text-tertiary);
}

.wechat-config-status__item-content {
  flex: 1;
  min-width: 0;
}

.wechat-config-status__item-title {
  color: var(--ant-color-text);
  font-size: 14px;
  font-weight: 500;
  line-height: 1.4;
}

.wechat-config-status__item-desc {
  color: var(--ant-color-text-secondary);
  font-size: 12px;
  line-height: 1.6;
  margin-top: 2px;
}

.wechat-config-tips {
  color: var(--ant-color-text-secondary);
  display: grid;
  gap: 10px;
  line-height: 1.7;
}
</style>
