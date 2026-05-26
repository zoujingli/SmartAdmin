<template>
  <Page title="角色管理">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button
            v-if="canDeleteRoles"
            danger
            :disabled="selectedRowKeys.length === 0"
            @click="handleBatchDelete"
          >
            <span class="i-lucide-trash-2" />
            批量删除
          </Button>
          <Button v-if="canCreateRoles" type="primary" @click="handleAdd"><span class="i-lucide-plus" />新增角色</Button>
          <Button v-if="canCreateRoles" @click="handleImport"><span class="i-lucide-upload" />导入</Button>
          <Button v-if="canExportRoles" :loading="exporting" @click="handleExport"><span class="i-lucide-download" />导出</Button>
        </template>
        <template v-else>
          <Button v-if="canRecoveryRoles" :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRecovery">批量恢复</Button>
          <Button v-if="canRealDeleteRoles" danger :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRealDelete">批量彻底删除</Button>
        </template>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="data" tab="数据列表">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="名称搜索"><Input v-model:value="searchForm.name" placeholder="请输入角色名称" allow-clear /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="编码搜索"><Input v-model:value="searchForm.code" placeholder="请输入角色编码" allow-clear /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="当前状态">
                  <Select v-model:value="searchForm.status" class="w-full" placeholder="请选择" allow-clear>
                    <SelectOption :value="1">启用</SelectOption>
                    <SelectOption :value="0">禁用</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="数据范围">
                  <Select v-model:value="searchForm.scope" class="w-full" placeholder="请选择" allow-clear>
                    <SelectOption v-for="item in ROLE_SCOPE_OPTIONS" :key="item.value" :value="item.value">
                      {{ item.label }}
                    </SelectOption>
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
              empty-text="当前显示全部角色记录，可按名称、编码、状态和数据范围快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card>
            <CrudTableHeader
              title="角色列表"
              description="维护角色状态、数据权限和授权菜单，支持新增、编辑、授权与删除。"
              :count-text="`${pagination.total} 条记录`"
            />
            <Table
              :columns="columns"
              :data-source="roleData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无角色记录')"
              :pagination="pagination"
              :row-selection="rowSelection"
              :scroll="tableScrollX"
              row-key="id"
              @change="handleTableChange"
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
                <template v-else-if="column.key === 'scope'">
                  <CrudToneTag :color="roleScopeColor(record.scope)" :text="roleScopeText(record.scope)" />
                </template>
                <template v-else-if="column.key === 'menuNames'">
                  <CrudTagList :items="record.menuNames || []" color="blue" />
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="roleActions(record as RoleType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryRoles || canRealDeleteRoles" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除角色"
              description="回收站中的角色可恢复到主列表；彻底删除后将不可恢复。"
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
              :scroll="recycleTableScrollX"
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
                  <CrudTableActions :actions="roleRecycleActions(record as RoleType)" />
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
      :can-assign-permissions="canAssignRoles"
      :menu-tree-options="menuTreeOptions"
      @success="handleFormSuccess"
    />

    <Modal
      :open="detailOpen"
      title="角色详情"
      :width="popupWidth.lg"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentRole">
        <CrudDetailHero
          icon="i-lucide-shield"
          :lines="[
            `排序：${currentRole.sort}`,
            `授权菜单：${currentRole.menuNames?.length || 0} 项`,
          ]"
          :tags="[
            { label: currentRole.code || '未设置编码' },
            { color: currentRole.status === 1 ? 'success' : 'default', label: roleStatusText(currentRole.status) },
            { color: 'processing', label: roleScopeText(currentRole.scope) },
          ]"
          :title="currentRole.name"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="角色 ID">{{ currentRole.id }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentRole.created_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="角色名称">{{ currentRole.name }}</DescriptionsItem>
          <DescriptionsItem label="角色编码">{{ currentRole.code || '-' }}</DescriptionsItem>
          <DescriptionsItem label="状态">{{ roleStatusText(currentRole.status) }}</DescriptionsItem>
          <DescriptionsItem label="数据范围">{{ roleScopeText(currentRole.scope) }}</DescriptionsItem>
          <DescriptionsItem label="排序">{{ currentRole.sort }}</DescriptionsItem>
          <DescriptionsItem label="更新时间">{{ currentRole.updated_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="授权菜单" :span="2">
            <CrudDetailTagList :items="currentRole.menuNames || []" />
          </DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentRole.remark || '-' }}</DescriptionsItem>
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
  CrudDetailTagList,
  CrudFilterSummary,
  buildCrudTableLocale,
  CrudStatusTag,
  CrudStatCards,
  CrudTagList,
  CrudToneTag,
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

import { roleApiService } from '#/api';
import {
  exportCrudXlsx,
  openCrudImport,
  parseNumber,
  parseStatus,
  statusText,
} from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { popupWidth } from '#/utils/popup';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

import { ROLE_SCOPE_DEFAULT, ROLE_SCOPE_OPTIONS, parseRoleScope, roleScopeColor, roleScopeText } from './constants';
import FormModal from './modules/form.vue';
import type { RoleSearchForm, RoleType } from './types';

const searchForm = reactive<RoleSearchForm>({
  code: '',
  name: '',
  scope: undefined,
  status: undefined,
});

const modalVisible = ref(false);
const formData = ref<RoleType>();
const currentRole = ref<RoleType | null>(null);
const detailOpen = ref(false);
const activeTab = ref('data');
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const roleData = ref<RoleType[]>([]);
const selectedRowKeys = ref<number[]>([]);
const recycleData = ref<RoleType[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const menuTreeOptions = ref<any[]>([]);
const menuCodeToId = ref(new Map<string, number>());
const { hasAccessByCodes } = useAccess();
const canAssignRoles = computed(() => hasAccessByCodes(['system.role.assign']));
const canCreateRoles = computed(() => hasAccessByCodes(['system.role.create']));
const canUpdateRoles = computed(() => hasAccessByCodes(['system.role.update']));
const canDeleteRoles = computed(() => hasAccessByCodes(['system.role.delete']));
const canExportRoles = computed(() => hasAccessByCodes(['system.role.export']));
const canRecoveryRoles = computed(() => hasAccessByCodes(['system.role.recovery']));
const canRealDeleteRoles = computed(() => hasAccessByCodes(['system.role.real-delete']));
const summaryCards = computed(() => [
  {
    desc: '当前角色列表中的有效角色数量。',
    icon: 'i-lucide-shield',
    label: '总角色数',
    value: String(stats.value.totalRoles),
  },
  {
    desc: '当前处于启用状态的角色数量。',
    icon: 'i-lucide-badge-check',
    label: '启用角色',
    value: String(stats.value.activeRoles),
  },
  {
    desc: '当前处于禁用状态的角色数量。',
    icon: 'i-lucide-ban',
    label: '禁用角色',
    value: String(stats.value.inactiveRoles),
  },
  {
    desc: '今天新增写入的角色记录数量。',
    icon: 'i-lucide-calendar-plus',
    label: '今日新增',
    value: String(stats.value.todayRoles),
  },
]);
const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const name = searchForm.name?.trim() || '';
  if (name !== '') {
    items.push({ label: '角色名称', value: name });
  }
  const code = searchForm.code?.trim() || '';
  if (code !== '') {
    items.push({ label: '角色编码', value: code });
  }
  if (typeof searchForm.status === 'number') {
    items.push({ label: '状态', value: roleStatusText(searchForm.status) });
  }
  if (typeof searchForm.scope === 'number') {
    items.push({ label: '数据范围', value: roleScopeText(searchForm.scope) });
  }
  return items;
});

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([roleActions({} as RoleType)], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([roleRecycleActions({} as RoleType)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '角色名称', dataIndex: 'name', key: 'name' },
  { title: '数据范围', dataIndex: 'scope', key: 'scope', width: 120 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '权限菜单', dataIndex: 'menuNames', key: 'menuNames' },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '角色名称', dataIndex: 'name', key: 'name' },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const tableScrollX = computed(() => buildTableScrollX(columns.value, { selectionWidth: 60 }));
const recycleTableScrollX = computed(() => buildTableScrollX(recycleColumns.value, {
  selectionWidth: 60,
}));

function roleActions(record: RoleType) {
  return [
    { label: '编辑', visible: canUpdateRoles.value, onClick: () => handleEdit(record) },
    { label: '查看', onClick: () => handleView(record) },
    { label: '分配权限', visible: canAssignRoles.value, onClick: () => handleAssignPermissions(record) },
    { label: '删除', visible: canDeleteRoles.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function roleRecycleActions(record: RoleType) {
  return [
    { label: '恢复', visible: canRecoveryRoles.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteRoles.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'name', title: '角色名称', width: 150 },
  { key: 'code', title: '角色编码', width: 150 },
  { key: 'scope', title: '数据范围', width: 130, formatter: (record: RoleType) => roleScopeText(record.scope) },
  { key: 'sort', title: '排序', width: 80 },
  { key: 'status', title: '状态', width: 90, formatter: (record: RoleType) => statusText(record.status) },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const importColumns = [
  { key: 'name', title: '角色名称', required: true, example: '导入角色', rule: '角色名称需唯一。' },
  { key: 'code', title: '角色编码', required: true, example: 'import_role', rule: '权限编码需唯一，建议使用英文、数字或下划线。' },
  { key: 'scope', title: '数据范围', example: '本人数据', parser: (value: any) => parseRoleScope(value), rule: '支持 全部数据/本部门数据/本部门及以下/本人数据 或 1/2/3/4。' },
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

const stats = ref({
  totalRoles: 0,
  activeRoles: 0,
  inactiveRoles: 0,
  todayRoles: 0,
});

const rowSelection = computed(() => {
  if (!canDeleteRoles.value) {
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
  if (!canRecoveryRoles.value && !canRealDeleteRoles.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const rebuildMenuCodeLookup = () => {
  const nextMap = new Map<string, number>();

  const visit = (nodes: any[]) => {
    for (const node of nodes ?? []) {
      if (node?.code && typeof node?.id === 'number') {
        nextMap.set(String(node.code), node.id);
      }
      if (Array.isArray(node?.children) && node.children.length > 0) {
        visit(node.children);
      }
    }
  };

  visit(menuTreeOptions.value);
  menuCodeToId.value = nextMap;
};

const collectMenuIds = (menus: any[]) => {
  const ids: number[] = [];

  const visit = (nodes: any[]) => {
    for (const node of nodes ?? []) {
      if (typeof node?.id === 'number') {
        ids.push(node.id);
      }
      if (Array.isArray(node?.children) && node.children.length > 0) {
        visit(node.children);
      }
    }
  };

  visit(menus);

  return Array.from(new Set(ids));
};

const mapNodesToMenuIds = (nodes: string[]) => {
  if ((nodes ?? []).includes('*')) {
    return collectMenuIds(menuTreeOptions.value);
  }

  const ids: number[] = [];
  for (const node of nodes ?? []) {
    const id = menuCodeToId.value.get(node);
    if (typeof id === 'number') ids.push(id);
  }
  return Array.from(new Set(ids));
};

const loadRoleList = async () => {
  if (loading.value) return;
  try {
    loading.value = true;
    const response = await roleApiService.getRoleList({
      page: pagination.current,
      pageSize: pagination.pageSize,
      ...searchForm,
    });

    if (response?.items) {
      roleData.value = response.items as RoleType[];
      pagination.total = response.pageInfo?.total || 0;
      const statistics = response.extra?.statistics;
      stats.value.totalRoles = statistics?.total || 0;
      stats.value.activeRoles = statistics?.active_count || 0;
      stats.value.inactiveRoles = statistics?.inactive_count || 0;
      stats.value.todayRoles = statistics?.today || 0;
    } else {
      roleData.value = [];
      pagination.total = 0;
    }
  } catch (error) {
    console.error('加载角色列表失败:', error);
    message.error('获取角色列表失败');
  } finally {
    loading.value = false;
  }
};

const loadRecycleList = async () => {
  if (!canRecoveryRoles.value && !canRealDeleteRoles.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    selectedRecycleRowKeys.value = [];
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await roleApiService.getRecycleList({
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      ...searchForm,
    });

    if (response?.items) {
      recycleData.value = response.items as RoleType[];
      recyclePagination.total = response.pageInfo?.total || 0;
    } else {
      recycleData.value = [];
      recyclePagination.total = 0;
    }
  } catch (error) {
    console.error('加载角色回收站失败:', error);
    message.error('获取角色回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
};

const loadOptions = async () => {
  if (!canAssignRoles.value) {
    menuTreeOptions.value = [];
    menuCodeToId.value = new Map();
    return;
  }

  try {
    menuTreeOptions.value = await roleApiService.getRolePermissionTree();
    rebuildMenuCodeLookup();
  } catch (error) {
    console.error('加载菜单树失败:', error);
    message.error('加载角色授权菜单失败');
  }
};

const prepareRoleForEdit = async (record: RoleType) => {
  const detailPromise = roleApiService.getRoleDetail(record.id).catch(() => record as any);

  if (!canAssignRoles.value) {
    const detail = await detailPromise;
    formData.value = {
      ...(record as any),
      ...(detail as any),
      menuIds: [],
      allPermissions: false,
    };
    return;
  }

  if (menuTreeOptions.value.length === 0) await loadOptions();

  const [detail, nodes] = await Promise.all([
    detailPromise,
    roleApiService.getRoleNodes(record.id).catch(() => [] as string[]),
  ]);

  formData.value = {
    ...(record as any),
    ...(detail as any),
    menuIds: mapNodesToMenuIds(nodes || []),
    allPermissions: (nodes || []).includes('*'),
  };
};

const handleSearch = () => {
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadRoleList(), loadRecycleList()]);
};

const handleReset = () => {
  searchForm.code = '';
  searchForm.name = '';
  searchForm.scope = undefined;
  searchForm.status = undefined;
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadRoleList(), loadRecycleList()]);
};

const handleTableChange = (pag: any) => {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  loadRoleList();
};

const handleRecycleTableChange = (pag: any) => {
  recyclePagination.current = pag.current;
  recyclePagination.pageSize = pag.pageSize;
  loadRecycleList();
};

const handleAdd = async () => {
  try {
    loading.value = true;
    if (canAssignRoles.value && menuTreeOptions.value.length === 0) {
      await loadOptions();
    }
    formData.value = undefined;
    modalVisible.value = true;
  } finally {
    loading.value = false;
  }
};

const handleEdit = async (record: RoleType) => {
  try {
    loading.value = true;
    await prepareRoleForEdit(record);
    modalVisible.value = true;
  } finally {
    loading.value = false;
  }
};

const handleView = (record: RoleType) => {
  currentRole.value = record;
  detailOpen.value = true;
};

const handleAssignPermissions = async (record: RoleType) => {
  await handleEdit(record);
};

const handleDelete = async (record: RoleType) => {
  Modal.confirm({
    title: '确认删除',
    content: `确定要删除角色 "${record.name}" 吗？`,
    okText: '确认',
    cancelText: '取消',
    onOk: async () => {
      await roleApiService.deleteRole(record.id);
      message.success('删除成功');
      Promise.all([loadRoleList(), loadRecycleList()]);
    },
  });
};

const handleBatchDelete = () => {
  if (selectedRowKeys.value.length === 0) {
    message.warning('请选择要删除的角色');
    return;
  }

  const selectedRoles = roleData.value.filter((role) => selectedRowKeys.value.includes(role.id));
  const roleNames = selectedRoles.map((role) => role.name).join('、');

  Modal.confirm({
    title: '确认批量删除',
    content: `确定要删除选中的 ${selectedRowKeys.value.length} 个角色吗？\n\n角色列表：${roleNames}`,
    okText: '确认删除',
    cancelText: '取消',
    okType: 'danger',
    onOk: async () => {
      await roleApiService.batchDeleteRoles(selectedRowKeys.value);
      message.success(`成功删除 ${selectedRowKeys.value.length} 个角色`);
      selectedRowKeys.value = [];
      Promise.all([loadRoleList(), loadRecycleList()]);
    },
  });
};

const handleRecovery = (record: RoleType) => {
  Modal.confirm({
    title: '确认恢复',
    content: `确定要恢复角色 "${record.name}" 吗？`,
    onOk: async () => {
      await roleApiService.recoveryRoles([record.id]);
      message.success('恢复成功');
      Promise.all([loadRoleList(), loadRecycleList()]);
    },
  });
};

const handleRealDelete = (record: RoleType) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: `彻底删除后不可恢复，确定删除角色 "${record.name}" 吗？`,
    okType: 'danger',
    onOk: async () => {
      await roleApiService.realDeleteRoles([record.id]);
      message.success('彻底删除成功');
      Promise.all([loadRoleList(), loadRecycleList()]);
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的角色');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 个角色吗？`,
    onOk: async () => {
      await roleApiService.recoveryRoles(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量恢复成功');
      Promise.all([loadRoleList(), loadRecycleList()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的角色');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `彻底删除后不可恢复，确定删除选中的 ${selectedRecycleRowKeys.value.length} 个角色吗？`,
    okType: 'danger',
    onOk: async () => {
      await roleApiService.realDeleteRoles(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量彻底删除成功');
      Promise.all([loadRoleList(), loadRecycleList()]);
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<RoleType>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => roleApiService.getRoleList({
      code: searchForm.code,
      name: searchForm.name,
      page,
      pageSize,
      scope: searchForm.scope,
      status: searchForm.status,
    }),
    filename: `roles_${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '角色管理',
    });
  } finally {
    exporting.value = false;
  }
};

const handleImport = async () => {
  await openCrudImport<Record<string, any>>({
    afterDone: loadRoleList,
    columns: importColumns,
    moduleName: '角色',
    rules: [
      '角色导入只创建角色基础信息，菜单权限请导入后通过授权功能维护。',
      '角色编码和名称需满足后端唯一性校验，重复行会在结果中标记失败。',
    ],
    submit: async (payload) => {
      await roleApiService.createRole({
        code: String(payload.code || ''),
        name: String(payload.name || ''),
        scope: parseRoleScope(payload.scope, ROLE_SCOPE_DEFAULT),
        sort: parseNumber(payload.sort, 0),
        status: parseStatus(payload.status, 1),
        remark: String(payload.remark || ''),
      });
    },
  });
};

const handleFormSuccess = () => {
  modalVisible.value = false;
  Promise.all([loadRoleList(), loadRecycleList()]);
};

function roleStatusText(status?: number) {
  return status === 1 ? '启用' : '禁用';
}

onMounted(() => {
  Promise.all([loadRoleList(), loadRecycleList()]);
});
</script>
