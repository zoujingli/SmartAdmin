<template>
  <Page title="部门管理">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button
            v-if="canDeleteDepts"
            danger
            :disabled="selectedRowKeys.length === 0"
            @click="handleBatchDelete"
          >
            <span class="i-lucide-trash-2" />
            批量删除
          </Button>
          <Button v-if="canCreateDepts" type="primary" @click="handleAdd"><span class="i-lucide-plus" />新增部门</Button>
          <Button v-if="canCreateDepts" @click="handleImport"><span class="i-lucide-upload" />导入</Button>
          <Button v-if="canExportDepts" :loading="exporting" @click="handleExport"><span class="i-lucide-download" />导出</Button>
          <Button @click="handleExpandAll"><span class="i-lucide-expand" />展开全部</Button>
          <Button @click="handleCollapseAll"><span class="i-lucide-collapse" />收起全部</Button>
        </template>
        <template v-else>
          <Button v-if="canRecoveryDepts" :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRecovery">批量恢复</Button>
          <Button v-if="canRealDeleteDepts" danger :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRealDelete">批量彻底删除</Button>
        </template>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="data" tab="数据列表">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="名称搜索"><Input v-model:value="searchForm.name" placeholder="请输入部门名称" allow-clear /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="编码搜索"><Input v-model:value="searchForm.code" placeholder="请输入部门编码" allow-clear /></SearchField>
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
              empty-text="当前显示全部部门树节点，可按部门名称、编码和状态快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card>
            <CrudTableHeader
              title="部门列表"
              description="维护部门层级、负责人和状态，支持新增子部门、查看部门成员与批量删除。"
              :count-text="`${stats.totalDepts} 个节点`"
            />
            <Table
              :columns="columns"
              :data-source="deptData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无部门记录')"
              :pagination="false"
              :row-selection="rowSelection"
              :scroll="tableScroll"
              row-key="id"
              :expanded-row-keys="expandedRowKeys"
              :expand-row-by-click="false"
              :default-expand-all-rows="false"
              :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
              @expand="handleExpand"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'status'">
                  <CrudStatusTag :value="record.status === 1" />
                </template>
                <template v-else-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft">
                    <div class="truncate">{{ record.name }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="deptActions(record as DeptType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryDepts || canRealDeleteDepts" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除部门"
              description="回收站中的部门可恢复到主列表；彻底删除后将不可恢复，请谨慎操作。"
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
                <template v-if="column.key === 'status'">
                  <CrudStatusTag :value="record.status === 1" />
                </template>
                <template v-else-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft">
                    <div class="truncate">{{ record.name }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="deptRecycleActions(record as DeptType)" />
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
      :dept-tree-options="deptTreeOptions"
      @success="handleFormSuccess"
    />

    <Modal
      :open="detailOpen"
      title="部门详情"
      :width="popupWidth.xl"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentDept">
        <CrudDetailHero
          icon="i-lucide-building-2"
          :lines="[
            `负责人：${currentDept.leader || '-'}`,
            `联系电话：${currentDept.phone || '-'}`,
            `邮箱：${currentDept.email || '-'}`,
          ]"
          :tags="[
            { label: currentDept.code || '未设置编码' },
            { color: currentDept.status === 1 ? 'success' : 'default', label: deptStatusText(currentDept.status) },
            { label: `层级 ${currentDept.level ?? 0}` },
          ]"
          :title="currentDept.name || '-'"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="部门 ID">{{ currentDept.id }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentDept.createdAt || '-' }}</DescriptionsItem>
          <DescriptionsItem label="部门名称">{{ currentDept.name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="部门编码">{{ currentDept.code || '-' }}</DescriptionsItem>
          <DescriptionsItem label="负责人">{{ currentDept.leader || '-' }}</DescriptionsItem>
          <DescriptionsItem label="联系电话">{{ currentDept.phone || '-' }}</DescriptionsItem>
          <DescriptionsItem label="邮箱">{{ currentDept.email || '-' }}</DescriptionsItem>
          <DescriptionsItem label="排序">{{ currentDept.sort ?? 0 }}</DescriptionsItem>
          <DescriptionsItem label="状态">{{ deptStatusText(currentDept.status) }}</DescriptionsItem>
          <DescriptionsItem label="父级部门">{{ currentDept.parentId || 0 }}</DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentDept.remark || '-' }}</DescriptionsItem>
        </CrudDetailDescriptions>
      </CrudDetailPanel>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAccess } from '@vben/access';
