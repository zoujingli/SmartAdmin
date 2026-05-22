<template>
  <Page title="微信接口账号">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load">
          <span class="i-lucide-refresh-cw" />刷新
        </Button>
        <Button v-if="canCreateAccount" type="primary" @click="openCreate">
          <span class="i-lucide-plus" />新增账号
        </Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="AppID / 账号名称" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="4">
            <SearchField label="当前状态">
              <Select v-model:value="statusFilter" allow-clear class="w-full" placeholder="请选择">
                <SelectOption :value="1">启用</SelectOption>
                <SelectOption :value="0">禁用</SelectOption>
              </Select>
            </SearchField>
          </Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
            <Space wrap>
              <Button type="primary" :loading="loading" @click="handleSearch">
                <span class="i-lucide-search" />搜索
              </Button>
              <Button :disabled="loading" @click="handleReset">
                <span class="i-lucide-refresh-cw" />重置
              </Button>
            </Space>
          </Col>
        </Row>
        <CrudFilterSummary
          :items="activeFilterItems"
          empty-text="当前显示全部微信接口账号，可按 AppID、账号名称或启用状态快速筛选。"
        />
      </Card>

      <Card>
        <CrudTableHeader
          title="接口账号台账"
          description="维护租户侧公众号与小程序接口账号、接入模式和消息安全参数。"
          :count-text="`${pagination.total} 条记录`"
        />
        <Table
          :columns="columns"
          :data-source="items"
          :loading="loading"
          :locale="buildCrudTableLocale('暂无微信接口账号')"
          :pagination="pagination"
          :scroll="tableScroll"
          row-key="id"
          @change="onTableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'appid'">
              <Tooltip :title="record.appid" placement="topLeft">
                <div class="truncate">{{ record.appid || '-' }}</div>
              </Tooltip>
            </template>
            <template v-else-if="column.key === 'name'">
              <Tooltip :title="record.name" placement="topLeft">
                <div class="truncate">{{ record.name || '-' }}</div>
              </Tooltip>
            </template>
            <template v-else-if="column.key === 'account_type'">
              <Tag color="blue">{{ accountTypeText(record.account_type) }}</Tag>
            </template>
            <template v-else-if="column.key === 'service_mode'">
              <Tag :color="Number(record.service_mode) === 1 ? 'purple' : 'default'">
                {{ serviceModeText(record.service_mode) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'status'">
              <Tag :color="Number(record.status) === 1 ? 'success' : 'default'">
                {{ Number(record.status) === 1 ? '启用' : '禁用' }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'action'">
              <CrudTableActions :actions="accountActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </Card>

    <Drawer
      :open="open"
      :title="editingId ? '编辑接口账号' : '新增接口账号'"
      :body-style="{ padding: '20px 24px 8px' }"
      width="min(760px, calc(100vw - 32px))"
      placement="right"
      @close="open = false"
    >
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :md="12" :span="24">
            <FormItem label="账号名称">
              <Input v-model:value="form.name" :maxlength="120" allow-clear placeholder="请输入账号名称" />
            </FormItem>
          </Col>
          <Col :md="12" :span="24">
            <FormItem label="AppID">
              <Input v-model:value="form.appid" :maxlength="64" allow-clear placeholder="请输入微信 AppID" />
            </FormItem>
          </Col>
          <Col :md="12" :span="24">
            <FormItem label="账号类型">
              <Select v-model:value="form.account_type" class="w-full">
                <SelectOption value="official_account">公众号</SelectOption>
                <SelectOption value="mini_program">小程序</SelectOption>
              </Select>
            </FormItem>
          </Col>
          <Col :md="12" :span="24">
            <FormItem label="接入模式">
              <Select v-model:value="form.service_mode" class="w-full">
                <SelectOption :value="0">直连</SelectOption>
                <SelectOption :value="1">开放平台</SelectOption>
              </Select>
            </FormItem>
          </Col>
          <Col :md="12" :span="24">
            <FormItem label="状态" extra="禁用后后台列表仍可维护，但 SDK 调用和微信回调会拒绝该账号。">
              <Switch v-model:checked="accountEnabled" checked-children="启用" un-checked-children="禁用" />
            </FormItem>
          </Col>
          <template v-if="Number(form.service_mode) === 1">
            <Col :span="24">
              <FormItem label="JSON-RPC 地址">
                <Input v-model:value="form.extra.gateway_url" allow-clear placeholder="https://example.com/wechat-service/api/rpc/jsonrpc" />
              </FormItem>
            </Col>
            <Col :md="12" :span="24">
              <FormItem label="网关 Key">
                <Input v-model:value="form.extra.gateway_client_key" allow-clear placeholder="请输入网关 Key" />
              </FormItem>
            </Col>
            <Col :md="12" :span="24">
              <FormItem label="网关 Secret">
                <InputPassword v-model:value="form.extra.gateway_client_secret" placeholder="请输入网关 Secret" />
              </FormItem>
            </Col>
          </template>
          <Col :md="12" :span="24">
            <FormItem label="AppSecret">
              <InputPassword v-model:value="form.appsecret" placeholder="请输入 AppSecret" />
            </FormItem>
          </Col>
          <Col :md="12" :span="24">
            <FormItem label="消息 Token">
              <InputPassword v-model:value="form.token" placeholder="请输入消息 Token" />
            </FormItem>
          </Col>
          <Col :span="24">
            <FormItem label="EncodingAESKey">
              <InputPassword v-model:value="form.encodingaeskey" placeholder="请输入 EncodingAESKey" />
            </FormItem>
          </Col>
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
import {
  buildCrudTableLocale,
  CrudFilterSummary,
  CrudStatCards,
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import {
  Button,
  Card,
  Col,
  Drawer,
  Form,
  FormItem,
  Input,
  InputPassword,
  message,
  Row,
  Select,
  SelectOption,
  Space,
  Switch,
  Table,
  Tag,
  Tooltip,
} from 'ant-design-vue';

import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';

const { hasAccessByCodes } = useAccess();
const canCreateAccount = computed(() => hasAccessByCodes(['wechat.client.account.create']));
const canUpdateAccount = computed(() => hasAccessByCodes(['wechat.client.account.update']));
const canDeleteAccount = computed(() => hasAccessByCodes(['wechat.client.account.delete']));

const keyword = ref('');
const statusFilter = ref<number>();
const loading = ref(false);
const saving = ref(false);
const open = ref(false);
const editingId = ref<number | null>(null);
const items = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const emptyExtra = () => ({ gateway_url: '', gateway_client_key: '', gateway_client_secret: '' });
const form = reactive<any>({ name: '', appid: '', account_type: 'official_account', service_mode: 0, appsecret: '', token: '', encodingaeskey: '', extra: emptyExtra(), status: 1 });
const accountEnabled = computed({
  get: () => Number(form.status) === 1,
  set: (value: boolean) => { form.status = value ? 1 : 0; },
});

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([accountActions({})], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: 'AppID', key: 'appid', dataIndex: 'appid', width: 200 },
  { title: '名称', key: 'name', dataIndex: 'name', width: 180 },
  { title: '类型', key: 'account_type', dataIndex: 'account_type', width: 130 },
  { title: '模式', key: 'service_mode', dataIndex: 'service_mode', width: 120 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);
const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1040 }));

function accountActions(record: any) {
  return [
    { label: '编辑', visible: canUpdateAccount.value, onClick: () => openEdit(record) },
    { label: '删除', visible: canDeleteAccount.value, danger: true, confirmTitle: '确认删除该接口账号？', onClick: () => deleteRow(record.id) },
  ];
}

const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => {
  const filters: CrudFilterSummaryItem[] = [];
  if (keyword.value.trim()) {
    filters.push({ label: '关键字', value: keyword.value.trim() });
  }
  if (statusFilter.value !== undefined) {
    filters.push({ label: '状态', value: Number(statusFilter.value) === 1 ? '启用' : '禁用' });
  }
  return filters;
});

