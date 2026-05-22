<template>
  <Page title="岗位管理">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button
            v-if="canDeletePosts"
            danger
            :disabled="selectedRowKeys.length === 0"
            @click="handleBatchDelete"
          >
            <span class="i-lucide-trash-2" />
            批量删除
          </Button>
          <Button v-if="canCreatePosts" type="primary" @click="handleAdd"><span class="i-lucide-plus" />新增岗位</Button>
          <Button v-if="canCreatePosts" @click="handleImport"><span class="i-lucide-upload" />导入</Button>
          <Button v-if="canExportPosts" :loading="exporting" @click="handleExport"><span class="i-lucide-download" />导出</Button>
        </template>
        <template v-else>
          <Button v-if="canRecoveryPosts" :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRecovery">批量恢复</Button>
          <Button v-if="canRealDeletePosts" danger :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRealDelete">批量彻底删除</Button>
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
                <SearchField label="搜索内容"><Input v-model:value="searchForm.keyword" placeholder="请输入岗位名称/编码" allow-clear /></SearchField>
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
              empty-text="当前显示全部岗位记录，可按岗位名称、编码和状态快速筛选。"
            />
          </Card>

          <Card>
            <CrudTableHeader
              title="岗位台账"
              description="维护岗位编码、状态与排序，支持新增、编辑、导出和批量删除。"
              :count-text="`${pagination.total} 条记录`"
            />
            <Table
              :columns="columns"
              :data-source="postData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无岗位记录')"
              :pagination="pagination"
              :row-selection="rowSelection"
              :scroll="tableScroll"
              row-key="id"
              @change="handleTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'status'">
                  <Switch
                    :checked="record.status === 1"
                    :disabled="!canUpdatePosts"
                    @change="(checked) => handleStatusChange(record as PostType, Boolean(checked))"
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
                  <CrudTableActions :actions="postActions(record as PostType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryPosts || canRealDeletePosts" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除岗位"
              description="回收站中的岗位可恢复到主列表；彻底删除后将不可恢复，请谨慎操作。"
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
                <template v-if="column.key === 'code'">
                  <Tooltip :title="record.code" placement="topLeft">
                    <div class="truncate">{{ record.code }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft">
                    <div class="truncate">{{ record.name }}</div>
                  </Tooltip>
                </template>
                <template v-if="column.key === 'action'">
                  <CrudTableActions :actions="postRecycleActions(record as PostType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>
      </Tabs>
    </Card>

    <FormModal v-model:visible="modalVisible" :data="formData" @success="handleFormSuccess" />

    <Modal
      :open="detailOpen"
      title="岗位详情"
      width="min(860px, calc(100vw - 32px))"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentPost">
        <CrudDetailHero
          icon="i-lucide-briefcase-business"
          :lines="[
            `排序：${currentPost.sort ?? 0}`,
            `创建时间：${currentPost.created_at || '-'}`,
          ]"
          :tags="[
            { label: currentPost.code || '未设置编码' },
            { color: currentPost.status === 1 ? 'success' : 'default', label: postStatusText(currentPost.status) },
          ]"
          :title="currentPost.name || '-'"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="岗位 ID">{{ currentPost.id }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentPost.created_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="岗位编码">{{ currentPost.code || '-' }}</DescriptionsItem>
          <DescriptionsItem label="岗位名称">{{ currentPost.name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="状态">{{ postStatusText(currentPost.status) }}</DescriptionsItem>
          <DescriptionsItem label="排序">{{ currentPost.sort ?? 0 }}</DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentPost.remark || '-' }}</DescriptionsItem>
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
  Tooltip,
} from 'ant-design-vue';

import { postApiService } from '#/api/system/post';
import {
  exportCrudXlsx,
  openCrudImport,
  parseNumber,
  parseStatus,
  statusText,
} from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

import FormModal from './modules/form.vue';
import type { PostType } from './types';

const searchForm = reactive({
  keyword: '',
  status: undefined as number | undefined,
});

