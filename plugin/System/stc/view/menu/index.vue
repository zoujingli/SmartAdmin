<template>
  <Page title="菜单管理">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button v-if="canCreateMenus" type="primary" @click="handleAdd"><span class="i-lucide-plus" />新增菜单</Button>
          <Button v-if="canCreateMenus" @click="handleImport"><span class="i-lucide-upload" />导入</Button>
          <Button v-if="canExportMenus" :loading="exporting" @click="handleExport"><span class="i-lucide-download" />导出</Button>
          <Button @click="handleExpandAll"><span class="i-lucide-expand" />展开全部</Button>
          <Button @click="handleCollapseAll"><span class="i-lucide-collapse" />收起全部</Button>
        </template>
        <template v-else>
          <Button v-if="canRecoveryMenus" :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRecovery">批量恢复</Button>
          <Button v-if="canRealDeleteMenus" danger :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRealDelete">批量彻底删除</Button>
        </template>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="data" tab="数据列表">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="菜单名称"><Input v-model:value="searchForm.name" placeholder="请输入菜单名称" allow-clear /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="菜单类型">
                  <Select v-model:value="searchForm.type" class="w-full" placeholder="请选择" allow-clear>
                    <SelectOption :value="1">目录</SelectOption>
                    <SelectOption :value="2">菜单</SelectOption>
                    <SelectOption :value="3">按钮</SelectOption>
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
              empty-text="当前显示全部菜单树节点，可按名称和类型快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card>
            <CrudTableHeader
              title="菜单列表"
              description="维护目录、菜单与按钮的层级结构，支持新增子菜单、查看详情与批量展开收起。"
              :count-text="`共 ${visibleMenuCount} 个可见节点`"
            />
            <Table
              :columns="columns"
              :data-source="menuData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无菜单记录')"
              :pagination="false"
              :expanded-row-keys="expandedRowKeys"
              :expand-row-by-click="false"
              :default-expand-all-rows="false"
              :expandable="{ showExpandColumn: false }"
              :scroll="tableScroll"
              row-key="id"
              :tree-props="{ children: 'children' }"
              @expand="handleExpand"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'expand'">
                  <div class="flex items-center">
                    <Button
                      v-if="record.children?.length"
                      type="text"
                      size="small"
                      class="menu-expand-trigger"
                      @click.stop="toggleRowExpand(record as MenuType)"
                    >
                      <i
                        :class="
                          expandedRowKeys.includes(record.id)
                            ? 'i-lucide-chevron-down'
                            : 'i-lucide-chevron-right'
                        "
                      />
                    </Button>
                  </div>
                </template>
                <template v-else-if="column.key === 'name'">
                  <div
                    class="flex min-w-0 items-center gap-2"
                    :style="{ paddingLeft: `${Math.max((record.level || 0) * 18, 0)}px` }"
                  >
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg" :style="menuIconStyle">
                      <IconifyIcon v-if="record.icon" :icon="record.icon" class="size-4 text-primary" />
                      <i v-else class="i-lucide-file-text size-4" />
                    </span>
                    <Tooltip :title="record.name">
                      <span class="truncate font-medium" :style="menuNameStyle">
                        {{ record.name }}
                      </span>
                    </Tooltip>
                  </div>
                </template>
                <template v-else-if="column.key === 'permission'">
                  <Tooltip :title="record.permission || '-'">
                    <span class="block truncate text-xs" :style="secondaryTextStyle">
                      {{ record.permission || '-' }}
                    </span>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'path'">
                  <Tooltip :title="record.path || '-'">
                    <span class="block truncate text-xs" :style="secondaryTextStyle">
                      {{ record.path || '-' }}
                    </span>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'type'">
                  <CrudToneTag :color="getTypeColor(record.type)" :text="getTypeText(record.type)" />
                </template>
                <template v-else-if="column.key === 'status'">
                  <CrudStatusTag :value="record.status === 1" />
                </template>
                <template v-else-if="column.key === 'createdAt'">
                  <span :style="dateTextStyle">{{ record.createdAt || '-' }}</span>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="menuActions(record as MenuType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryMenus || canRealDeleteMenus" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除菜单"
              description="回收站中的菜单可恢复到主列表；彻底删除后将不可恢复，请谨慎操作。"
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
                <template v-if="column.key === 'name'">
                  <div class="flex min-w-0 items-center gap-2">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg" :style="menuIconStyle">
                      <IconifyIcon v-if="record.icon" :icon="record.icon" class="size-4 text-primary" />
                      <i v-else class="i-lucide-file-text size-4" />
                    </span>
                    <Tooltip :title="record.name">
                      <span class="truncate font-medium" :style="menuNameStyle">
                        {{ record.name }}
                      </span>
                    </Tooltip>
                  </div>
                </template>
                <template v-else-if="column.key === 'permission'">
                  <Tooltip :title="record.permission || '-'">
                    <span class="block truncate text-xs" :style="secondaryTextStyle">
                      {{ record.permission || '-' }}
                    </span>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'path'">
                  <Tooltip :title="record.path || '-'">
                    <span class="block truncate text-xs" :style="secondaryTextStyle">
                      {{ record.path || '-' }}
                    </span>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'type'">
                  <CrudToneTag :color="getTypeColor(record.type)" :text="getTypeText(record.type)" />
                </template>
                <template v-else-if="column.key === 'status'">
                  <CrudStatusTag :value="record.status === 1" />
                </template>
                <template v-else-if="column.key === 'deleted_at'">
                  <span :style="dateTextStyle">{{ record.deleted_at || '-' }}</span>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="menuRecycleActions(record as MenuType)" />
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
      :menu-tree-options="menuTreeOptions"
      @success="handleFormSuccess"
    />

    <Modal
      v-model:open="detailOpen"
      title="菜单详情"
      :width="popupWidth.sm"
      :footer="null"
      destroy-on-close
    >
      <template v-if="currentMenu">
        <CrudDetailHero
          :icon="menuDetailIcon"
          :lines="[
            `ID：${currentMenu.id}`,
            `上级 ID：${currentMenu.parentId || 0}`,
            `排序：${currentMenu.sort ?? 0}`,
          ]"
          :tags="[
            { color: getTypeColor(currentMenu.type), label: getTypeText(currentMenu.type) },
            { color: currentMenu.status === 1 ? 'success' : 'error', label: menuStatusText(currentMenu.status) },
          ]"
          :title="currentMenu.name"
          :tone="menuDetailTone"
        />

        <Descriptions bordered :column="2" size="middle">
          <DescriptionsItem label="菜单名称">{{ currentMenu.name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="菜单类型">{{ getTypeText(currentMenu.type) }}</DescriptionsItem>
          <DescriptionsItem label="菜单路由">{{ currentMenu.path || '-' }}</DescriptionsItem>
          <DescriptionsItem label="默认跳转">{{ currentMenu.redirect || '-' }}</DescriptionsItem>
          <DescriptionsItem label="组件路径">{{ currentMenu.component || '-' }}</DescriptionsItem>
          <DescriptionsItem label="权限标识">{{ currentMenu.permission || '-' }}</DescriptionsItem>
          <DescriptionsItem label="图标">{{ currentMenu.icon || '-' }}</DescriptionsItem>
          <DescriptionsItem label="状态">{{ menuStatusText(currentMenu.status) }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentMenu.createdAt || '-' }}</DescriptionsItem>
          <DescriptionsItem label="更新时间">{{ currentMenu.updatedAt || '-' }}</DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentMenu.remark || '-' }}</DescriptionsItem>
        </Descriptions>
      </template>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import {
  CrudDetailHero,
  CrudFilterSummary,
  buildCrudTableLocale,
  CrudStatusTag,
  CrudStatCards,
  CrudToneTag,
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import { IconifyIcon } from '@vben/icons';
import {
  Button,
  Card,
  Col,
  Descriptions,
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
  theme,
} from 'ant-design-vue';

import { menuApiService } from '#/api';
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

import type { MenuSearchForm, MenuType } from './types';
import FormModal from './modules/form.vue';

const searchForm = reactive<MenuSearchForm>({
  name: '',
  type: undefined,
});
const modalVisible = ref(false);
const formData = ref<MenuType>();
const activeTab = ref('data');
const detailOpen = ref(false);
const currentMenu = ref<MenuType>();
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const rawTree = ref<MenuType[]>([]);
const menuData = ref<MenuType[]>([]);
const expandedRowKeys = ref<number[]>([]);
const recycleData = ref<MenuType[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const menuTreeOptions = ref<any[]>([{ id: 0, name: '根目录', children: [] }]);
const { hasAccessByCodes } = useAccess();
const { token } = theme.useToken();
const canCreateMenus = computed(() => hasAccessByCodes(['system.menu.create']));
const canUpdateMenus = computed(() => hasAccessByCodes(['system.menu.update']));
const canDeleteMenus = computed(() => hasAccessByCodes(['system.menu.delete']));
const canExportMenus = computed(() => hasAccessByCodes(['system.menu.export']));
const canRecoveryMenus = computed(() => hasAccessByCodes(['system.menu.recovery']));
const canRealDeleteMenus = computed(() => hasAccessByCodes(['system.menu.real-delete']));

const stats = ref({
  totalMenus: 0,
  activeMenus: 0,
  inactiveMenus: 0,
  todayMenus: 0,
});

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([menuActions({} as MenuType)], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([menuRecycleActions({} as MenuType)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: '', key: 'expand', width: 64 },
  { title: '菜单名称', key: 'name', width: 260 },
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '菜单权限', dataIndex: 'permission', key: 'permission', width: 220 },
  { title: '菜单路由', dataIndex: 'path', key: 'path', width: 220 },
  { title: '菜单类型', dataIndex: 'type', key: 'type', width: 110 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '创建时间', dataIndex: 'createdAt', key: 'createdAt', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '菜单名称', key: 'name', width: 240 },
  { title: '菜单权限', dataIndex: 'permission', key: 'permission', width: 220 },
  { title: '菜单路由', dataIndex: 'path', key: 'path', width: 220 },
  { title: '菜单类型', dataIndex: 'type', key: 'type', width: 110 },
  { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1540 }));
const recycleTableScroll = computed(() => buildTableScrollX(recycleColumns.value, { minWidth: 1420, selectionWidth: 60 }));

function menuActions(record: MenuType) {
  return [
    { label: '编辑', visible: canUpdateMenus.value, onClick: () => handleEdit(record) },
    { label: '查看', onClick: () => handleView(record) },
    { label: '新增子菜单', visible: canCreateMenus.value, onClick: () => handleAddChild(record) },
    { label: '删除', visible: canDeleteMenus.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function menuRecycleActions(record: MenuType) {
  return [
    { label: '恢复', visible: canRecoveryMenus.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteMenus.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'name', title: '菜单名称', width: 180 },
  { key: 'permission', title: '权限标识', width: 220 },
  { key: 'path', title: '路由', width: 220 },
  { key: 'type', title: '类型', width: 100, formatter: (record: MenuType) => getTypeText(record.type) },
  { key: 'sort', title: '排序', width: 80 },
  { key: 'status', title: '状态', width: 90, formatter: (record: MenuType) => statusText(record.status) },
  { key: 'createdAt', title: '创建时间', width: 180 },
];

const importColumns = [
  { key: 'name', title: '菜单名称', required: true, example: '导入菜单', rule: '菜单、目录或按钮的显示名称。' },
  { key: 'permission', title: '权限标识', example: 'system.import.demo', rule: '按钮或接口权限码；目录可留空。' },
  { key: 'path', title: '路由', required: true, example: '/system/import-demo', rule: '菜单路由或外链地址，导入默认挂到根目录。' },
  { key: 'component', title: '组件路径', example: '@plugin/System/views/user/index.vue', rule: '菜单组件路径；目录、按钮可留空。' },
  { key: 'redirect', title: '默认跳转', example: '', rule: '目录跳转地址，可选。' },
  { key: 'icon', title: '图标', example: 'lucide:file-text', rule: '支持当前图标体系名称，可选。' },
  { key: 'type', title: '类型', example: '菜单', parser: (value: any) => normalizeType(value), rule: '支持 目录/菜单/按钮/外链/内嵌页 或 1/2/3/4/5。' },
  { key: 'sort', title: '排序', example: 0, parser: (value: any) => parseNumber(value, 0), rule: '数字越小排序越靠前，留空默认 0。' },
  { key: 'status', title: '状态', example: '启用', parser: (value: any) => parseStatus(value, 1), rule: '支持 启用/禁用 或 1/0，留空默认启用。' },
  { key: 'remark', title: '备注', example: '导入样例', rule: '可选，最多填写业务备注。' },
];

const menuIconStyle = computed(() => ({
  backgroundColor: token.value.colorFillSecondary,
  border: `1px solid ${token.value.colorBorderSecondary}`,
  color: token.value.colorTextSecondary,
}));

const menuDetailIcon = computed(() => currentMenu.value?.icon || 'lucide:file-text');

const menuDetailTone = computed<'info' | 'primary' | 'success' | 'warning'>(() => {
  const type = currentMenu.value?.type;
  if (type === 3) return 'warning';
  if (type === 2) return 'info';
  return 'primary';
});

const menuNameStyle = computed(() => ({
  color: token.value.colorText,
}));

const secondaryTextStyle = computed(() => ({
  color: token.value.colorTextSecondary,
}));

const dateTextStyle = computed(() => ({
  color: token.value.colorTextTertiary ?? token.value.colorTextSecondary,
}));

const flattenNodes = (nodes: MenuType[]): MenuType[] => {
  return nodes.flatMap((node) => [node, ...(node.children ? flattenNodes(node.children) : [])]);
};

const visibleMenuCount = computed(() => flattenNodes(menuData.value).length);
const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];

  if (searchForm.name?.trim()) {
    items.push({ label: '菜单名称', value: searchForm.name.trim() });
  }

  if (searchForm.type !== undefined) {
    items.push({ label: '菜单类型', value: getTypeText(Number(searchForm.type)) });
  }

  return items;
});

const menuMetrics = computed(() => {
  const allNodes = flattenNodes(rawTree.value);

  return {
    buttonMenus: allNodes.filter((item) => item.type === 3).length,
    rootMenus: rawTree.value.length,
  };
});

const summaryCards = computed(() => [
  {
    desc: '当前菜单树中的全部目录、菜单与按钮节点数量。',
    icon: 'i-lucide-layout-panel-top',
    label: '总节点数',
    tone: 'primary' as const,
    value: String(stats.value.totalMenus),
  },
  {
    desc: '顶级菜单数量，用于反映后台主导航的层级入口规模。',
    icon: 'i-lucide-folder-tree',
    label: '根节点',
    tone: 'info' as const,
    value: String(menuMetrics.value.rootMenus),
  },
  {
    desc: '当前启用状态的目录与菜单节点数量。',
    icon: 'i-lucide-shield-check',
    label: '启用菜单',
    tone: 'success' as const,
    value: String(stats.value.activeMenus),
  },
  {
    desc: '权限按钮节点数量，可用于评估页面操作粒度。',
    icon: 'i-lucide-mouse-pointer-click',
    label: '按钮节点',
    tone: 'warning' as const,
    value: String(menuMetrics.value.buttonMenus),
  },
]);

const getTypeColor = (type: number) => {
  const colors = {
    1: 'blue',
    2: 'green',
    3: 'orange',
    4: 'purple',
    5: 'cyan',
  } as Record<number, string>;
  return colors[type] || 'default';
};

const getTypeText = (type: number) => {
  const text = {
    1: '目录',
    2: '菜单',
    3: '按钮',
    4: '外链',
    5: '内嵌页',
  } as Record<number, string>;
  return text[type] || '-';
};

const menuStatusText = (status: number) => (status === 1 ? '启用' : '禁用');

const normalizeType = (value: any) => {
  const normalized = String(value ?? '').trim().toUpperCase();
  const typeMap: Record<string, number> = {
    '1': 1,
    '2': 2,
    '3': 3,
    '4': 4,
    '5': 5,
    内嵌页: 5,
    外链: 4,
    按钮: 3,
    菜单: 2,
    目录: 1,
    B: 3,
    BUTTON: 3,
    D: 1,
    DIRECTORY: 1,
    I: 5,
    IFRAME: 5,
    L: 4,
    LINK: 4,
    M: 2,
    MENU: 2,
    PATH: 1,
  };

  return typeMap[normalized] || 1;
};

const processTreeData = (nodes: any[], level = 0): MenuType[] => {
  return (nodes || []).map((item) => ({
    ...item,
    parentId: item.pid || 0,
    path: item.route || item.path || '',
    permission: item.code || item.permission || '',
    type: normalizeType(item.type),
    createdAt: item.created_at,
    updatedAt: item.updated_at,
    level,
    hasChildren: Array.isArray(item.children) && item.children.length > 0,
    children: processTreeData(item.children || [], level + 1),
  }));
};

const filterTree = (nodes: MenuType[], keyword: string, type?: number): MenuType[] => {
  const key = keyword.trim().toLowerCase();
  return nodes
    .map((node) => {
      const children = filterTree(node.children || [], keyword, type);
      const matchName = !key || (node.name || '').toLowerCase().includes(key);
      const matchType = type === undefined || Number(node.type) === Number(type);
      if ((matchName && matchType) || children.length > 0) {
        return { ...node, children };
      }
      return null;
    })
    .filter(Boolean) as MenuType[];
};

const collectExpandedRowKeys = (nodes: MenuType[]): number[] => {
  const keys: number[] = [];

  const visit = (items: MenuType[]) => {
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
  const filtered = filterTree(rawTree.value, searchForm.name || '', searchForm.type);
  menuData.value = filtered;

  const hasFilters = Boolean(searchForm.name?.trim()) || searchForm.type !== undefined;
  expandedRowKeys.value = hasFilters ? collectExpandedRowKeys(filtered) : [];
};

const loadMenuList = async () => {
  if (loading.value) return;
  try {
    loading.value = true;

    const listResponse = await menuApiService.getMenuList({ page: 1, pageSize: 1000 });
    const statistics = listResponse?.extra?.statistics;
    stats.value.totalMenus = statistics?.total || 0;
    stats.value.activeMenus = statistics?.active_count || 0;
    stats.value.inactiveMenus = statistics?.inactive_count || 0;
    stats.value.todayMenus = statistics?.today || 0;

    const treeData = await menuApiService.getMenuTree();
    rawTree.value = processTreeData(treeData as any[]);
    applyTreeFilters();
    menuTreeOptions.value = [{ id: 0, name: '根目录', children: treeData || [] }];
  } catch (error) {
    console.error('加载菜单列表失败:', error);
    message.error('获取菜单列表失败');
  } finally {
    loading.value = false;
  }
};

const recyclePagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const recycleRowSelection = computed(() => {
  if (!canRecoveryMenus.value && !canRealDeleteMenus.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const loadRecycleList = async () => {
  if (!canRecoveryMenus.value && !canRealDeleteMenus.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await menuApiService.getRecycleList({
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      name: searchForm.name,
      type: searchForm.type,
    });

    recycleData.value = (response?.items || []).map((item: any) => ({
      ...item,
      parentId: item.pid || 0,
      path: item.route || item.path || '',
      permission: item.code || item.permission || '',
      type: normalizeType(item.type),
      createdAt: item.created_at,
      updatedAt: item.updated_at,
    })) as MenuType[];
    recyclePagination.total = response?.pageInfo?.total || 0;
  } catch (error) {
    console.error('加载菜单回收站失败:', error);
    message.error('获取菜单回收站失败');
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
  searchForm.name = '';
  searchForm.type = undefined;
  menuData.value = [...rawTree.value];
  expandedRowKeys.value = [];
  recyclePagination.current = 1;
  loadRecycleList();
};

const handleExpandAll = () => {
  expandedRowKeys.value = collectExpandedRowKeys(menuData.value);
};

const handleCollapseAll = () => {
  expandedRowKeys.value = [];
};

const handleExpand = (expanded: boolean, record: MenuType) => {
  const current = new Set(expandedRowKeys.value);
  if (expanded) {
    current.add(record.id);
  } else {
    current.delete(record.id);
  }
  expandedRowKeys.value = [...current];
};

const toggleRowExpand = (record: MenuType) => {
  handleExpand(!expandedRowKeys.value.includes(record.id), record);
};

const handleAdd = () => {
  formData.value = undefined;
  modalVisible.value = true;
};

const handleEdit = (record: MenuType) => {
  formData.value = record;
  modalVisible.value = true;
};

const handleView = (record: MenuType) => {
  currentMenu.value = record;
  detailOpen.value = true;
};

const handleAddChild = (record: MenuType) => {
  formData.value = {
    id: 0,
    parentId: record.id,
    name: '',
    path: '',
    redirect: '',
    component: '',
    icon: '',
    type: 1,
    status: 1,
    sort: 0,
    permission: '',
    remark: '',
  } as MenuType;
  modalVisible.value = true;
};

const handleDelete = async (record: MenuType) => {
  Modal.confirm({
    title: '确认删除',
    content: `确定要删除菜单 "${record.name}" 吗？`,
    okText: '确认',
    cancelText: '取消',
    onOk: async () => {
      await menuApiService.deleteMenu(record.id);
      message.success('删除成功');
      Promise.all([loadMenuList(), loadRecycleList()]);
    },
  });
};

const handleRecycleTableChange = (pag: any) => {
  recyclePagination.current = pag.current;
  recyclePagination.pageSize = pag.pageSize;
  loadRecycleList();
};

const handleRecovery = (record: MenuType) => {
  Modal.confirm({
    title: '确认恢复',
    content: `确定要恢复菜单 "${record.name}" 吗？`,
    onOk: async () => {
      await menuApiService.recoveryMenus([record.id]);
      message.success('恢复成功');
      Promise.all([loadMenuList(), loadRecycleList()]);
    },
  });
};

const handleRealDelete = (record: MenuType) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: `确定要彻底删除菜单 "${record.name}" 吗？此操作不可恢复。`,
    okType: 'danger',
    onOk: async () => {
      await menuApiService.realDeleteMenus([record.id]);
      message.success('彻底删除成功');
      loadRecycleList();
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的菜单');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 个菜单吗？`,
    onOk: async () => {
      await menuApiService.recoveryMenus(selectedRecycleRowKeys.value);
      message.success(`成功恢复 ${selectedRecycleRowKeys.value.length} 个菜单`);
      selectedRecycleRowKeys.value = [];
      Promise.all([loadMenuList(), loadRecycleList()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的菜单');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `确定要彻底删除选中的 ${selectedRecycleRowKeys.value.length} 个菜单吗？此操作不可恢复。`,
    okType: 'danger',
    onOk: async () => {
      await menuApiService.realDeleteMenus(selectedRecycleRowKeys.value);
      message.success(`成功彻底删除 ${selectedRecycleRowKeys.value.length} 个菜单`);
      selectedRecycleRowKeys.value = [];
      loadRecycleList();
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<MenuType>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => menuApiService.getMenuList({
      name: searchForm.name,
      page,
      pageSize,
      type: searchForm.type,
    }) as any,
    filename: `menus_${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '菜单管理',
    transformItems: (items) => flattenTreeRows(items),
    });
  } finally {
    exporting.value = false;
  }
};

const handleImport = async () => {
  await openCrudImport<Record<string, any>>({
    afterDone: loadMenuList,
    columns: importColumns,
    moduleName: '菜单',
    rules: [
      '菜单导入默认挂到根目录，如需调整层级请导入后编辑父级。',
      '类型字段支持中文标签，推荐使用 目录、菜单、按钮、外链、内嵌页。',
    ],
    submit: async (payload) => {
      await menuApiService.createMenu({
        code: String(payload.permission || ''),
        component: String(payload.component || ''),
        icon: String(payload.icon || ''),
        level: '',
        name: String(payload.name || ''),
        pid: 0,
        redirect: String(payload.redirect || ''),
        remark: String(payload.remark || ''),
        route: String(payload.path || ''),
        sort: parseNumber(payload.sort, 0),
        status: parseStatus(payload.status, 1),
        type: normalizeType(payload.type),
      } as any);
    },
  });
};

const handleFormSuccess = () => {
  modalVisible.value = false;
  Promise.all([loadMenuList(), loadRecycleList()]);
};

onMounted(() => {
  Promise.all([loadMenuList(), loadRecycleList()]);
});
</script>

<style scoped>
.menu-toolbar-actions {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 1200px) {
  .menu-toolbar-actions {
    justify-content: flex-start;
  }
}

</style>
