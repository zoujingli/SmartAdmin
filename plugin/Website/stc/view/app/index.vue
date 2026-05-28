<template>
  <Page title="接口应用">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="activeLoading" @click="loadActive"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="activeTab === 'data' && canCreateApp" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增应用</Button>
      </Space>
    </template>

    <Card class="website-app-page crud-page-shell">
      <Alert
        class="website-app-page__intro"
        show-icon
        message="管理第三方调用官网开放 API 的 AppID 与 AppKey。"
        description="AppKey 创建或重置后只显示一次；新接入统一使用 Authorization: Website-HMAC ... 标准签名头，并按应用绑定站点隔离数据。"
      />

      <Card class="mb-5">
        <Row class="crud-search-grid" :gutter="[16, 16]">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="应用名称、AppID 或备注" @press-enter="handleSearch" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="5">
            <SearchField label="所属站点">
              <Select v-model:value="filters.site_id" allow-clear show-search option-filter-prop="label" class="w-full" placeholder="请选择站点">
                <SelectOption v-for="item in siteOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption>
              </Select>
            </SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="4">
            <SearchField label="当前状态">
              <Select v-model:value="filters.status" allow-clear class="w-full" placeholder="请选择状态">
                <SelectOption v-for="item in enabledStatusOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption>
              </Select>
            </SearchField>
          </Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="8">
            <Space wrap><Button type="primary" :loading="activeLoading" @click="handleSearch">搜索</Button><Button :disabled="activeLoading" @click="handleReset">重置</Button></Space>
          </Col>
        </Row>
      </Card>

      <Card>
        <Tabs v-if="canRecoveryApp || canRealDeleteApp" v-model:activeKey="activeTab" class="website-app-page__tabs" @change="onTabChange">
          <TabPane key="data" tab="数据列表" />
          <TabPane key="recycle" tab="回收站" />
        </Tabs>
        <CrudTableHeader :title="activeListTitle" :description="activeListDescription" :count-text="`${activePagination.total} 条记录`" />
        <Table :columns="tableColumns" :data-source="activeItems" :loading="activeLoading" :pagination="activePagination" :scroll="{ x: 1280 }" row-key="id" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'app_id'"><TypographyText copyable code>{{ record.app_id }}</TypographyText></template>
            <template v-else-if="column.key === 'scopes'">
              <Space wrap size="small"><Tag v-for="item in record.scope_texts || []" :key="item">{{ item }}</Tag></Space>
            </template>
            <template v-else-if="column.key === 'ip_whitelist'">
              <Space v-if="record.ip_whitelist?.length" wrap size="small"><Tag v-for="item in record.ip_whitelist" :key="item">{{ item }}</Tag></Space><span v-else>不限</span>
            </template>
            <template v-else-if="column.key === 'status'"><Tag :color="statusColor(record.status)">{{ statusText(record.status) }}</Tag></template>
            <template v-else-if="column.key === 'action'"><CrudTableActions :actions="rowActions(record)" /></template>
          </template>
        </Table>
      </Card>
    </Card>

    <AppDrawer :confirm-loading="saving" :open="drawerOpen" :title="form.id ? '编辑接口应用' : '新增接口应用'" width-size="lg" @close="drawerOpen = false" @ok="save">
      <Alert class="mb-4" show-icon type="info" message="线索提交属于写入权限，默认不勾选；IP 白名单为空表示不限制来源 IP。" />
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :md="12" :span="24"><FormItem label="所属站点" required><Select v-model:value="form.site_id" show-search option-filter-prop="label" class="w-full" placeholder="请选择站点"><SelectOption v-for="item in siteOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption></Select></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="应用名称" required><Input v-model:value="form.name" :maxlength="120" allow-clear placeholder="例如 官网前端服务端" /></FormItem></Col>
          <Col :span="24">
            <FormItem label="接口权限" required>
              <div class="website-app-page__scope-panel">
                <div class="website-app-page__scope-toolbar">
                  <div class="website-app-page__scope-tip">按接口能力展开勾选；线索提交会写入访客数据，需确认调用方确实需要。</div>
                  <Space wrap size="small">
                    <Button size="small" type="link" @click="selectReadScopes">勾选全部读取权限</Button>
                    <Button size="small" type="link" danger @click="clearScopes">清空权限</Button>
                  </Space>
                </div>
                <div class="website-app-page__scope-section">
                  <div class="website-app-page__scope-title">读取权限</div>
                  <div class="website-app-page__scope-grid">
                    <div v-for="item in readScopeOptions" :key="item.value" class="website-app-page__scope-card" :class="{ 'is-checked': scopeChecked(item.value) }">
                      <Checkbox :checked="scopeChecked(item.value)" @change="onScopeChange(item.value, $event)">
                        <span class="website-app-page__scope-name">{{ item.label }}</span>
                      </Checkbox>
                      <span class="website-app-page__scope-desc">{{ scopeDescription(item.value) }}</span>
                    </div>
                  </div>
                </div>
                <div class="website-app-page__scope-section">
                  <div class="website-app-page__scope-title">写入权限</div>
                  <div class="website-app-page__scope-grid">
                    <div v-for="item in writeScopeOptions" :key="item.value" class="website-app-page__scope-card website-app-page__scope-card--danger" :class="{ 'is-checked': scopeChecked(item.value) }">
                      <Checkbox :checked="scopeChecked(item.value)" @change="onScopeChange(item.value, $event)">
                        <span class="website-app-page__scope-name">{{ item.label }}</span>
                      </Checkbox>
                      <span class="website-app-page__scope-desc">{{ scopeDescription(item.value) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </FormItem>
          </Col>
          <Col :md="12" :span="24"><FormItem label="每分钟限流"><InputNumber v-model:value="form.rate_limit" class="w-full" :min="0" :max="100000" placeholder="0 表示不限流" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="当前状态"><Select v-model:value="form.status" class="w-full"><SelectOption v-for="item in enabledStatusOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption></Select></FormItem></Col>
          <Col :span="24"><FormItem label="IP白名单"><Textarea v-model:value="ipWhitelistText" :auto-size="{ minRows: 3, maxRows: 8 }" :maxlength="2000" show-count allow-clear placeholder="一行一个 IP 或 IPv4 CIDR，例如 203.0.113.10 或 203.0.113.0/24；留空不限" /></FormItem></Col>
          <Col :span="24"><FormItem label="备注"><Textarea v-model:value="form.remark" :auto-size="{ minRows: 3, maxRows: 8 }" :maxlength="1000" show-count allow-clear placeholder="填写调用方、用途或对接人" /></FormItem></Col>
        </Row>
      </Form>
    </AppDrawer>

    <Modal v-model:open="secretOpen" title="请立即保存开放接口凭证" :footer="null" width="680px">
      <Alert class="mb-4" type="warning" show-icon message="AppKey 明文只显示一次，关闭后无法再次查看；如遗失只能重置密钥。" />
      <Descriptions bordered :column="1" size="small">
        <DescriptionsItem label="AppID"><TypographyText copyable code>{{ secret.app_id }}</TypographyText></DescriptionsItem>
        <DescriptionsItem label="AppKey"><TypographyText copyable code>{{ secret.app_key }}</TypographyText></DescriptionsItem>
        <DescriptionsItem label="认证头">
          <TypographyText copyable code>{{ authorizationExample }}</TypographyText>
        </DescriptionsItem>
      </Descriptions>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import { CrudTableHeader, Page } from '@vben/common-ui';
import { Alert, Button, Card, Checkbox, Col, Descriptions, DescriptionsItem, Form, FormItem, Input, InputNumber, message, Modal, Row, Select, SelectOption, Space, Table, Tabs, TabPane, Tag, Textarea, TypographyText } from 'ant-design-vue';
import { requestClient } from '#/api/request';
import CrudTableActions from '#/components/crud-table-actions.vue';
import SearchField from '#/components/crud-search-field.vue';
import { enabledStatusOptions, pageParams, splitStringList, statusColor, statusText } from '../website-api';
import AppDrawer from '#/components/app-drawer.vue';

const { hasAccessByCodes } = useAccess();
const loading = ref(false); const loadingRecycle = ref(false); const saving = ref(false); const drawerOpen = ref(false); const secretOpen = ref(false);
const activeTab = ref<'data' | 'recycle'>('data');
const keyword = ref(''); const items = ref<any[]>([]); const recycleItems = ref<any[]>([]); const siteOptions = ref<any[]>([]); const scopeOptions = ref<any[]>([]);
const filters = reactive<Record<string, any>>({ site_id: undefined, status: undefined });
const form = reactive<Record<string, any>>({}); const secret = reactive({ app_id: '', app_key: '' });
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const recyclePagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const ipWhitelistText = computed({ get: () => (form.ip_whitelist || []).join('\n'), set: (value: string) => { form.ip_whitelist = splitStringList(value); } });
const authorizationExample = computed(() => `Authorization: Website-HMAC appid="${secret.app_id || '${WEBSITE_APPID}'}", timestamp="\${TIMESTAMP}", nonce="\${NONCE}", signature="\${SIGN}"`);
const readScopeOptions = computed(() => scopeOptions.value.filter((item) => String(item.value) !== 'lead:create'));
const writeScopeOptions = computed(() => scopeOptions.value.filter((item) => String(item.value) === 'lead:create'));
const canCreateApp = computed(() => hasAccessByCodes(['website.app.create']));
const canUpdateApp = computed(() => hasAccessByCodes(['website.app.update']));
const canResetKeyApp = computed(() => hasAccessByCodes(['website.app.reset-key']));
const canStatusApp = computed(() => hasAccessByCodes(['website.app.status']));
const canDeleteApp = computed(() => hasAccessByCodes(['website.app.delete']));
const canRecoveryApp = computed(() => hasAccessByCodes(['website.app.recovery']));
const canRealDeleteApp = computed(() => hasAccessByCodes(['website.app.real-delete']));
const hasActions = computed(() => activeTab.value === 'recycle' ? (canRecoveryApp.value || canRealDeleteApp.value) : (canUpdateApp.value || canResetKeyApp.value || canStatusApp.value || canDeleteApp.value));
const activeItems = computed(() => activeTab.value === 'recycle' ? recycleItems.value : items.value);
const activePagination = computed(() => activeTab.value === 'recycle' ? recyclePagination : pagination);
const activeLoading = computed(() => activeTab.value === 'recycle' ? loadingRecycle.value : loading.value);
const activeListTitle = computed(() => activeTab.value === 'recycle' ? '已删除接口应用' : '接口应用列表');
const activeListDescription = computed(() => activeTab.value === 'recycle' ? '回收站中的接口应用可恢复；彻底删除后对应凭证无法找回。' : '调用方凭证按站点绑定；禁用后对应开放接口立即不可用。');
const columns = [
  { title: '应用名称', dataIndex: 'name', width: 180 },
  { title: '所属站点', dataIndex: ['site', 'name'], width: 140 },
  { title: 'AppID', key: 'app_id', dataIndex: 'app_id', width: 220 },
  { title: '权限范围', key: 'scopes', dataIndex: 'scopes', width: 260 },
  { title: 'IP白名单', key: 'ip_whitelist', dataIndex: 'ip_whitelist', width: 220 },
  { title: '限流/分钟', dataIndex: 'rate_limit', width: 100 },
  { title: '最后调用', dataIndex: 'last_used_at', width: 170 },
  { title: '最后IP', dataIndex: 'last_used_ip', width: 140 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 90 },
];
const tableColumns = computed(() => hasActions.value ? [...columns, { title: '操作', key: 'action', width: activeTab.value === 'recycle' ? 150 : 190, fixed: 'right' as const }] : columns);

function queryParams(page = pagination.current, pageSize = pagination.pageSize) {
  const payload: Record<string, any> = pageParams({ current: page, pageSize }, { keyword: keyword.value, ...filters });
  return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== ''));
}
async function loadOptions() { [siteOptions.value, scopeOptions.value] = await Promise.all([requestClient.get<any[]>('system/website/site/options'), requestClient.get<any[]>('system/website/app/scope-options')]); }
async function load() { if (loading.value) return; loading.value = true; try { const data = await requestClient.get<any>('system/website/app/index', { params: queryParams() }); items.value = data?.items || []; pagination.total = data?.pageInfo?.total || 0; } finally { loading.value = false; } }
async function loadRecycle() { if (loadingRecycle.value || (!canRecoveryApp.value && !canRealDeleteApp.value)) return; loadingRecycle.value = true; try { const data = await requestClient.get<any>('system/website/app/recycle', { params: queryParams(recyclePagination.current, recyclePagination.pageSize) }); recycleItems.value = data?.items || []; recyclePagination.total = data?.pageInfo?.total || 0; } finally { loadingRecycle.value = false; } }
async function loadActive() { if (activeTab.value === 'recycle') await loadRecycle(); else await load(); }
function handleSearch() { if (activeTab.value === 'recycle') recyclePagination.current = 1; else pagination.current = 1; void loadActive(); }
function handleReset() { keyword.value = ''; filters.site_id = undefined; filters.status = undefined; handleSearch(); }
function onTabChange() { void loadActive(); }
function onTableChange(page: any) { if (activeTab.value === 'recycle') { recyclePagination.current = page.current; recyclePagination.pageSize = page.pageSize; } else { pagination.current = page.current; pagination.pageSize = page.pageSize; } void loadActive(); }
function resetForm(record: any = {}) { Object.keys(form).forEach((key) => delete form[key]); Object.assign(form, { id: record.id || 0, site_id: record.site_id || undefined, name: record.name || '', scopes: record.scopes || scopeOptions.value.filter((item) => item.value !== 'lead:create').map((item) => item.value), ip_whitelist: record.ip_whitelist || [], rate_limit: record.rate_limit ?? 60, status: record.status ?? 1, remark: record.remark || '' }); }
function normalizedScopes() { return Array.isArray(form.scopes) ? form.scopes.map((item) => String(item)) : []; }
function scopeChecked(value: any) { return normalizedScopes().includes(String(value)); }
function onScopeChange(value: any, event: { target?: { checked?: boolean } }) {
  const scopes = new Set(normalizedScopes());
  const scope = String(value);
  if (event?.target?.checked) scopes.add(scope); else scopes.delete(scope);
  form.scopes = Array.from(scopes);
}
function selectReadScopes() { form.scopes = Array.from(new Set([...normalizedScopes(), ...readScopeOptions.value.map((item) => String(item.value))])); }
function clearScopes() { form.scopes = []; }
function scopeDescription(value: any) {
  return ({
    'site:read': '允许读取站点基础资料、SEO 与联系方式。',
    'nav:read': '允许读取顶部、底部等导航树。',
    'channel:read': '允许读取官网栏目树与栏目详情。',
    'page:read': '允许读取页面详情和页面区块组合数据。',
    'content:read': '允许读取新闻、案例、产品、方案等内容。',
    'block:read': '允许读取首页或栏目页区块数据。',
    'lead:create': '允许提交访客留言、手机号、邮箱等线索信息。',
  } as Record<string, string>)[String(value)] || '允许调用对应开放接口。';
}
function openCreate() { resetForm(); drawerOpen.value = true; }
function openEdit(record: any) { resetForm(record); drawerOpen.value = true; }
function showSecret(data: any) { secret.app_id = data?.app_id || ''; secret.app_key = data?.app_key || ''; secretOpen.value = Boolean(secret.app_id && secret.app_key); }
async function save() { if (saving.value) return; saving.value = true; try { const payload = { site_id: form.site_id, name: form.name, scopes: form.scopes || [], ip_whitelist: form.ip_whitelist || [], rate_limit: form.rate_limit ?? 60, status: form.status ?? 1, remark: form.remark || '' }; const data = form.id ? await requestClient.put<any>(`system/website/app/update/${form.id}`, payload) : await requestClient.post<any>('system/website/app/create', payload); message.success('保存成功'); drawerOpen.value = false; if (!form.id) showSecret(data); await load(); } finally { saving.value = false; } }
async function deleteRow(id: number) { await requestClient.delete(`system/website/app/delete/${id}`); message.success('删除成功'); await load(); }
async function toggleStatus(record: any) { const next = Number(record.status) === 1 ? 0 : 1; await requestClient.put(`system/website/app/status/${record.id}`, { status: next }); message.success(next === 1 ? '已启用' : '已禁用'); await load(); }
async function resetKey(record: any) { const data = await requestClient.put<any>(`system/website/app/reset-key/${record.id}`, {}); showSecret(data); await load(); }
async function recoveryRow(id: number) { await requestClient.put(`system/website/app/recovery/${id}`, {}); message.success('恢复成功'); await loadRecycle(); await load(); }
async function realDeleteRow(id: number) { await requestClient.delete(`system/website/app/real-delete/${id}`); message.success('彻底删除成功'); await loadRecycle(); }
function rowActions(record: any) { const id = Number(record.id || 0); return [
  ...(activeTab.value === 'recycle'
    ? [
        { label: '恢复', visible: canRecoveryApp.value && id > 0, onClick: () => recoveryRow(id) },
        { label: '彻底删除', visible: canRealDeleteApp.value && id > 0, danger: true, confirmTitle: '确认彻底删除该接口应用？', confirmContent: '彻底删除后无法在后台恢复，请确认对应第三方凭证已经废弃。', onClick: () => realDeleteRow(id) },
      ]
    : [
        { label: '编辑', visible: canUpdateApp.value && id > 0, onClick: () => openEdit(record) },
        { label: '重置密钥', visible: canResetKeyApp.value && id > 0, danger: true, confirmTitle: '确认重置该应用密钥？', confirmContent: '重置后旧 AppKey 立即失效，请确认第三方已准备切换。', onClick: () => resetKey(record) },
        { label: Number(record.status) === 1 ? '禁用' : '启用', visible: canStatusApp.value && id > 0, danger: Number(record.status) === 1, confirmTitle: `确认${Number(record.status) === 1 ? '禁用' : '启用'}该应用？`, confirmContent: '状态变更会立即影响开放 API 调用。', onClick: () => toggleStatus(record) },
        { label: '删除', visible: canDeleteApp.value && id > 0, danger: true, confirmTitle: '确认删除该接口应用？', confirmContent: '删除后对应 AppID 将无法调用开放 API，可在回收站恢复。', onClick: () => deleteRow(id) },
      ]),
]; }

