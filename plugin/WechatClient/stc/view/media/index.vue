<template>
  <Page title="素材管理">
    <template #extra>
      <Space wrap>
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="canSave" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增素材</Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row class="crud-search-grid" :gutter="[16, 16]">
          <Col :xs="24" :sm="12" :xl="6"><SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="名称 / MediaID / 地址" /></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="5">
            <SearchField label="接口账号"><InputNumber v-model:value="accountId" :min="1" class="w-full" placeholder="账号 ID" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="5">
            <SearchField label="素材类型">
              <Select v-model:value="mediaType" allow-clear class="w-full" placeholder="请选择">
                <SelectOption value="image">图片</SelectOption>
                <SelectOption value="voice">语音</SelectOption>
                <SelectOption value="video">视频</SelectOption>
                <SelectOption value="news">图文</SelectOption>
              </Select>
            </SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="4">
            <SearchField label="当前状态">
              <Select v-model:value="statusFilter" allow-clear class="w-full" placeholder="请选择">
                <SelectOption :value="1">启用</SelectOption>
                <SelectOption :value="0">禁用</SelectOption>
              </Select>
            </SearchField>
          </Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="8">
            <Space wrap>
              <Button type="primary" :loading="loading" @click="handleSearch">搜索</Button>
              <Button :disabled="loading" @click="handleReset">重置</Button>
              <Button v-if="canSync" :disabled="!accountId || syncing" :loading="syncing" @click="syncMedia">同步官方素材</Button>
            </Space>
          </Col>
        </Row>
        <CrudFilterSummary class="mt-4" :items="activeFilterItems" empty-text="当前显示全部素材，可按账号 ID、素材类型、状态、名称、MediaID 或地址筛选。" />
      </Card>

      <Card>
        <CrudTableHeader title="素材列表" description="维护本地素材与微信永久素材 MediaID，可用于自动回复和菜单发布。" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="columns" :data-source="items" :loading="loading" :locale="buildCrudTableLocale('暂无素材记录')" :pagination="pagination" row-key="id" :scroll="tableScroll" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'type'"><Tag color="blue">{{ typeLabel(record.media_type) }}</Tag></template>
            <template v-else-if="column.key === 'media_id'"><TypographyText copyable class="block max-w-[260px] truncate">{{ record.media_id || '-' }}</TypographyText></template>
            <template v-else-if="column.key === 'url'">
              <a v-if="record.url || record.file_url" :href="record.url || record.file_url" target="_blank">查看</a>
              <span v-else>-</span>
            </template>
            <template v-else-if="column.key === 'status'">
              <Tag :color="Number(record.status) === 1 ? 'success' : 'default'">
                {{ Number(record.status) === 1 ? '启用' : '禁用' }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'action'">
              <CrudTableActions :actions="mediaActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </Card>

    <Drawer
      :open="modalOpen"
      :title="editingId ? '编辑素材' : '新增素材'"
      :body-style="{ padding: '20px 24px 8px' }"
      :width="popupWidth.sm"
      placement="right"
      @close="modalOpen = false"
    >
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :span="12"><FormItem label="账号 ID"><InputNumber v-model:value="form.account_id" :min="1" class="w-full" /></FormItem></Col>
          <Col :span="12"><FormItem label="素材类型"><Select v-model:value="form.media_type"><SelectOption value="image">图片</SelectOption><SelectOption value="voice">语音</SelectOption><SelectOption value="video">视频</SelectOption><SelectOption value="news">图文</SelectOption></Select></FormItem></Col>
          <Col :span="24"><FormItem label="素材名称"><Input v-model:value="form.name" :maxlength="180" /></FormItem></Col>
          <Col :span="24"><FormItem label="微信 MediaID"><Input v-model:value="form.media_id" placeholder="可同步或上传后自动写入" /></FormItem></Col>
          <Col :span="12"><FormItem label="本地文件 ID"><InputNumber v-model:value="form.file_id" :min="0" class="w-full" /></FormItem></Col>
          <Col :span="12"><FormItem label="状态"><Select v-model:value="form.status"><SelectOption :value="1">启用</SelectOption><SelectOption :value="0">禁用</SelectOption></Select></FormItem></Col>
          <Col :span="24"><FormItem label="文件地址"><Input v-model:value="form.file_url" placeholder="用于上传永久素材的 http(s) 地址；服务器本地文件请填写本地文件 ID" /></FormItem></Col>
          <Col :span="24"><FormItem label="素材 URL"><Input v-model:value="form.url" placeholder="微信返回或外部可访问地址" /></FormItem></Col>
        </Row>
      </Form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button @click="modalOpen = false">取消</Button>
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
import { Button, Card, Col, Drawer, Form, FormItem, Input, InputNumber, message, Row, Select, SelectOption, Space, Table, Tag, TypographyText } from 'ant-design-vue';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { popupWidth } from '#/utils/popup';

const { hasAccessByCodes } = useAccess();
const canSave = computed(() => hasAccessByCodes(['wechat.client.media.save']));
const canSync = computed(() => hasAccessByCodes(['wechat.client.media.sync']));
const canUpload = computed(() => hasAccessByCodes(['wechat.client.media.upload']));
const canDelete = computed(() => hasAccessByCodes(['wechat.client.media.delete']));

const keyword = ref('');
const accountId = ref<number>();
const mediaType = ref<string>();
const statusFilter = ref<number>();
const loading = ref(false);
const saving = ref(false);
const syncing = ref(false);
const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const items = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const form = reactive<any>({ account_id: undefined, media_type: 'image', name: '', media_id: '', url: '', file_id: 0, file_url: '', status: 1 });
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([mediaActions({})], { maxWidth: 220 }));
const columns = computed<any[]>(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '账号', dataIndex: 'account_id', width: 90 },
  { title: '类型', key: 'type', dataIndex: 'media_type', width: 100 },
  { title: '名称', dataIndex: 'name', width: 180 },
  { title: 'MediaID', key: 'media_id', dataIndex: 'media_id', width: 280 },
  { title: '地址', key: 'url', width: 100 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' },
]);
const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1320 }));

