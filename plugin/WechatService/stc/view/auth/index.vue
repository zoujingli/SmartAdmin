<template>
  <Page title="授权账号">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="AppID / 昵称 / 主体" /></SearchField>
          </Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
            <Space wrap>
              <Button type="primary" :loading="loading" @click="handleSearch"><span class="i-lucide-search" />搜索</Button>
              <Button :disabled="loading" @click="handleReset"><span class="i-lucide-refresh-cw" />重置</Button>
            </Space>
          </Col>
        </Row>
        <CrudFilterSummary :items="activeFilterItems" empty-text="当前显示全部开放平台授权账号，可按 AppID、昵称或主体筛选。" />
      </Card>

      <Card>
        <CrudTableHeader title="授权账号列表" description="维护开放平台已授权公众号和小程序的租户归属、状态与同步信息。" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="columns" :data-source="items" :loading="loading" :locale="buildCrudTableLocale('暂无授权账号')" :pagination="pagination" :scroll="tableScroll" row-key="id" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'authorizer_appid'">
              <Tooltip :title="record.authorizer_appid" placement="topLeft"><div class="truncate">{{ record.authorizer_appid || '-' }}</div></Tooltip>
            </template>
            <template v-else-if="column.key === 'nick_name'">
              <Tooltip :title="record.nick_name" placement="topLeft"><div class="truncate">{{ record.nick_name || '-' }}</div></Tooltip>
            </template>
            <template v-else-if="column.key === 'account_type'"><Tag color="blue">{{ accountTypeText(record.account_type) }}</Tag></template>
            <template v-else-if="column.key === 'status'">
              <Switch :checked="Number(record.status) === 1" :disabled="!canUpdateAuth" @change="(checked) => changeStatus(record.id, Boolean(checked))" />
            </template>
            <template v-else-if="column.key === 'action'">
              <CrudTableActions :actions="authActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </Card>
  </Page>
</template>

<script setup lang="ts">
import type { CrudFilterSummaryItem } from '@vben/common-ui';

import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import { buildCrudTableLocale, CrudFilterSummary, CrudStatCards, CrudTableHeader, Page } from '@vben/common-ui';
import { Button, Card, Col, Input, message, Row, Space, Switch, Table, Tag, Tooltip } from 'ant-design-vue';

import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';

const { hasAccessByCodes } = useAccess();
const canUpdateAuth = computed(() => hasAccessByCodes(['wechat.service.auth.update']));
const canSyncAuth = computed(() => hasAccessByCodes(['wechat.service.auth.sync']));
const canDeleteAuth = computed(() => hasAccessByCodes(['wechat.service.auth.delete']));

const keyword = ref('');
const loading = ref(false);
const items = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([authActions({})], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '租户', dataIndex: 'tenant_id', width: 90 },
  { title: 'AppID', key: 'authorizer_appid', dataIndex: 'authorizer_appid', width: 200 },
  { title: '昵称', key: 'nick_name', dataIndex: 'nick_name', width: 180 },
  { title: '类型', key: 'account_type', dataIndex: 'account_type', width: 130 },
  { title: '主体', dataIndex: 'principal_name', width: 240 },
  { title: '调用次数', dataIndex: 'total', width: 100 },
  { title: '状态', key: 'status', width: 100 },
  { title: '授权时间', dataIndex: 'auth_time', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);
const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1280 }));

function authActions(record: any) {
  return [
    { label: '同步', visible: canSyncAuth.value, onClick: () => syncAuth(record.id) },
    { label: '删除', visible: canDeleteAuth.value, danger: true, confirmTitle: '确认删除该授权账号？', onClick: () => deleteAuth(record.id) },
  ];
}

const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => keyword.value.trim() ? [{ label: '关键字', value: keyword.value.trim() }] : []);
const summaryCards = computed(() => {
  const enabled = items.value.filter((item) => Number(item.status) === 1).length;
  return [
    { label: '授权总数', value: String(pagination.total), desc: '当前筛选条件下的授权账号数量', icon: 'i-lucide-badge-check', tone: 'primary' as const },
    { label: '本页启用', value: String(enabled), desc: '当前页启用的授权账号', icon: 'i-lucide-circle-check', tone: 'success' as const },
    { label: '本页禁用', value: String(items.value.length - enabled), desc: '当前页禁用的授权账号', icon: 'i-lucide-circle-off', tone: 'warning' as const },
    { label: '调用次数', value: String(items.value.reduce((sum, item) => sum + Number(item.total || 0), 0)), desc: '当前页网关调用次数合计', icon: 'i-lucide-activity', tone: 'info' as const },
  ];
});

function accountTypeText(value: string) {
  return ({ mini_program: '小程序', official_account: '公众号/服务号', subscription: '订阅号' } as Record<string, string>)[value] || value || '-';
}
async function load() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-service/auth/index', { params: { page: pagination.current, pageSize: pagination.pageSize, keyword: keyword.value } });
    items.value = data?.items || [];
    pagination.total = data?.pageInfo?.total || 0;
  } finally { loading.value = false; }
}
function handleSearch() { pagination.current = 1; load(); }
function handleReset() { keyword.value = ''; handleSearch(); }
async function changeStatus(id: number, checked: boolean) {
  await requestClient.put(`wechat-service/auth/status/${id}`, { status: checked ? 1 : 0 });
  message.success('状态已更新');
  await load();
}
async function syncAuth(id: number) {
  await requestClient.post(`wechat-service/auth/sync/${id}`);
  message.success('同步成功');
  await load();
}
async function deleteAuth(id: number) {
  await requestClient.delete(`wechat-service/auth/delete/${id}`);
  message.success('删除成功');
  await load();
}
function onTableChange(pag: any) { pagination.current = pag.current; pagination.pageSize = pag.pageSize; load(); }
onMounted(load);
</script>