const modalVisible = ref(false);
const formData = ref<PostType>();
const activeTab = ref('data');
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const currentPost = ref<PostType | null>(null);
const detailOpen = ref(false);
const postData = ref<PostType[]>([]);
const selectedRowKeys = ref<number[]>([]);
const recycleData = ref<PostType[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const statistics = ref({
  total: 0,
  active_count: 0,
  inactive_count: 0,
  today_created: 0,
});
const summaryCards = computed(() => [
  {
    desc: '当前岗位台账中的全部岗位数量。',
    icon: 'i-lucide-briefcase-business',
    label: '岗位总数',
    value: String(statistics.value.total),
  },
  {
    desc: '当前处于启用状态的岗位数量。',
    icon: 'i-lucide-badge-check',
    label: '启用岗位',
    value: String(statistics.value.active_count),
  },
  {
    desc: '当前处于禁用状态的岗位数量。',
    icon: 'i-lucide-ban',
    label: '禁用岗位',
    value: String(statistics.value.inactive_count),
  },
  {
    desc: '今天新增写入的岗位记录数量。',
    icon: 'i-lucide-calendar-plus',
    label: '今日新增',
    value: String(statistics.value.today_created),
  },
]);
const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const keyword = searchForm.keyword.trim();
  if (keyword !== '') {
    items.push({ label: '关键字', value: keyword });
  }
  if (typeof searchForm.status === 'number') {
    items.push({ label: '状态', value: postStatusText(searchForm.status) });
  }
  return items;
});
const { hasAccessByCodes } = useAccess();
const canCreatePosts = computed(() => hasAccessByCodes(['system.post.create']));
const canUpdatePosts = computed(() => hasAccessByCodes(['system.post.update']));
const canDeletePosts = computed(() => hasAccessByCodes(['system.post.delete']));
const canExportPosts = computed(() => hasAccessByCodes(['system.post.export']));
const canRecoveryPosts = computed(() => hasAccessByCodes(['system.post.recovery']));
const canRealDeletePosts = computed(() => hasAccessByCodes(['system.post.real-delete']));

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([postActions({} as PostType)], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([postRecycleActions({} as PostType)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '岗位编码', dataIndex: 'code', key: 'code', width: 160 },
  { title: '岗位名称', dataIndex: 'name', key: 'name', width: 200 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 90 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 110 },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '岗位编码', dataIndex: 'code', key: 'code', width: 160 },
  { title: '岗位名称', dataIndex: 'name', key: 'name', width: 200 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 90 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const tableScroll = computed(() => buildTableScrollX(columns.value, { selectionWidth: 60 }));
const recycleTableScroll = computed(() => buildTableScrollX(recycleColumns.value, {
  selectionWidth: 60,
}));

function postActions(record: PostType) {
  return [
    { label: '编辑', visible: canUpdatePosts.value, onClick: () => handleEdit(record) },
    { label: '查看', onClick: () => handleView(record) },
    { label: '删除', visible: canDeletePosts.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function postRecycleActions(record: PostType) {
  return [
    { label: '恢复', visible: canRecoveryPosts.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeletePosts.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'code', title: '岗位编码', width: 150 },
  { key: 'name', title: '岗位名称', width: 160 },
  { key: 'sort', title: '排序', width: 80 },
  { key: 'status', title: '状态', width: 90, formatter: (record: PostType) => statusText(record.status) },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const importColumns = [
  { key: 'code', title: '岗位编码', required: true, example: 'import_post', rule: '岗位编码需唯一，建议使用英文、数字或下划线。' },
  { key: 'name', title: '岗位名称', required: true, example: '导入岗位', rule: '岗位名称需唯一或保持业务可辨识。' },
  { key: 'sort', title: '排序', example: 0, parser: (value: any) => parseNumber(value, 0), rule: '数字越小排序越靠前，留空默认 0。' },
  { key: 'status', title: '状态', example: '启用', parser: (value: any) => parseStatus(value, 1), rule: '支持 启用/禁用 或 1/0，留空默认启用。' },
  { key: 'remark', title: '备注', example: '导入样例', rule: '可选，最多填写业务备注。' },
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
  if (!canDeletePosts.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const recycleRowSelection = computed(() => {
  if (!canRecoveryPosts.value && !canRealDeletePosts.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const loadPostList = async () => {
  if (loading.value) return;
  try {
    loading.value = true;
    const [response, statisticsResp] = await Promise.all([
      postApiService.getPostList({
        page: pagination.current,
        pageSize: pagination.pageSize,
        keyword: searchForm.keyword,
        status: searchForm.status,
      }),
      postApiService.getStatistics({
        keyword: searchForm.keyword,
        status: searchForm.status,
      }),
    ]);

    if (response?.items) {
      postData.value = response.items as PostType[];
      pagination.total = response.pageInfo?.total || response.total || 0;
    } else {
      postData.value = [];
      pagination.total = 0;
    }

    statistics.value = statisticsResp || statistics.value;
  } catch (error) {
    console.error('加载岗位列表失败:', error);
    message.error('获取岗位列表失败');
  } finally {
    loading.value = false;
  }
};

const loadRecycleList = async () => {
  if (!canRecoveryPosts.value && !canRealDeletePosts.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    selectedRecycleRowKeys.value = [];
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await postApiService.getRecycleList({
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      keyword: searchForm.keyword,
      status: searchForm.status,
    });

    if (response?.items) {
      recycleData.value = response.items as PostType[];
      recyclePagination.total = response.pageInfo?.total || response.total || 0;
    } else {
      recycleData.value = [];
      recyclePagination.total = 0;
    }
  } catch (error) {
    console.error('加载岗位回收站失败:', error);
    message.error('获取岗位回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
};

const handleSearch = () => {
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadPostList(), loadRecycleList()]);
};

const handleReset = () => {
  searchForm.keyword = '';
  searchForm.status = undefined;
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadPostList(), loadRecycleList()]);
};

const handleTableChange = (pag: any) => {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  loadPostList();
};

const handleRecycleTableChange = (pag: any) => {
  recyclePagination.current = pag.current;
  recyclePagination.pageSize = pag.pageSize;
  loadRecycleList();
};

const handleAdd = () => {
  formData.value = undefined;
  modalVisible.value = true;
};

const handleEdit = (record: PostType) => {
  formData.value = record;
  modalVisible.value = true;
};

const handleView = (record: PostType) => {
  currentPost.value = record;
  detailOpen.value = true;
};

const handleStatusChange = async (record: PostType, checked: boolean) => {
  try {
    await postApiService.updatePostStatus(record.id, checked ? 1 : 0);
    message.success('状态更新成功');
    loadPostList();
  } catch (error) {
    console.error('更新状态失败:', error);
    message.error('状态更新失败');
  }
};

const handleDelete = (record: PostType) => {
  Modal.confirm({
    title: '确认删除',
    content: `确定要删除岗位 "${record.name}" 吗？`,
    onOk: async () => {
      await postApiService.deletePost(record.id);
      message.success('删除成功');
      Promise.all([loadPostList(), loadRecycleList()]);
    },
  });
};

const handleBatchDelete = () => {
  if (selectedRowKeys.value.length === 0) {
    message.warning('请选择要删除的岗位');
    return;
  }

  const selectedPosts = postData.value.filter((post) => selectedRowKeys.value.includes(post.id));
  const postNames = selectedPosts.map((post) => post.name).join('、');

  Modal.confirm({
    title: '确认批量删除',
    content: `确定要删除选中的 ${selectedRowKeys.value.length} 个岗位吗？\n\n岗位列表：${postNames}`,
    okType: 'danger',
    onOk: async () => {
      await postApiService.batchDeletePosts(selectedRowKeys.value);
      message.success(`成功删除 ${selectedRowKeys.value.length} 个岗位`);
      selectedRowKeys.value = [];
      Promise.all([loadPostList(), loadRecycleList()]);
    },
  });
};

const handleRecovery = (record: PostType) => {
  Modal.confirm({
    title: '确认恢复',
    content: `确定要恢复岗位 "${record.name}" 吗？`,
    onOk: async () => {
      await postApiService.recoveryPosts([record.id]);
      message.success('恢复成功');
      Promise.all([loadPostList(), loadRecycleList()]);
    },
  });
};

const handleRealDelete = (record: PostType) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: `彻底删除后不可恢复，确定删除岗位 "${record.name}" 吗？`,
    okType: 'danger',
    onOk: async () => {
      await postApiService.realDeletePosts([record.id]);
      message.success('彻底删除成功');
      Promise.all([loadPostList(), loadRecycleList()]);
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的岗位');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 个岗位吗？`,
    onOk: async () => {
      await postApiService.recoveryPosts(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量恢复成功');
      Promise.all([loadPostList(), loadRecycleList()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的岗位');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `彻底删除后不可恢复，确定删除选中的 ${selectedRecycleRowKeys.value.length} 个岗位吗？`,
    okType: 'danger',
    onOk: async () => {
      await postApiService.realDeletePosts(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量彻底删除成功');
      Promise.all([loadPostList(), loadRecycleList()]);
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<PostType>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => postApiService.getPostList({
      keyword: searchForm.keyword,
      page,
      pageSize,
      status: searchForm.status,
    }),
    filename: `posts_${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '岗位管理',
    });
  } finally {
    exporting.value = false;
  }
};

const handleImport = async () => {
  await openCrudImport<Record<string, any>>({
    afterDone: loadPostList,
    columns: importColumns,
    moduleName: '岗位',
    rules: [
      '岗位编码需满足后端唯一性校验，重复行会在结果中标记失败。',
    ],
    submit: async (payload) => {
      await postApiService.createPost({
        code: String(payload.code || ''),
        name: String(payload.name || ''),
        remark: String(payload.remark || ''),
        sort: parseNumber(payload.sort, 0),
        status: parseStatus(payload.status, 1),
      });
    },
  });
};

const handleFormSuccess = () => {
  modalVisible.value = false;
  Promise.all([loadPostList(), loadRecycleList()]);
};

function postStatusText(status?: number) {
  return status === 1 ? '启用' : '禁用';
}

onMounted(() => {
  Promise.all([loadPostList(), loadRecycleList()]);
});
</script>
