<template>
  <Page title="接口应用">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button type="primary" @click="openCreate"><span class="i-lucide-plus" />新增应用</Button>
      </Space>
    </template>

    <Card class="website-app-page crud-page-shell">
      <Alert
        class="website-app-page__intro"
        show-icon
        message="管理第三方调用官网开放 API 的 AppID 与 AppKey。"
        description="AppKey 创建或重置后只显示一次；开放接口全部要求 HMAC-SHA256 签名，并按应用绑定站点隔离数据。"
      />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
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
            <Space wrap><Button type="primary" :loading="loading" @click="handleSearch">搜索</Button><Button :disabled="loading" @click="handleReset">重置</Button></Space>
          </Col>
        </Row>
      </Card>

      <Card>
        <CrudTableHeader title="接口应用列表" description="调用方凭证按站点绑定；禁用后对应开放接口立即不可用。" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="columns" :data-source="items" :loading="loading" :pagination="pagination" :scroll="{ x: 1280 }" row-key="id" @change="onTableChange">
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

    <Drawer :open="drawerOpen" :title="form.id ? '编辑接口应用' : '新增接口应用'" :width="popupWidth.lg" @close="drawerOpen = false">
      <Alert class="mb-4" show-icon type="info" message="线索提交属于写入权限，默认不勾选；IP 白名单为空表示不限制来源 IP。" />
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :md="12" :span="24"><FormItem label="所属站点" required><Select v-model:value="form.site_id" show-search option-filter-prop="label" class="w-full" placeholder="请选择站点"><SelectOption v-for="item in siteOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption></Select></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="应用名称" required><Input v-model:value="form.name" :maxlength="120" allow-clear placeholder="例如 官网前端服务端" /></FormItem></Col>
          <Col :span="24"><FormItem label="接口权限" required><Select v-model:value="form.scopes" mode="multiple" class="w-full" placeholder="请选择接口权限"><SelectOption v-for="item in scopeOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption></Select></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="每分钟限流"><InputNumber v-model:value="form.rate_limit" class="w-full" :min="0" :max="100000" placeholder="0 表示不限流" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="当前状态"><Select v-model:value="form.status" class="w-full"><SelectOption v-for="item in enabledStatusOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption></Select></FormItem></Col>
          <Col :span="24"><FormItem label="IP白名单"><Textarea v-model:value="ipWhitelistText" :auto-size="{ minRows: 3, maxRows: 8 }" :maxlength="2000" show-count allow-clear placeholder="一行一个 IP 或 IPv4 CIDR，例如 203.0.113.10 或 203.0.113.0/24；留空不限" /></FormItem></Col>
          <Col :span="24"><FormItem label="备注"><Textarea v-model:value="form.remark" :auto-size="{ minRows: 3, maxRows: 8 }" :maxlength="1000" show-count allow-clear placeholder="填写调用方、用途或对接人" /></FormItem></Col>
        </Row>
      </Form>
      <template #footer><div class="flex justify-end gap-3"><Button @click="drawerOpen = false">取消</Button><Button type="primary" :loading="saving" @click="save">保存</Button></div></template>
    </Drawer>

    <Modal v-model:open="secretOpen" title="请立即保存开放接口凭证" :footer="null" width="680px">
      <Alert class="mb-4" type="warning" show-icon message="AppKey 明文只显示一次，关闭后无法再次查看；如遗失只能重置密钥。" />
      <Descriptions bordered :column="1" size="small">
        <DescriptionsItem label="AppID"><TypographyText copyable code>{{ secret.app_id }}</TypographyText></DescriptionsItem>
        <DescriptionsItem label="AppKey"><TypographyText copyable code>{{ secret.app_key }}</TypographyText></DescriptionsItem>
      </Descriptions>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { CrudTableHeader, Page } from '@vben/common-ui';
import { Alert, Button, Card, Col, Descriptions, DescriptionsItem, Drawer, Form, FormItem, Input, InputNumber, message, Modal, Row, Select, SelectOption, Space, Table, Tag, Textarea, TypographyText } from 'ant-design-vue';
import { requestClient } from '#/api/request';
import CrudTableActions from '#/components/crud-table-actions.vue';
import SearchField from '#/components/crud-search-field.vue';
import { popupWidth } from '#/utils/popup';
import { enabledStatusOptions, pageParams, splitStringList, statusColor, statusText } from '../website-api';

