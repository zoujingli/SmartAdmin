<template>
  <Page title="接口网关">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="canSaveGateway" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增凭据</Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Alert v-if="plainSecret" class="mb-5" type="success" show-icon>
        <template #message>网关密钥仅本次显示</template>
        <template #description><TypographyText copyable>{{ plainSecret }}</TypographyText></template>
      </Alert>

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="Key / 凭据名称" /></SearchField>
          </Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
            <Space wrap>
              <Button type="primary" :loading="loading" @click="handleSearch"><span class="i-lucide-search" />搜索</Button>
              <Button :disabled="loading" @click="handleReset"><span class="i-lucide-refresh-cw" />重置</Button>
            </Space>
          </Col>
        </Row>
        <CrudFilterSummary :items="activeFilterItems" empty-text="当前显示全部网关凭据，可按调用 Key 或凭据名称筛选。" />
      </Card>

      <Card>
        <CrudTableHeader title="网关凭据" description="维护开放平台内部 JSON-RPC 调用凭据、密钥轮换和授权 AppID 白名单。" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="columns" :data-source="items" :loading="loading" :locale="buildCrudTableLocale('暂无网关凭据')" :pagination="pagination" :scroll="tableScroll" row-key="id" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'client_key'">
              <Tooltip :title="record.client_key" placement="topLeft"><div class="truncate">{{ record.client_key || '-' }}</div></Tooltip>
            </template>
            <template v-else-if="column.key === 'name'">
              <Tooltip :title="record.name" placement="topLeft"><div class="truncate">{{ record.name || '-' }}</div></Tooltip>
            </template>
            <template v-else-if="column.key === 'allowed_appids'">
              <Tooltip :title="formatAllowedAppids(record.allowed_appids)" placement="topLeft"><div class="truncate">{{ formatAllowedAppids(record.allowed_appids) }}</div></Tooltip>
            </template>
            <template v-else-if="column.key === 'status'">
              <Tag :color="Number(record.status) === 1 ? 'success' : 'default'">{{ Number(record.status) === 1 ? '启用' : '禁用' }}</Tag>
            </template>
            <template v-else-if="column.key === 'action'">
              <CrudTableActions :actions="gatewayActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </Card>

    <Drawer
      :open="open"
      title="网关凭据"
      :body-style="{ padding: '20px 24px 8px' }"
      width="min(760px, calc(100vw - 32px))"
      placement="right"
      @close="open = false"
    >
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :md="12" :span="24"><FormItem label="凭据名称"><Input v-model:value="form.name" :maxlength="100" allow-clear placeholder="请输入凭据名称" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="调用 Key"><Input v-model:value="form.client_key" :maxlength="80" allow-clear placeholder="留空自动生成" /></FormItem></Col>
          <Col :span="24"><FormItem label="允许 AppID"><Textarea v-model:value="allowedAppidsText" :rows="4" placeholder="多个 AppID 用换行或英文逗号分隔；留空表示不限制" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="状态"><Switch v-model:checked="enabled" checked-children="启用" un-checked-children="禁用" /></FormItem></Col>
          <Col :span="24"><FormItem label="备注"><Textarea v-model:value="form.remark" :maxlength="255" :rows="3" allow-clear show-count /></FormItem></Col>
        </Row>
      </Form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button @click="open = false">取消</Button>
          <Button type="primary" :loading="saving" @click="save">确定</Button>
        </div>
      </template>
    </Drawer>
  </Page>
</template>

<script setup lang="ts">
import type { CrudFilterSummaryItem } from '@vben/common-ui';

import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import { buildCrudTableLocale, CrudFilterSummary, CrudStatCards, CrudTableHeader, Page } from '@vben/common-ui';
import { Alert, Button, Card, Col, Drawer, Form, FormItem, Input, message, Row, Space, Switch, Table, Tag, Textarea, Tooltip, TypographyText } from 'ant-design-vue';

import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';

const { hasAccessByCodes } = useAccess();
const canSaveGateway = computed(() => hasAccessByCodes(['wechat.service.gateway.save']));
const canDeleteGateway = computed(() => hasAccessByCodes(['wechat.service.gateway.delete']));

