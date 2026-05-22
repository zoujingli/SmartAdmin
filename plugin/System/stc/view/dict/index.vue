<template>
  <Page title="数据字典">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button
            v-if="canDeleteDicts"
            danger
            :disabled="selectedRowKeys.length === 0"
            @click="handleBatchDelete"
          >
            <span class="i-lucide-trash-2" />
            批量删除
          </Button>
          <Button v-if="canCreateDicts" type="primary" @click="handleAdd"><span class="i-lucide-plus" />新增字典</Button>
          <Button v-if="canExportDicts" :loading="exporting" @click="handleExport"><span class="i-lucide-download" />导出</Button>
        </template>
        <template v-else>
          <Button v-if="canRecoveryDicts" :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRecovery">批量恢复</Button>
          <Button v-if="canRealDeleteDicts" danger :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRealDelete">批量彻底删除</Button>
        </template>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="data" tab="数据列表">
          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容"><Input v-model:value="searchForm.keyword" placeholder="请输入名称/编码/值" allow-clear /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="当前状态">
                  <Select v-model:value="searchForm.status" class="w-full" placeholder="请选择" allow-clear>
                    <SelectOption :value="1">启用</SelectOption>
                    <SelectOption :value="0">禁用</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
                <Space wrap>
                  <Button type="primary" :loading="loading" @click="handleSearch"><span class="i-lucide-search" />搜索</Button>
                  <Button :disabled="loading" @click="handleReset"><span class="i-lucide-refresh-cw" />重置</Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary
              :items="activeFilterItems"
              empty-text="当前显示全部字典记录，可按名称、编码、值和状态筛选。"
            />
          </Card>

          <Card>
            <CrudTableHeader
              title="字典台账"
              description="维护平台全局字典分类与字典项，供业务表单和列表统一取值。"
              :count-text="`${pagination.total} 条记录`"
            />
            <Table
              :columns="columns"
              :data-source="dictData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无字典记录')"
              :pagination="pagination"
              :row-selection="rowSelection"
              :scroll="tableScroll"
              row-key="id"
              @change="handleTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'type'">
                  <Tag :color="record.pid === 0 ? 'blue' : 'green'">{{ record.pid === 0 ? '分类' : '字典项' }}</Tag>
                </template>
                <template v-else-if="column.key === 'status'">
                  <Switch
                    :checked="record.status === 1"
                    :disabled="!canUpdateDicts"
                    @change="(checked) => handleStatusChange(record as DictInfo, Boolean(checked))"
                  />
                </template>
                <template v-else-if="column.key === 'code'">
                  <Tooltip :title="record.code" placement="topLeft">
                    <div class="truncate">{{ record.code }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft">
                    <div class="truncate">{{ record.name }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="dictActions(record as DictInfo)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryDicts || canRealDeleteDicts" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除字典"
              description="回收站中的字典可恢复；彻底删除前请确认没有业务仍依赖对应编码和值。"
              count-color="warning"
              :count-text="`${recyclePagination.total} 条记录`"
            />
            <Table
              :columns="recycleColumns"
              :data-source="recycleData"
              :loading="loadingRecycle"
              :locale="buildCrudTableLocale('回收站为空')"
              :pagination="recyclePagination"
              :row-selection="recycleRowSelection"
              :scroll="recycleTableScroll"
              row-key="id"
              @change="handleRecycleTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'type'">
                  <Tag :color="record.pid === 0 ? 'blue' : 'green'">{{ record.pid === 0 ? '分类' : '字典项' }}</Tag>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="dictRecycleActions(record as DictInfo)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>
      </Tabs>
    </Card>

    <FormModal
      v-model:visible="modalVisible"
      :data="formData"
      :parent-options="parentOptions"
      @success="handleFormSuccess"
    />

    <Modal
      :open="detailOpen"
      title="字典详情"
      width="min(860px, calc(100vw - 32px))"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentDict">
        <CrudDetailHero
          icon="i-lucide-book-open-text"
          :lines="[
            `编码：${currentDict.code || '-'}`,
            `创建时间：${currentDict.created_at || '-'}`,
          ]"
          :tags="[
            { label: currentDict.pid === 0 ? '分类' : '字典项' },
            { color: currentDict.status === 1 ? 'success' : 'default', label: dictStatusText(currentDict.status) },
          ]"
          :title="currentDict.name || '-'"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="字典 ID">{{ currentDict.id }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentDict.created_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="父级 ID">{{ currentDict.pid || 0 }}</DescriptionsItem>
          <DescriptionsItem label="字典编码">{{ currentDict.code || '-' }}</DescriptionsItem>
          <DescriptionsItem label="字典名称">{{ currentDict.name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="字典值">{{ currentDict.value || '-' }}</DescriptionsItem>
          <DescriptionsItem label="状态">{{ dictStatusText(currentDict.status) }}</DescriptionsItem>
          <DescriptionsItem label="排序">{{ currentDict.sort ?? 0 }}</DescriptionsItem>
          <DescriptionsItem label="扩展配置" :span="2">
            <pre class="dict-detail-json">{{ formatExtra(currentDict.extra) }}</pre>
          </DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentDict.remark || '-' }}</DescriptionsItem>
        </CrudDetailDescriptions>
      </CrudDetailPanel>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import {
  CrudDetailDescriptions,
  CrudDetailHero,
  CrudDetailPanel,
  CrudFilterSummary,
  buildCrudTableLocale,
  CrudStatCards,
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import {
  Button,
  Card,
  Col,
  DescriptionsItem,
  Input,
  message,
  Modal,
  Row,
  Select,
  SelectOption,
  Space,
  Switch,
  Table,
  Tabs,
  TabPane,
  Tag,
  Tooltip,
} from 'ant-design-vue';

import { dictApiService } from '#/api/system/dict';
import { exportCrudXlsx, statusText } from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

import FormModal from './modules/form.vue';
import type { DictInfo } from './types';

const searchForm = reactive({
  keyword: '',
  status: undefined as number | undefined,
});

const modalVisible = ref(false);
const formData = ref<DictInfo>();
const activeTab = ref('data');
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const currentDict = ref<DictInfo | null>(null);
const detailOpen = ref(false);
const dictData = ref<DictInfo[]>([]);
const recycleData = ref<DictInfo[]>([]);
const parentOptions = ref<DictInfo[]>([{ id: 0, pid: 0, code: '', name: '根分类', value: '', extra: {}, sort: 0, status: 1, remark: '', created_by: 0, updated_by: 0, created_at: '', updated_at: '', children: [] }]);
const selectedRowKeys = ref<number[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const statistics = ref({
  total: 0,
  active_count: 0,
  inactive_count: 0,
  today_created: 0,
});

const summaryCards = computed(() => [
  { desc: '当前字典分类和字典项总数。', icon: 'i-lucide-book-open-text', label: '字典总数', value: String(statistics.value.total) },
  { desc: '当前处于启用状态的字典数量。', icon: 'i-lucide-badge-check', label: '启用字典', value: String(statistics.value.active_count) },
  { desc: '当前处于禁用状态的字典数量。', icon: 'i-lucide-ban', label: '禁用字典', value: String(statistics.value.inactive_count) },
  { desc: '今天新增写入的字典记录数量。', icon: 'i-lucide-calendar-plus', label: '今日新增', value: String(statistics.value.today_created) },
]);

const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const keyword = searchForm.keyword.trim();
  if (keyword !== '') items.push({ label: '关键字', value: keyword });
  if (typeof searchForm.status === 'number') items.push({ label: '状态', value: dictStatusText(searchForm.status) });
  return items;
});

const { hasAccessByCodes } = useAccess();
const canCreateDicts = computed(() => hasAccessByCodes(['system.dict.create']));
const canUpdateDicts = computed(() => hasAccessByCodes(['system.dict.update']));
const canDeleteDicts = computed(() => hasAccessByCodes(['system.dict.delete']));
const canExportDicts = computed(() => hasAccessByCodes(['system.dict.export']));
const canRecoveryDicts = computed(() => hasAccessByCodes(['system.dict.recovery']));
const canRealDeleteDicts = computed(() => hasAccessByCodes(['system.dict.real-delete']));

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([dictActions({} as DictInfo)], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([dictRecycleActions({} as DictInfo)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '类型', key: 'type', width: 90 },
  { title: '字典编码', dataIndex: 'code', key: 'code', width: 180 },
  { title: '字典名称', dataIndex: 'name', key: 'name', width: 180 },
  { title: '字典值', dataIndex: 'value', key: 'value', width: 140 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 90 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 110 },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '类型', key: 'type', width: 90 },
  { title: '字典编码', dataIndex: 'code', key: 'code', width: 180 },
  { title: '字典名称', dataIndex: 'name', key: 'name', width: 180 },
  { title: '字典值', dataIndex: 'value', key: 'value', width: 140 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const tableScroll = computed(() => buildTableScrollX(columns.value, { selectionWidth: 60 }));
const recycleTableScroll = computed(() => buildTableScrollX(recycleColumns.value, { selectionWidth: 60 }));

function dictActions(record: DictInfo) {
  return [
    { label: '编辑', visible: canUpdateDicts.value, onClick: () => handleEdit(record) },
    { label: '查看', onClick: () => handleView(record) },
    { label: '删除', visible: canDeleteDicts.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function dictRecycleActions(record: DictInfo) {
  return [
    { label: '恢复', visible: canRecoveryDicts.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteDicts.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'code', title: '字典编码', width: 180 },
  { key: 'name', title: '字典名称', width: 180 },
  { key: 'value', title: '字典值', width: 140 },
  { key: 'status', title: '状态', width: 90, formatter: (record: DictInfo) => statusText(record.status) },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const pagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const recyclePagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const rowSelection = computed(() => {
  if (!canDeleteDicts.value) return undefined;
  return {
    selectedRowKeys: selectedRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const recycleRowSelection = computed(() => {
  if (!canRecoveryDicts.value && !canRealDeleteDicts.value) return undefined;
  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const loadDictList = async () => {
  if (loading.value) return;
  try {
    loading.value = true;
    const [response, statisticsResp] = await Promise.all([
      dictApiService.getDictList({
        page: pagination.current,
        pageSize: pagination.pageSize,
        keyword: searchForm.keyword,
        status: searchForm.status,
      }),
      dictApiService.getStatistics({
        keyword: searchForm.keyword,
        status: searchForm.status,
      }),
    ]);

    dictData.value = (response?.items || []) as DictInfo[];
    pagination.total = response?.pageInfo?.total || response?.total || 0;
    statistics.value = statisticsResp || statistics.value;
  } catch (error) {
    console.error('加载字典列表失败:', error);
    message.error('获取字典列表失败');
  } finally {
    loading.value = false;
  }
};

const loadRecycleList = async () => {
  if (!canRecoveryDicts.value && !canRealDeleteDicts.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    selectedRecycleRowKeys.value = [];
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await dictApiService.getRecycleList({
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      keyword: searchForm.keyword,
      status: searchForm.status,
    });

    recycleData.value = (response?.items || []) as DictInfo[];
    recyclePagination.total = response?.pageInfo?.total || response?.total || 0;
  } catch (error) {
    console.error('加载字典回收站失败:', error);
    message.error('获取字典回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
};

const loadParentOptions = async () => {
  try {
    const tree = await dictApiService.getDictTree({ pid: 0 });
    parentOptions.value = [
      { id: 0, pid: 0, code: '', name: '根分类', value: '', extra: {}, sort: 0, status: 1, remark: '', created_by: 0, updated_by: 0, created_at: '', updated_at: '', children: [] },
      ...(tree || []),
    ];
  } catch {
    parentOptions.value = [{ id: 0, pid: 0, code: '', name: '根分类', value: '', extra: {}, sort: 0, status: 1, remark: '', created_by: 0, updated_by: 0, created_at: '', updated_at: '', children: [] }];
  }
};

const handleSearch = () => {
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadDictList(), loadRecycleList()]);
};

const handleReset = () => {
  searchForm.keyword = '';
  searchForm.status = undefined;
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadDictList(), loadRecycleList()]);
};

const handleTableChange = (pag: any) => {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  loadDictList();
};

const handleRecycleTableChange = (pag: any) => {
  recyclePagination.current = pag.current;
  recyclePagination.pageSize = pag.pageSize;
  loadRecycleList();
};

const handleAdd = async () => {
  await loadParentOptions();
  formData.value = undefined;
  modalVisible.value = true;
};

const handleEdit = async (record: DictInfo) => {
  await loadParentOptions();
  formData.value = record;
  modalVisible.value = true;
};

const handleView = (record: DictInfo) => {
  currentDict.value = record;
  detailOpen.value = true;
};

const handleStatusChange = async (record: DictInfo, checked: boolean) => {
  try {
    await dictApiService.updateDictStatus(record.id, checked ? 1 : 0);
    message.success('状态更新成功');
    loadDictList();
  } catch (error) {
    console.error('更新字典状态失败:', error);
    message.error('状态更新失败');
  }
};

const handleDelete = (record: DictInfo) => {
  Modal.confirm({
    title: '确认删除',
    content: `确定要删除字典 "${record.name}" 吗？`,
    onOk: async () => {
      await dictApiService.deleteDict(record.id);
      message.success('删除成功');
      Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
    },
  });
};

const handleBatchDelete = () => {
  if (selectedRowKeys.value.length === 0) {
    message.warning('请选择要删除的字典');
    return;
  }

  Modal.confirm({
    title: '确认批量删除',
    content: `确定要删除选中的 ${selectedRowKeys.value.length} 条字典吗？`,
    okType: 'danger',
    onOk: async () => {
      await dictApiService.batchDeleteDicts(selectedRowKeys.value);
      selectedRowKeys.value = [];
      message.success('批量删除成功');
      Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
    },
  });
};

const handleRecovery = (record: DictInfo) => {
  Modal.confirm({
    title: '确认恢复',
    content: `确定要恢复字典 "${record.name}" 吗？`,
    onOk: async () => {
      await dictApiService.recoveryDicts([record.id]);
      message.success('恢复成功');
      Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
    },
  });
};

const handleRealDelete = (record: DictInfo) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: `彻底删除后不可恢复，确定删除字典 "${record.name}" 吗？`,
    okType: 'danger',
    onOk: async () => {
      await dictApiService.realDeleteDicts([record.id]);
      message.success('彻底删除成功');
      Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的字典');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 条字典吗？`,
    onOk: async () => {
      await dictApiService.recoveryDicts(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量恢复成功');
      Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的字典');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `彻底删除后不可恢复，确定删除选中的 ${selectedRecycleRowKeys.value.length} 条字典吗？`,
    okType: 'danger',
    onOk: async () => {
      await dictApiService.realDeleteDicts(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量彻底删除成功');
      Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<DictInfo>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => dictApiService.getDictList({
      keyword: searchForm.keyword,
      page,
      pageSize,
      status: searchForm.status,
    }),
    filename: `dict_${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '数据字典',
    });
  } finally {
    exporting.value = false;
  }
};

const handleFormSuccess = () => {
  modalVisible.value = false;
  Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
};

function dictStatusText(status?: number) {
  return status === 1 ? '启用' : '禁用';
}

function formatExtra(extra: Record<string, any> = {}) {
  return JSON.stringify(extra || {}, null, 2);
}

onMounted(() => {
  Promise.all([loadDictList(), loadRecycleList(), loadParentOptions()]);
});
</script>

<style scoped>
.dict-detail-json {
  margin: 0;
  max-height: 180px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