const loading = ref(false); const saving = ref(false); const drawerOpen = ref(false); const secretOpen = ref(false);
const keyword = ref(''); const items = ref<any[]>([]); const siteOptions = ref<any[]>([]); const scopeOptions = ref<any[]>([]);
const filters = reactive<Record<string, any>>({ site_id: undefined, status: undefined });
const form = reactive<Record<string, any>>({}); const secret = reactive({ app_id: '', app_key: '' });
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const ipWhitelistText = computed({ get: () => (form.ip_whitelist || []).join('\n'), set: (value: string) => { form.ip_whitelist = splitStringList(value); } });
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
  { title: '操作', key: 'action', width: 190, fixed: 'right' as const },
];

function queryParams(page = pagination.current, pageSize = pagination.pageSize) {
  const payload: Record<string, any> = pageParams({ current: page, pageSize }, { keyword: keyword.value, ...filters });
  return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== ''));
}
async function loadOptions() { [siteOptions.value, scopeOptions.value] = await Promise.all([requestClient.get<any[]>('system/website/site/options'), requestClient.get<any[]>('system/website/app/scope-options')]); }
async function load() { if (loading.value) return; loading.value = true; try { const data = await requestClient.get<any>('system/website/app/index', { params: queryParams() }); items.value = data?.items || []; pagination.total = data?.pageInfo?.total || 0; } finally { loading.value = false; } }
function handleSearch() { pagination.current = 1; void load(); }
function handleReset() { keyword.value = ''; filters.site_id = undefined; filters.status = undefined; handleSearch(); }
function onTableChange(page: any) { pagination.current = page.current; pagination.pageSize = page.pageSize; void load(); }
function resetForm(record: any = {}) { Object.keys(form).forEach((key) => delete form[key]); Object.assign(form, { id: record.id || 0, site_id: record.site_id || undefined, name: record.name || '', scopes: record.scopes || scopeOptions.value.filter((item) => item.value !== 'lead:create').map((item) => item.value), ip_whitelist: record.ip_whitelist || [], rate_limit: record.rate_limit ?? 60, status: record.status ?? 1, remark: record.remark || '' }); }
function openCreate() { resetForm(); drawerOpen.value = true; }
function openEdit(record: any) { resetForm(record); drawerOpen.value = true; }
function showSecret(data: any) { secret.app_id = data?.app_id || ''; secret.app_key = data?.app_key || ''; secretOpen.value = Boolean(secret.app_id && secret.app_key); }
async function save() { if (saving.value) return; saving.value = true; try { const payload = { site_id: form.site_id, name: form.name, scopes: form.scopes || [], ip_whitelist: form.ip_whitelist || [], rate_limit: form.rate_limit ?? 60, status: form.status ?? 1, remark: form.remark || '' }; const data = form.id ? await requestClient.put<any>(`system/website/app/update/${form.id}`, payload) : await requestClient.post<any>('system/website/app/create', payload); message.success('保存成功'); drawerOpen.value = false; if (!form.id) showSecret(data); await load(); } finally { saving.value = false; } }
async function deleteRow(id: number) { await requestClient.delete(`system/website/app/delete/${id}`); message.success('删除成功'); await load(); }
async function toggleStatus(record: any) { const next = Number(record.status) === 1 ? 0 : 1; await requestClient.put(`system/website/app/status/${record.id}`, { status: next }); message.success(next === 1 ? '已启用' : '已禁用'); await load(); }
async function resetKey(record: any) { const data = await requestClient.put<any>(`system/website/app/reset-key/${record.id}`, {}); showSecret(data); await load(); }
function rowActions(record: any) { const id = Number(record.id || 0); return [
  { label: '编辑', visible: id > 0, onClick: () => openEdit(record) },
  { label: '重置密钥', visible: id > 0, danger: true, confirmTitle: '确认重置该应用密钥？', confirmContent: '重置后旧 AppKey 立即失效，请确认第三方已准备切换。', onClick: () => resetKey(record) },
  { label: Number(record.status) === 1 ? '禁用' : '启用', visible: id > 0, danger: Number(record.status) === 1, confirmTitle: `确认${Number(record.status) === 1 ? '禁用' : '启用'}该应用？`, confirmContent: '状态变更会立即影响开放 API 调用。', onClick: () => toggleStatus(record) },
  { label: '删除', visible: id > 0, danger: true, confirmTitle: '确认删除该接口应用？', confirmContent: '删除后对应 AppID 将无法调用开放 API。', onClick: () => deleteRow(id) },
]; }

onMounted(async () => { await loadOptions(); await load(); });
</script>

<style scoped>
.website-app-page__intro {
  margin-bottom: 16px;
}
</style>
