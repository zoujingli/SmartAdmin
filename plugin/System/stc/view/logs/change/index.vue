<template>
  <Page title="变更日志">
    <template #extra>
      <Space wrap class="justify-end">
        <Button v-if="canExportChangeLogs" :loading="exporting" @click="handleExport">
          <span class="i-lucide-download mr-1" />导出
        </Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="搜索内容"><Input v-model:value="searchForm.keyword" allow-clear placeholder="对象 / 字段 / 摘要 / 用户" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="操作编号"><Input v-model:value="searchForm.action_id" allow-clear placeholder="操作日志 ID" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="操作用户"><Input v-model:value="searchForm.username" allow-clear placeholder="请输入操作用户" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="变更动作">
              <Select v-model:value="searchForm.event" allow-clear class="w-full" placeholder="请选择">
                <SelectOption value="created">新增</SelectOption>
                <SelectOption value="updated">更新</SelectOption>
                <SelectOption value="deleted">删除</SelectOption>
                <SelectOption value="force_deleted">彻底删除</SelectOption>
                <SelectOption value="restored">恢复</SelectOption>
              </Select>
            </SearchField>
          </Col>
        </Row>
        <Row class="crud-search-grid" :gutter="[16, 16]">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="业务对象"><Input v-model:value="searchForm.model_name" allow-clear placeholder="请输入业务对象" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="数据表名"><Input v-model:value="searchForm.table_name" allow-clear placeholder="请输入数据表名" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="5">
            <SearchField label="创建时间">
              <RangePicker
                v-model:value="searchForm.created_at"
                :placeholder="['开始日期', '结束日期']"
                class="w-full max-w-full"
                format="YYYY-MM-DD"
              />
            </SearchField>
          </Col>
          <Col :xs="24" :sm="24" :xl="6" class="flex flex-wrap items-center gap-2 crud-search-grid__actions">
            <Button type="primary" :loading="loading" @click="handleSearch">
              <span class="i-lucide-search mr-1" />搜索
            </Button>
            <Button :disabled="loading" @click="handleReset">
              <span class="i-lucide-refresh-cw mr-1" />重置
            </Button>
          </Col>
        </Row>

        <CrudFilterSummary
          class="mt-4"
          :items="activeFilterItems"
          empty-text="当前显示全部变更日志，可按操作、对象、动作、用户和日期范围快速筛选。"
        />
      </Card>

      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card>
        <CrudTableHeader
          title="变更台账"
          description="展示业务对象级别的数据变化，一条记录对应一次操作中的一个业务对象变更。"
          :count-text="`${pagination.total} 条记录`"
        />
        <Table
          :columns="columns"
          :data-source="changeData"
          :loading="loading"
          :locale="buildCrudTableLocale('暂无变更日志')"
          :pagination="pagination"
          :scroll="tableScrollX"
          row-key="id"
          @change="handleTableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'event'">
              <CrudToneTag :color="eventTone(record.event)" :text="eventLabel(record.event)" />
            </template>
            <template v-else-if="column.key === 'record'">
              <Tooltip :title="record.record_label || record.record_id || '-'" placement="topLeft">
                <div class="truncate">{{ record.record_label || record.record_id || '-' }}</div>
              </Tooltip>
            </template>
            <template v-else-if="column.key === 'change_remark'">
              <Tooltip :title="record.change_remark || '-'" placement="topLeft">
                <div class="line-clamp-2">{{ record.change_remark || '-' }}</div>
              </Tooltip>
            </template>
            <template v-else-if="column.key === 'action'">
              <CrudTableActions :actions="changeActions(record as LogsChangeRow)" />
            </template>
          </template>
        </Table>
      </Card>
    </Card>

    <Modal
      :open="detailOpen"
      title="变更日志详情"
      width="min(980px, calc(100vw - 32px))"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentChange">
        <CrudDetailHero
          icon="i-lucide-file-diff"
          :lines="[
            `业务表：${currentChange.table_name || '-'}`,
            `记录标识：${currentChange.record_id || '-'}`,
            `记录时间：${currentChange.created_at || '-'}`,
          ]"
          :tags="[
            { color: eventTone(currentChange.event), label: eventLabel(currentChange.event) },
            { label: currentChange.username || '匿名用户' },
            { label: `Action #${currentChange.action_id || '-'}` },
          ]"
          :title="detailTitle"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="变更日志 ID">{{ currentChange.id }}</DescriptionsItem>
          <DescriptionsItem label="操作日志 ID">{{ currentChange.action_id || '-' }}</DescriptionsItem>
          <DescriptionsItem label="操作用户">{{ currentChange.username || '-' }}</DescriptionsItem>
          <DescriptionsItem label="变更动作">{{ eventLabel(currentChange.event) }}</DescriptionsItem>
          <DescriptionsItem label="模型">{{ currentChange.model || '-' }}</DescriptionsItem>
          <DescriptionsItem label="业务对象">{{ currentChange.model_name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="业务表">{{ currentChange.table_name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="记录标识">{{ currentChange.record_id || '-' }}</DescriptionsItem>
          <DescriptionsItem label="记录名称" :span="2">{{ currentChange.record_label || '-' }}</DescriptionsItem>
          <DescriptionsItem label="变更摘要" :span="2">{{ currentChange.change_remark || '-' }}</DescriptionsItem>
        </CrudDetailDescriptions>

        <Card v-if="changeFieldRows.length > 0" class="mb-4" size="small" title="字段变化">
          <Table
            :columns="fieldColumns"
            :data-source="changeFieldRows"
            :pagination="false"
            :scroll="fieldTableScroll"
            row-key="key"
            size="small"
          />
        </Card>
        <CrudDetailSection v-else title="结构化变更数据" :content="changePayload" preformatted />

        <Card v-if="currentAction" class="mb-4" size="small" title="关联操作日志">
          <CrudDetailDescriptions>
            <DescriptionsItem label="操作名称">{{ currentAction.name || '-' }}</DescriptionsItem>
            <DescriptionsItem label="响应码">{{ currentAction.response_code || '-' }}</DescriptionsItem>
            <DescriptionsItem label="请求方式">{{ currentAction.method || '-' }}</DescriptionsItem>
            <DescriptionsItem label="请求路由" :span="2">{{ currentAction.router || '-' }}</DescriptionsItem>
            <DescriptionsItem label="操作摘要" :span="2">{{ currentAction.remark || '-' }}</DescriptionsItem>
          </CrudDetailDescriptions>
        </Card>
      </CrudDetailPanel>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import type { LogsChangeApi } from '#/api';

import { computed, onMounted, reactive, ref } from 'vue';
import dayjs from 'dayjs';

import {
  buildCrudTableLocale,
  CrudDetailDescriptions,
  CrudDetailHero,
  CrudDetailPanel,
  CrudDetailSection,
  CrudFilterSummary,
  CrudStatCards,
  CrudTableHeader,
  CrudToneTag,
  Page,
} from '@vben/common-ui';
import { useAccess } from '@vben/access';

import {
  Button,
  Card,
  Col,
  DescriptionsItem,
  Input,
  message,
  Modal,
  RangePicker,
  Row,
  Select,
  SelectOption,
  Space,
  Table,
  Tooltip,
} from 'ant-design-vue';

import { logsChangeApiService } from '#/api';
import { exportCrudXlsx } from '#/utils/crud-excel';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';

type LogsChangeRow = LogsChangeApi.LogsChangeRow;
type LogsChangeValue = LogsChangeApi.LogsChangeValue;

const { hasAccessByCodes } = useAccess();
const canExportChangeLogs = computed(() => hasAccessByCodes(['system.logs.change.export']));

const searchForm = reactive({
  keyword: '',
  action_id: '',
  username: '',
  model_name: '',
  table_name: '',
  event: undefined as string | undefined,
  created_at: undefined as any,
});

const loading = ref(false);
const exporting = ref(false);
const changeData = ref<LogsChangeRow[]>([]);
const currentChange = ref<LogsChangeRow | null>(null);
const detailOpen = ref(false);
const statistics = ref<LogsChangeApi.LogsChangeStatistics>({
  total: 0,
  today: 0,
  by_event: {},
  by_model: {},
  by_table: {},
});

const pagination = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([changeActions({} as LogsChangeRow)], { maxWidth: 150 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: 'Action ID', dataIndex: 'action_id', key: 'action_id', width: 110 },
  { title: '用户', dataIndex: 'username', key: 'username', width: 120 },
  { title: '业务对象', dataIndex: 'model_name', key: 'model_name', width: 140 },
  { title: '业务表', dataIndex: 'table_name', key: 'table_name', width: 160 },
  { title: '记录', key: 'record', width: 180 },
  { title: '动作', dataIndex: 'event', key: 'event', width: 120 },
  { title: '变更摘要', dataIndex: 'change_remark', key: 'change_remark', width: 420 },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const fieldColumns = [
  { title: '字段', dataIndex: 'field', key: 'field', width: 180 },
  { title: '原值', dataIndex: 'old_text', key: 'old_text', width: 260 },
  { title: '新值', dataIndex: 'new_text', key: 'new_text', width: 260 },
];

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'action_id', title: '操作日志 ID', width: 120 },
  { key: 'username', title: '用户', width: 120 },
  { key: 'model_name', title: '业务对象', width: 160 },
  { key: 'table_name', title: '业务表', width: 180 },
  { key: 'record_id', title: '记录 ID', width: 140 },
  { key: 'record_label', title: '记录名称', width: 180 },
  { key: 'event', title: '动作', width: 120, formatter: (record: LogsChangeRow) => eventLabel(record.event) },
  { key: 'change_remark', title: '变更摘要', width: 420 },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const tableScrollX = computed(() => buildTableScrollX(columns.value, { minWidth: 1600 }));
const fieldTableScroll = buildTableScrollX(fieldColumns, { minWidth: 760 });

function changeActions(record: LogsChangeRow) {
  return [
    { label: '查看', onClick: () => handleView(record) },
  ];
}

const currentAction = computed(() => currentChange.value?.action || null);
const detailTitle = computed(() => {
  if (!currentChange.value) return '变更日志';
  const name = currentChange.value.model_name || currentChange.value.model || '业务记录';
  const label = currentChange.value.record_label || currentChange.value.record_id || '';
  return label ? `${name}(${label})` : name;
});

const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  if (searchForm.keyword.trim()) items.push({ label: '关键字', value: searchForm.keyword.trim() });
  if (String(searchForm.action_id || '').trim()) items.push({ label: '操作日志 ID', value: String(searchForm.action_id).trim() });
  if (searchForm.username.trim()) items.push({ label: '操作用户', value: searchForm.username.trim() });
  if (searchForm.model_name.trim()) items.push({ label: '业务对象', value: searchForm.model_name.trim() });
  if (searchForm.table_name.trim()) items.push({ label: '业务表', value: searchForm.table_name.trim() });
  if (searchForm.event) items.push({ label: '变更动作', value: eventLabel(searchForm.event) });
  if ((searchForm.created_at || []).length === 2) {
    const [start, end] = searchForm.created_at as any[];
    if (start && end) {
      items.push({
        label: '日期范围',
        value: `${dayjs(start).format('YYYY-MM-DD')} ~ ${dayjs(end).format('YYYY-MM-DD')}`,
      });
    }
  }
  return items;
});

const summaryCards = computed(() => {
  const updatedCount = Number(statistics.value.by_event.updated || 0);
  const createdCount = Number(statistics.value.by_event.created || 0);
  return [
    {
      desc: '当前筛选范围内的变更日志总量。',
      icon: 'i-lucide-file-diff',
      label: '变更总量',
      value: String(statistics.value.total),
    },
    {
      desc: '今天产生的业务数据变更数量。',
      icon: 'i-lucide-calendar-days',
      label: '今日变更',
      value: String(statistics.value.today),
    },
    {
      desc: '当前筛选范围内的更新动作数量。',
      icon: 'i-lucide-pencil-line',
      label: '更新动作',
      value: String(updatedCount),
    },
    {
      desc: '当前筛选范围内的新增动作数量。',
      icon: 'i-lucide-plus-circle',
      label: '新增动作',
      value: String(createdCount),
    },
  ];
});

const changeFieldRows = computed(() => {
  return parseChangeValues(currentChange.value?.change_values).map((field, index) => ({
    key: `${field.field || 'field'}-${index}`,
    field: `${field.label || field.field || '字段'}(${field.field || '-'})`,
    old_text: field.old_text ?? stringifyValue(field.old),
    new_text: field.new_text ?? stringifyValue(field.new),
  }));
});

const changePayload = computed(() => safeJson(currentChange.value?.change_values));

function buildQueryParams() {
  const [start, end] = (searchForm.created_at || []) as any[];
  return {
    page: pagination.current,
    pageSize: pagination.pageSize,
    keyword: searchForm.keyword,
    action_id: searchForm.action_id,
    username: searchForm.username,
    model_name: searchForm.model_name,
    table_name: searchForm.table_name,
    event: searchForm.event,
    startDate: start ? dayjs(start).format('YYYY-MM-DD') : undefined,
    endDate: end ? dayjs(end).format('YYYY-MM-DD') : undefined,
  };
}

async function loadStatistics() {
  statistics.value = await logsChangeApiService.getChangeLogStatistics(buildQueryParams());
}

async function loadChangeList() {
  if (loading.value) return;
  try {
    loading.value = true;
    const [response] = await Promise.all([
      logsChangeApiService.getChangeLogList(buildQueryParams()),
      loadStatistics(),
    ]);
    changeData.value = (response?.items || []) as LogsChangeRow[];
    pagination.total = response?.pageInfo?.total || 0;
  } catch (error) {
    console.error('加载变更日志失败:', error);
    message.error('获取变更日志失败');
  } finally {
    loading.value = false;
  }
}

function handleSearch() {
  pagination.current = 1;
  loadChangeList();
}

function handleReset() {
  searchForm.keyword = '';
  searchForm.action_id = '';
  searchForm.username = '';
  searchForm.model_name = '';
  searchForm.table_name = '';
  searchForm.event = undefined;
  searchForm.created_at = undefined;
  pagination.current = 1;
  loadChangeList();
}

function handleTableChange(pag: any) {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  loadChangeList();
}

async function handleView(record: LogsChangeRow) {
  currentChange.value = record;
  detailOpen.value = true;
  try {
    const detail = await logsChangeApiService.getChangeLogDetail(record.id);
    currentChange.value = detail || record;
  } catch (error) {
    console.error('加载变更日志详情失败:', error);
    message.error('获取变更日志详情失败');
  }
}

async function handleExport() {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<LogsChangeRow>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => logsChangeApiService.getChangeLogList({
      ...buildQueryParams(),
      page,
      pageSize,
    }),
    filename: `变更日志_${dayjs().format('YYYYMMDD_HHmmss')}.xlsx`,
    sheetName: '变更日志',
    });
  } finally {
    exporting.value = false;
  }
}

function parseChangeValues(value?: LogsChangeRow['change_values']): LogsChangeValue[] {
  if (!value) return [];
  if (Array.isArray(value)) return value;
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? (parsed as LogsChangeValue[]) : [];
  } catch {
    return [];
  }
}

function safeJson(value: unknown) {
  if (!value) return '-';
  try {
    return JSON.stringify(typeof value === 'string' ? JSON.parse(value) : value, null, 2);
  } catch {
    return String(value);
  }
}

function stringifyValue(value: unknown) {
  if (value === undefined || value === null || value === '') return '-';
  return typeof value === 'object' ? safeJson(value) : String(value);
}

function eventLabel(event?: string) {
  const labels: Record<string, string> = {
    created: '新增',
    updated: '更新',
    deleted: '删除',
    force_deleted: '彻底删除',
    restored: '恢复',
  };
  return labels[event || ''] || event || '-';
}

function eventTone(event?: string) {
  const tones: Record<string, string> = {
    created: 'success',
    updated: 'processing',
    deleted: 'warning',
    force_deleted: 'error',
    restored: 'green',
  };
  return tones[event || ''] || 'default';
}

onMounted(() => {
  loadChangeList();
});
</script>