import {
  CrudDetailDescriptions,
  CrudDetailHero,
  CrudDetailPanel,
  CrudFilterSummary,
  buildCrudTableLocale,
  CrudStatusTag,
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
  Table,
  Tabs,
  TabPane,
  Tooltip,
} from 'ant-design-vue';

import { deptApiService } from '#/api';
import {
  exportCrudXlsx,
  flattenTreeRows,
  openCrudImport,
  parseNumber,
  parseStatus,
  statusText,
} from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { popupWidth } from '#/utils/popup';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

import type { DeptSearchForm, DeptType } from './types';
import FormModal from './modules/form.vue';

const searchForm = reactive<DeptSearchForm>({
  code: '',
  name: '',
  status: undefined,
});
const router = useRouter();
const modalVisible = ref(false);
const formData = ref<DeptType>();
const activeTab = ref('data');
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const currentDept = ref<DeptType | null>(null);
const detailOpen = ref(false);

const rawTree = ref<DeptType[]>([]);
const deptData = ref<DeptType[]>([]);
const expandedRowKeys = ref<number[]>([]);
const selectedRowKeys = ref<number[]>([]);
const recycleData = ref<DeptType[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const deptTreeOptions = ref<any[]>([{ id: 0, name: '根目录', children: [] }]);
const { hasAccessByCodes } = useAccess();
const canCreateDepts = computed(() => hasAccessByCodes(['system.dept.create']));
const canUpdateDepts = computed(() => hasAccessByCodes(['system.dept.update']));
const canDeleteDepts = computed(() => hasAccessByCodes(['system.dept.delete']));
const canExportDepts = computed(() => hasAccessByCodes(['system.dept.export']));
const canRecoveryDepts = computed(() => hasAccessByCodes(['system.dept.recovery']));
const canRealDeleteDepts = computed(() => hasAccessByCodes(['system.dept.real-delete']));
const canAccessUsers = computed(() => hasAccessByCodes(['system.user.index']));

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([deptActions({} as DeptType)], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([deptRecycleActions({} as DeptType)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '部门名称', dataIndex: 'name', key: 'name', width: 180 },
  { title: '部门编码', dataIndex: 'code', key: 'code', width: 140 },
  { title: '负责人', dataIndex: 'leader', key: 'leader', width: 120 },
  { title: '联系电话', dataIndex: 'phone', key: 'phone', width: 150 },
  { title: '邮箱', dataIndex: 'email', key: 'email', width: 180 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '创建时间', dataIndex: 'createdAt', key: 'createdAt', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '部门名称', dataIndex: 'name', key: 'name', width: 180 },
  { title: '部门编码', dataIndex: 'code', key: 'code', width: 140 },
  { title: '负责人', dataIndex: 'leader', key: 'leader', width: 120 },
  { title: '联系电话', dataIndex: 'phone', key: 'phone', width: 150 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const tableScroll = computed(() => buildTableScrollX(columns.value, { selectionWidth: 60 }));
const recycleTableScroll = computed(() => buildTableScrollX(recycleColumns.value, {
  selectionWidth: 60,
}));

function deptActions(record: DeptType) {
  return [
    { label: '编辑', visible: canUpdateDepts.value, onClick: () => handleEdit(record) },
    { label: '查看', onClick: () => handleView(record) },
    { label: '新增子部门', visible: canCreateDepts.value, onClick: () => handleAddChild(record) },
    { label: '查看用户', visible: canAccessUsers.value, onClick: () => handleViewUsers(record) },
    { label: '删除', visible: canDeleteDepts.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function deptRecycleActions(record: DeptType) {
  return [
    { label: '恢复', visible: canRecoveryDepts.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteDepts.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'name', title: '部门名称', width: 160 },
  { key: 'code', title: '部门编码', width: 140 },
  { key: 'leader', title: '负责人', width: 120 },
  { key: 'phone', title: '联系电话', width: 140 },
  { key: 'email', title: '邮箱', width: 180 },
  { key: 'sort', title: '排序', width: 80 },
  { key: 'status', title: '状态', width: 90, formatter: (record: DeptType) => statusText(record.status) },
  { key: 'createdAt', title: '创建时间', width: 180, formatter: (record: DeptType) => record.createdAt || (record as any).created_at || '' },
];

const importColumns = [
  { key: 'name', title: '部门名称', required: true, example: '导入部门', rule: '部门名称需在同级内保持清晰可辨。' },
  { key: 'code', title: '部门编码', required: true, example: 'import_dept', rule: '部门编码需唯一，建议使用英文、数字或下划线。' },
  { key: 'leader', title: '负责人', example: '张三', rule: '可选，填写负责人姓名。' },
  { key: 'phone', title: '联系电话', example: '13800000002', rule: '可选，填写部门联系电话。' },
  { key: 'email', title: '邮箱', example: 'dept@example.com', rule: '可选，填写部门邮箱。' },
  { key: 'sort', title: '排序', example: 0, parser: (value: any) => parseNumber(value, 0), rule: '数字越小排序越靠前，留空默认 0。' },
  { key: 'status', title: '状态', example: '启用', parser: (value: any) => parseStatus(value, 1), rule: '支持 启用/禁用 或 1/0，留空默认启用。' },
  { key: 'remark', title: '备注', example: '导入样例', rule: '可选，最多填写业务备注。' },
];

const stats = ref({
  totalDepts: 0,
  maxLevel: 0,
  rootDepts: 0,
  leafDepts: 0,
});
const summaryCards = computed(() => [
  {
    desc: '当前部门树中的全部部门节点数量。',
    icon: 'i-lucide-building-2',
    label: '总部门数',
    value: String(stats.value.totalDepts),
  },
  {
    desc: '当前部门结构的最大层级深度。',
    icon: 'i-lucide-layers-3',
    label: '最大层级',
    value: String(stats.value.maxLevel),
  },
  {
    desc: '直属于根节点的一级部门数量。',
    icon: 'i-lucide-network',
    label: '根部门数',
    value: String(stats.value.rootDepts),
  },
  {
    desc: '当前没有下级部门的叶子节点数量。',
    icon: 'i-lucide-git-branch-plus',
    label: '叶子部门数',
    value: String(stats.value.leafDepts),
  },
]);
const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const name = (searchForm.name || '').trim();
  if (name !== '') {
    items.push({ label: '部门名称', value: name });
  }
  const code = (searchForm.code || '').trim();
  if (code !== '') {
    items.push({ label: '部门编码', value: code });
  }
  if (typeof searchForm.status === 'number') {
    items.push({ label: '状态', value: deptStatusText(searchForm.status) });
  }
  return items;
});

const rowSelection = computed(() => {
  if (!canDeleteDepts.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const recyclePagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const recycleRowSelection = computed(() => {
  if (!canRecoveryDepts.value && !canRealDeleteDepts.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const processTreeData = (nodes: any[], level = 0): DeptType[] => {
  return (nodes || []).map((item) => ({
    ...item,
    parentId: item.pid || 0,
    createdAt: item.created_at,
    updatedAt: item.updated_at,
    level,
    hasChildren: Array.isArray(item.children) && item.children.length > 0,
    children: processTreeData(item.children || [], level + 1),
  }));
};

const flattenTree = (nodes: DeptType[]): DeptType[] => {
  const result: DeptType[] = [];
  const visit = (list: DeptType[]) => {
    for (const node of list) {
      result.push(node);
      if (node.children?.length) visit(node.children);
    }
  };
  visit(nodes);
  return result;
};

const filterTree = (nodes: DeptType[], keyword: string, code: string, status?: number): DeptType[] => {
  const key = keyword.trim().toLowerCase();
  const codeKey = code.trim().toLowerCase();

  return nodes
    .map((node) => {
      const children = filterTree(node.children || [], keyword, code, status);
      const matchName = !key || (node.name || '').toLowerCase().includes(key);
      const matchCode = !codeKey || (node.code || '').toLowerCase().includes(codeKey);
      const matchStatus = status === undefined || Number(node.status) === Number(status);
      if ((matchName && matchCode && matchStatus) || children.length > 0) {
        return { ...node, children };
      }
      return null;
    })
    .filter(Boolean) as DeptType[];
};

const collectExpandedRowKeys = (nodes: DeptType[]): number[] => {
  const keys: number[] = [];

  const visit = (items: DeptType[]) => {
    for (const item of items) {
      if (item.children?.length) {
        keys.push(item.id);
        visit(item.children);
      }
    }
  };

  visit(nodes);

  return keys;
};

const applyTreeFilters = () => {
  const filtered = filterTree(rawTree.value, searchForm.name || '', searchForm.code || '', searchForm.status);
  deptData.value = filtered;

  const hasFilters = Boolean(searchForm.name?.trim()) || Boolean(searchForm.code?.trim()) || searchForm.status !== undefined;
  expandedRowKeys.value = hasFilters ? collectExpandedRowKeys(filtered) : [];
};

const refreshStats = (tree: DeptType[]) => {
  const all = flattenTree(tree);
  stats.value.totalDepts = all.length;
  stats.value.maxLevel = all.length > 0 ? Math.max(...all.map((item) => item.level || 0)) : 0;
  stats.value.rootDepts = tree.length;
  stats.value.leafDepts = all.filter((item) => !item.children || item.children.length === 0).length;
};

const loadDeptList = async () => {
  if (loading.value) return;
  try {
    loading.value = true;
    const treeData = await deptApiService.getDeptTree();
    rawTree.value = processTreeData(treeData as any[]);
    deptTreeOptions.value = [{ id: 0, name: '根目录', children: treeData || [] }];

    applyTreeFilters();
    refreshStats(rawTree.value);
  } catch (error) {
    console.error('加载部门列表失败:', error);
    message.error('获取部门列表失败');
  } finally {
    loading.value = false;
  }
};

const loadRecycleList = async () => {
  if (!canRecoveryDepts.value && !canRealDeleteDepts.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await deptApiService.getRecycleList({
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      code: searchForm.code,
      name: searchForm.name,
      status: searchForm.status,
    });

    recycleData.value = (response?.items || []).map((item: any) => ({
      ...item,
      parentId: item.pid || 0,
      createdAt: item.created_at,
      updatedAt: item.updated_at,
    })) as DeptType[];
    recyclePagination.total = response?.pageInfo?.total || 0;
  } catch (error) {
    console.error('加载部门回收站失败:', error);
    message.error('获取部门回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
};

const handleSearch = () => {
  applyTreeFilters();
  recyclePagination.current = 1;
  loadRecycleList();
};

const handleReset = () => {
  searchForm.code = '';
  searchForm.name = '';
  searchForm.status = undefined;
  deptData.value = [...rawTree.value];
  expandedRowKeys.value = [];
  recyclePagination.current = 1;
  loadRecycleList();
};

const handleExpandAll = () => {
  expandedRowKeys.value = collectExpandedRowKeys(deptData.value);
};

const handleCollapseAll = () => {
  expandedRowKeys.value = [];
};

const handleExpand = (expanded: boolean, record: DeptType) => {
  const current = new Set(expandedRowKeys.value);
  if (expanded) {
    current.add(record.id);
  } else {
    current.delete(record.id);
  }
  expandedRowKeys.value = [...current];
};

const handleAdd = () => {
  formData.value = undefined;
  modalVisible.value = true;
};

const handleEdit = (record: DeptType) => {
  formData.value = record;
  modalVisible.value = true;
};

const handleView = (record: DeptType) => {
  currentDept.value = record;
  detailOpen.value = true;
};

const handleAddChild = (record: DeptType) => {
  formData.value = {
    ...record,
    id: 0,
    parentId: record.id,
    name: '',
    code: '',
    leader: '',
    phone: '',
    email: '',
    status: 1,
    sort: 0,
    remark: '',
  } as DeptType;
  modalVisible.value = true;
};

const handleViewUsers = (record: DeptType) => {
  router.push({
    path: '/system/user',
    query: { deptId: String(record.id) },
  }).catch(() => {});
};

const handleDelete = (record: DeptType) => {
  Modal.confirm({
    title: '确认删除',
    content: `确定要删除部门 "${record.name}" 吗？`,
    okText: '确认',
    cancelText: '取消',
    onOk: async () => {
      await deptApiService.deleteDept(record.id);
      message.success('删除成功');
      Promise.all([loadDeptList(), loadRecycleList()]);
    },
  });
};

const handleBatchDelete = () => {
  if (selectedRowKeys.value.length === 0) {
    message.warning('请选择要删除的部门');
    return;
  }

  const selected = flattenTree(rawTree.value).filter((item) => selectedRowKeys.value.includes(item.id));
  const deptNames = selected.map((item) => item.name).join('、');

  Modal.confirm({
    title: '确认批量删除',
    content: `确定要删除选中的 ${selectedRowKeys.value.length} 个部门吗？\n\n部门列表：${deptNames}`,
    okText: '确认删除',
    cancelText: '取消',
    okType: 'danger',
    onOk: async () => {
      await Promise.all(selectedRowKeys.value.map((id) => deptApiService.deleteDept(id)));
      message.success(`成功删除 ${selectedRowKeys.value.length} 个部门`);
      selectedRowKeys.value = [];
      Promise.all([loadDeptList(), loadRecycleList()]);
    },
  });
};

const handleRecycleTableChange = (pag: any) => {
  recyclePagination.current = pag.current;
  recyclePagination.pageSize = pag.pageSize;
  loadRecycleList();
};

const handleRecovery = (record: DeptType) => {
  Modal.confirm({
    title: '确认恢复',
    content: `确定要恢复部门 "${record.name}" 吗？`,
    onOk: async () => {
      await deptApiService.recoveryDepts([record.id]);
      message.success('恢复成功');
      Promise.all([loadDeptList(), loadRecycleList()]);
    },
  });
};

const handleRealDelete = (record: DeptType) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: `确定要彻底删除部门 "${record.name}" 吗？此操作不可恢复。`,
    okType: 'danger',
    onOk: async () => {
      await deptApiService.realDeleteDepts([record.id]);
      message.success('彻底删除成功');
      loadRecycleList();
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的部门');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 个部门吗？`,
    onOk: async () => {
      await deptApiService.recoveryDepts(selectedRecycleRowKeys.value);
      message.success(`成功恢复 ${selectedRecycleRowKeys.value.length} 个部门`);
      selectedRecycleRowKeys.value = [];
      Promise.all([loadDeptList(), loadRecycleList()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的部门');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `确定要彻底删除选中的 ${selectedRecycleRowKeys.value.length} 个部门吗？此操作不可恢复。`,
    okType: 'danger',
    onOk: async () => {
      await deptApiService.realDeleteDepts(selectedRecycleRowKeys.value);
      message.success(`成功彻底删除 ${selectedRecycleRowKeys.value.length} 个部门`);
      selectedRecycleRowKeys.value = [];
      loadRecycleList();
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<DeptType>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => deptApiService.getDeptList({
      code: searchForm.code,
      name: searchForm.name,
      page,
      pageSize,
      status: searchForm.status,
    }) as any,
    filename: `depts_${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '部门管理',
    transformItems: (items) => flattenTreeRows(items),
    });
  } finally {
    exporting.value = false;
  }
};

const handleImport = async () => {
  await openCrudImport<Record<string, any>>({
    afterDone: loadDeptList,
    columns: importColumns,
    moduleName: '部门',
    rules: [
      '部门导入默认挂到根目录，如需调整层级请导入后编辑父级。',
      '部门编码需满足后端唯一性校验，重复行会在结果中标记失败。',
    ],
    submit: async (payload) => {
      await deptApiService.createDept({
        code: String(payload.code || ''),
        email: String(payload.email || ''),
        leader: String(payload.leader || ''),
        name: String(payload.name || ''),
        phone: String(payload.phone || ''),
        pid: 0,
        remark: String(payload.remark || ''),
        sort: parseNumber(payload.sort, 0),
        status: parseStatus(payload.status, 1),
      } as any);
    },
  });
};

const handleFormSuccess = () => {
  modalVisible.value = false;
  Promise.all([loadDeptList(), loadRecycleList()]);
};

function deptStatusText(status?: number) {
  return status === 1 ? '启用' : '禁用';
}

onMounted(() => {
  Promise.all([loadDeptList(), loadRecycleList()]);
});
</script>