const keyword = ref('');
const loading = ref(false);
const saving = ref(false);
const open = ref(false);
const editingId = ref<number | null>(null);
const plainSecret = ref('');
const allowedAppidsText = ref('');
const items = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const form = reactive<any>({ client_key: '', name: '', allowed_appids: [], status: 1, remark: '' });
const enabled = computed({ get: () => Number(form.status) === 1, set: (value: boolean) => { form.status = value ? 1 : 0; } });
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([gatewayActions({})], { maxWidth: 220 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '调用 Key', key: 'client_key', dataIndex: 'client_key', width: 240 },
  { title: '名称', key: 'name', dataIndex: 'name', width: 180 },
  { title: '允许 AppID', key: 'allowed_appids', width: 260 },
  { title: '调用次数', dataIndex: 'total', width: 100 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);
const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1280 }));

function gatewayActions(record: any) {
  return [
    { label: '编辑', visible: canSaveGateway.value, onClick: () => openEdit(record) },
    { label: '轮换', visible: canSaveGateway.value, onClick: () => rotate(record.id) },
    { label: '删除', visible: canDeleteGateway.value, danger: true, confirmTitle: '确认删除该网关凭据？', onClick: () => deleteRow(record.id) },
  ];
}
const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => keyword.value.trim() ? [{ label: '关键字', value: keyword.value.trim() }] : []);
const summaryCards = computed(() => {
  const enabledCount = items.value.filter((item) => Number(item.status) === 1).length;
  return [
    { label: '凭据总数', value: String(pagination.total), desc: '当前筛选条件下的网关凭据数量', icon: 'i-lucide-key-round', tone: 'primary' as const },
    { label: '本页启用', value: String(enabledCount), desc: '当前页启用的网关凭据', icon: 'i-lucide-circle-check', tone: 'success' as const },
    { label: '调用次数', value: String(items.value.reduce((sum, item) => sum + Number(item.total || 0), 0)), desc: '当前页网关调用次数合计', icon: 'i-lucide-activity', tone: 'info' as const },
    { label: '白名单', value: String(items.value.filter((item) => Array.isArray(item.allowed_appids) && item.allowed_appids.length > 0).length), desc: '当前页配置 AppID 白名单的凭据', icon: 'i-lucide-list-checks', tone: 'warning' as const },
  ];
});

function formatAllowedAppids(value: unknown) { return Array.isArray(value) && value.length > 0 ? value.join(', ') : '不限制'; }
function parseAllowedAppids() { return Array.from(new Set(allowedAppidsText.value.split(/[\s,，]+/u).map((item: string) => item.trim()).filter(Boolean))); }
async function load() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-service/gateway/index', { params: { page: pagination.current, pageSize: pagination.pageSize, keyword: keyword.value } });
    items.value = data?.items || [];
    pagination.total = data?.pageInfo?.total || 0;
  } finally { loading.value = false; }
}
function handleSearch() { pagination.current = 1; load(); }
function handleReset() { keyword.value = ''; handleSearch(); }
function openCreate() { editingId.value = null; plainSecret.value = ''; allowedAppidsText.value = ''; Object.assign(form, { client_key: '', name: '', allowed_appids: [], status: 1, remark: '' }); open.value = true; }
function openEdit(record: any) { editingId.value = record.id; plainSecret.value = ''; allowedAppidsText.value = (record.allowed_appids || []).join('\n'); Object.assign(form, record); open.value = true; }
async function save() {
  const allowed = parseAllowedAppids();
  if (allowed.some((appid) => appid.length > 64)) {
    message.error('允许 AppID 最多 64 位');
    return;
  }
  saving.value = true;
  try {
    const payload = { ...form, allowed_appids: allowed };
    const data = editingId.value ? await requestClient.put<any>(`wechat-service/gateway/update/${editingId.value}`, payload) : await requestClient.post<any>('wechat-service/gateway/create', payload);
    plainSecret.value = data?.client_secret || '';
    message.success('保存成功');
    open.value = false;
    await load();
  } finally { saving.value = false; }
}
async function rotate(id: number) {
  const data = await requestClient.put<any>(`wechat-service/gateway/rotate/${id}`);
  plainSecret.value = data?.client_secret || '';
  message.success('密钥已轮换');
}
async function deleteRow(id: number) {
  await requestClient.delete(`wechat-service/gateway/delete/${id}`);
  message.success('删除成功');
  await load();
}
function onTableChange(pag: any) { pagination.current = pag.current; pagination.pageSize = pag.pageSize; load(); }
onMounted(load);
</script>