onMounted(async () => { await loadOptions(); await load(); });
</script>

<style scoped>
.website-app-page__intro {
  margin-bottom: 16px;
}

.website-app-page__scope-panel {
  padding: 14px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 8px;
  background: var(--ant-colorFillAlter, hsl(var(--muted) / 30%));
}

.website-app-page__scope-toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.website-app-page__scope-tip,
.website-app-page__scope-desc {
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  font-size: 12px;
  line-height: 1.5;
}

.website-app-page__scope-section + .website-app-page__scope-section {
  margin-top: 14px;
}

.website-app-page__scope-title {
  margin-bottom: 8px;
  color: var(--ant-colorText, hsl(var(--foreground)));
  font-weight: 600;
}

.website-app-page__scope-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
}

.website-app-page__scope-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-height: 82px;
  padding: 12px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 8px;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.website-app-page__scope-card.is-checked {
  border-color: var(--ant-colorPrimary, hsl(var(--primary)));
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--ant-colorPrimary, hsl(var(--primary))) 18%, transparent);
}

.website-app-page__scope-card--danger.is-checked {
  border-color: var(--ant-colorWarning, #faad14);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--ant-colorWarning, #faad14) 24%, transparent);
}

.website-app-page__scope-name {
  color: var(--ant-colorText, hsl(var(--foreground)));
  font-weight: 500;
}

@media (max-width: 768px) {
  .website-app-page__scope-toolbar {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