const summaryCards = computed(() => {
  const enabled = items.value.filter((item) => Number(item.status) === 1).length;
  const openPlatform = items.value.filter((item) => Number(item.service_mode) === 1).length;
  return [
    { label: '账号总数', value: String(pagination.total), desc: '当前筛选条件下的接口账号数量', icon: 'i-lucide-badge-check', tone: 'primary' as const },
    { label: '本页启用', value: String(enabled), desc: '当前页处于启用状态的账号', icon: 'i-lucide-circle-check', tone: 'success' as const },
    { label: '开放平台接入', value: String(openPlatform), desc: '当前页使用开放平台网关的账号', icon: 'i-lucide-radio-tower', tone: 'info' as const },
    { label: '直连账号', value: String(items.value.length - openPlatform), desc: '当前页使用直连模式的账号', icon: 'i-lucide-cable', tone: 'warning' as const },
  ];
});

function accountTypeText(value: string) {
  return value === 'mini_program' ? '小程序' : '公众号';
}

function serviceModeText(value: unknown) {
  return Number(value) === 1 ? '开放平台' : '直连';
}

async function load() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-client/account/index', { params: { page: pagination.current, pageSize: pagination.pageSize, keyword: keyword.value, status: statusFilter.value } });
    items.value = data?.items || [];
    pagination.total = data?.pageInfo?.total || 0;
  } finally {
    loading.value = false;
  }
}

function handleSearch() {
  pagination.current = 1;
  load();
}

function handleReset() {
  keyword.value = '';
  statusFilter.value = undefined;
  handleSearch();
}

function resetForm() {
  Object.assign(form, { name: '', appid: '', account_type: 'official_account', service_mode: 0, appsecret: '', token: '', encodingaeskey: '', extra: emptyExtra(), status: 1 });
}

function openCreate() {
  editingId.value = null;
  resetForm();
  open.value = true;
}

function openEdit(record: any) {
  editingId.value = record.id;
  Object.assign(form, record, { appsecret: '******', token: '******', encodingaeskey: '******', extra: { ...emptyExtra(), ...(record.extra || {}) } });
  open.value = true;
}

async function save() {
  if (!validateAccountForm()) {
    return;
  }
  saving.value = true;
  try {
    if (editingId.value) await requestClient.put(`wechat-client/account/update/${editingId.value}`, form);
    else await requestClient.post('wechat-client/account/create', form);
    message.success('保存成功');
    open.value = false;
    await load();
  } finally {
    saving.value = false;
  }
}

function validateAccountForm() {
  const gatewayUrl = String(form.extra?.gateway_url || '').trim();
  if (Number(form.service_mode) === 1 && gatewayUrl) {
    try {
      const parsed = new URL(gatewayUrl);
      if (!['http:', 'https:'].includes(parsed.protocol)) {
        message.error('开放平台网关地址必须是完整 http(s) URL');
        return false;
      }
      if (parsed.username || parsed.password || parsed.search || parsed.hash) {
        message.error('开放平台网关地址不能包含认证信息、查询参数或片段');
        return false;
      }
    } catch {
      message.error('开放平台网关地址必须是完整 http(s) URL');
      return false;
    }
  }

  return true;
}

async function deleteRow(id: number) {
  await requestClient.delete(`wechat-client/account/delete/${id}`);
  message.success('删除成功');
  await load();
}

function onTableChange(pag: any) {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  load();
}

onMounted(load);
</script>