function mediaActions(record: any) {
  return [
    { label: '编辑', visible: canSave.value, onClick: () => openEdit(record) },
    { label: '上传', visible: canUpload.value, confirmTitle: '确认上传该素材到微信永久素材库？', onClick: () => uploadMedia(record) },
    { label: '删除', visible: canDelete.value, danger: true, confirmTitle: '确认删除该素材？', onClick: () => deleteRow(record.id) },
  ];
}

const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => {
  const filters: CrudFilterSummaryItem[] = [];
  if (keyword.value.trim()) {
    filters.push({ label: '关键字', value: keyword.value.trim() });
  }
  if (accountId.value) {
    filters.push({ label: '账号 ID', value: String(accountId.value) });
  }
  if (mediaType.value) {
    filters.push({ label: '素材类型', value: typeLabel(mediaType.value) });
  }
  if (statusFilter.value !== undefined) {
    filters.push({ label: '状态', value: Number(statusFilter.value) === 1 ? '启用' : '禁用' });
  }
  return filters;
});

const summaryCards = computed(() => {
  const uploaded = items.value.filter((item) => String(item.media_id || '').trim() !== '').length;
  const enabled = items.value.filter((item) => Number(item.status) === 1).length;
  return [
    { label: '素材总数', value: String(pagination.total), desc: '当前筛选条件下的素材数量', icon: 'i-lucide-images', tone: 'primary' as const },
    { label: '本页已上传', value: String(uploaded), desc: '当前页已有微信 MediaID 的素材', icon: 'i-lucide-cloud-check', tone: 'success' as const },
    { label: '本页启用', value: String(enabled), desc: '当前页启用状态素材', icon: 'i-lucide-circle-check', tone: 'info' as const },
    { label: '同步账号', value: accountId.value ? String(accountId.value) : '-', desc: '同步官方素材时使用的接口账号 ID', icon: 'i-lucide-refresh-cw', tone: 'warning' as const },
  ];
});

function typeLabel(type: string) {
  return ({ image: '图片', voice: '语音', video: '视频', news: '图文' } as Record<string, string>)[type] || type;
}

async function load() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-client/media/index', { params: { page: pagination.current, pageSize: pagination.pageSize, keyword: keyword.value, account_id: accountId.value, media_type: mediaType.value, status: statusFilter.value } });
    items.value = data?.items || [];
    pagination.total = data?.pageInfo?.total || 0;
  } finally { loading.value = false; }
}
function handleSearch() { pagination.current = 1; load(); }
function handleReset() { keyword.value = ''; accountId.value = undefined; mediaType.value = undefined; statusFilter.value = undefined; handleSearch(); }
function openCreate() { editingId.value = null; Object.assign(form, { account_id: accountId.value, media_type: mediaType.value || 'image', name: '', media_id: '', url: '', file_id: 0, file_url: '', status: 1 }); modalOpen.value = true; }
function openEdit(record: any) { editingId.value = record.id; Object.assign(form, { ...record }); modalOpen.value = true; }
async function save() {
  saving.value = true;
  try {
    if (editingId.value) await requestClient.put(`wechat-client/media/update/${editingId.value}`, form);
    else await requestClient.post('wechat-client/media/create', form);
    message.success('保存成功'); modalOpen.value = false; await load();
  } finally { saving.value = false; }
}
async function syncMedia() {
  if (syncing.value) return;
  if (!accountId.value) return message.warning('请先输入账号 ID');
  syncing.value = true;
  try {
    await requestClient.post(`wechat-client/media/sync/${accountId.value}`, { media_type: mediaType.value || 'image', max_pages: 5 });
    message.success('同步任务完成');
    await load();
  } finally { syncing.value = false; }
}
async function uploadMedia(record: any) { await requestClient.post(`wechat-client/media/upload/${record.id}`); message.success('上传成功'); await load(); }
async function deleteRow(id: number) { await requestClient.delete(`wechat-client/media/delete/${id}`); message.success('删除成功'); await load(); }
function onTableChange(pag: any) { pagination.current = pag.current; pagination.pageSize = pag.pageSize; load(); }
onMounted(load);
</script>
