<template>
  <Page title="租户管理">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button
            v-if="canDeleteTenants"
            danger
            :disabled="selectedRowKeys.length === 0"
            @click="handleBatchDelete"
          >
            <span class="i-lucide-trash-2" />
            批量删除
          </Button>
          <Button v-if="canCreateTenants" type="primary" @click="handleAdd"><span class="i-lucide-plus" />新增租户</Button>
          <Button v-if="canCreateTenants" @click="handleImport"><span class="i-lucide-upload" />导入</Button>
          <Button v-if="canExportTenants" :loading="exporting" @click="handleExport"><span class="i-lucide-download" />导出</Button>
        </template>
        <template v-else>
          <Button v-if="canRecoveryTenants" :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRecovery">批量恢复</Button>
          <Button v-if="canRealDeleteTenants" danger :disabled="selectedRecycleRowKeys.length === 0" @click="handleBatchRealDelete">批量彻底删除</Button>
        </template>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="data" tab="数据列表">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容"><Input v-model:value="searchForm.keyword" placeholder="请输入租户名称/编码/联系人" allow-clear /></SearchField>
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
              empty-text="当前显示全部租户记录，可按关键字和状态快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card>
            <CrudTableHeader
              title="租户台账"
              description="维护租户基础信息、联系人、套餐、到期时间和启停状态。"
              :count-text="`${pagination.total} 条记录`"
            />
            <Table
              :columns="columns"
              :data-source="tenantData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无租户记录')"
              :pagination="pagination"
              :row-selection="rowSelection"
              :scroll="tableScrollX"
              row-key="id"
              @change="handleTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'status'">
                  <Switch
                    :checked="record.status === 1"
                    :disabled="!canUpdateTenants"
                    @change="(checked) => handleStatusChange(record as TenantType, Boolean(checked))"
                  />
                </template>
                <template v-else-if="column.key === 'expired_at'">
                  {{ record.expired_at || '-' }}
                </template>
                <template v-else-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft">
                    <div class="truncate">{{ record.name }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="tenantActions(record as TenantType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryTenants || canRealDeleteTenants" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除租户"
              description="回收站中的租户可恢复到主列表；彻底删除后将不可恢复。"
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
                  <Switch :checked="record.status === 1" disabled />
                </template>
                <template v-else-if="column.key === 'expired_at'">
                  {{ record.expired_at || '-' }}
                </template>
                <template v-else-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft">
                    <div class="truncate">{{ record.name }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="tenantRecycleActions(record as TenantType)" />
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
      title="租户详情"
      width="min(860px, calc(100vw - 32px))"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentTenant">
        <CrudDetailHero
          icon="i-lucide-building-2"
          :lines="[
            `联系人：${currentTenant.contact_name || '-'} / ${currentTenant.contact_phone || '-'}`,
            `联系邮箱：${currentTenant.contact_email || '-'}`,
            `到期时间：${currentTenant.expired_at || '未设置'}`,
          ]"
          :tags="[
            { label: currentTenant.code },
            { color: 'processing', label: currentTenant.package_code || '未设置套餐' },
            { color: currentTenant.status === 1 ? 'success' : 'default', label: tenantStatusText(currentTenant.status) },
          ]"
          :title="currentTenant.name"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="租户 ID">{{ currentTenant.id }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentTenant.created_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="租户编码">{{ currentTenant.code }}</DescriptionsItem>
          <DescriptionsItem label="状态">{{ tenantStatusText(currentTenant.status) }}</DescriptionsItem>
          <DescriptionsItem label="租户名称">{{ currentTenant.name }}</DescriptionsItem>
          <DescriptionsItem label="套餐编码">{{ currentTenant.package_code || '-' }}</DescriptionsItem>
          <DescriptionsItem label="联系人">{{ currentTenant.contact_name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="联系电话">{{ currentTenant.contact_phone || '-' }}</DescriptionsItem>
          <DescriptionsItem label="联系邮箱" :span="2">{{ currentTenant.contact_email || '-' }}</DescriptionsItem>
          <DescriptionsItem label="到期时间" :span="2">{{ currentTenant.expired_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentTenant.remark || '-' }}</DescriptionsItem>
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

import { tenantApiService } from '#/api/system/tenant';
import {
  exportCrudXlsx,
  openCrudImport,
  parseStatus,
  statusText,
} from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

import FormModal from './modules/form.vue';
import type { TenantType } from './types';

const searchForm = reactive({
  keyword: '',
  status: undefined as number | undefined,
});

const modalVisible = ref(false);
const formData = ref<TenantType>();
const activeTab = ref('data');
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const tenantData = ref<TenantType[]>([]);
const selectedRowKeys = ref<number[]>([]);
const recycleData = ref<TenantType[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const currentTenant = ref<TenantType | null>(null);
const detailOpen = ref(false);
const statistics = ref({
  total: 0,
  active: 0,
  inactive: 0,
  today_created: 0,
});
const { hasAccessByCodes } = useAccess();
const canCreateTenants = computed(() => hasAccessByCodes(['system.tenant.create']));
const canUpdateTenants = computed(() => hasAccessByCodes(['system.tenant.update']));
const canDeleteTenants = computed(() => hasAccessByCodes(['system.tenant.delete']));
const canExportTenants = computed(() => hasAccessByCodes(['system.tenant.export']));
const canRecoveryTenants = computed(() => hasAccessByCodes(['system.tenant.recovery']));
const canRealDeleteTenants = computed(() => hasAccessByCodes(['system.tenant.real-delete']));
const summaryCards = computed(() => [
  {
    desc: '当前租户台账中的有效租户数量。',
    icon: 'i-lucide-building-2',
    label: '租户总数',
    value: String(statistics.value.total),
  },
  {
    desc: '当前处于启用状态的租户数量。',
    icon: 'i-lucide-badge-check',
    label: '启用租户',
    value: String(statistics.value.active),
  },
  {
    desc: '当前处于禁用状态的租户数量。',
    icon: 'i-lucide-ban',
    label: '禁用租户',
    value: String(statistics.value.inactive),
  },
  {
    desc: '今天新增写入的租户记录数量。',
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
    items.push({ label: '状态', value: tenantStatusText(searchForm.status) });
  }

  return items;
});

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([tenantActions({} as TenantType)], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([tenantRecycleActions({} as TenantType)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '租户编码', dataIndex: 'code', key: 'code', width: 140 },
  { title: '租户名称', dataIndex: 'name', key: 'name', width: 180 },
  { title: '联系人', dataIndex: 'contact_name', key: 'contact_name', width: 120 },
  { title: '联系电话', dataIndex: 'contact_phone', key: 'contact_phone', width: 140 },
  { title: '套餐编码', dataIndex: 'package_code', key: 'package_code', width: 120 },
  { title: '到期时间', dataIndex: 'expired_at', key: 'expired_at', width: 180 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 100 },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '租户编码', dataIndex: 'code', key: 'code', width: 140 },
  { title: '租户名称', dataIndex: 'name', key: 'name', width: 180 },
  { title: '联系人', dataIndex: 'contact_name', key: 'contact_name', width: 120 },
  { title: '联系电话', dataIndex: 'contact_phone', key: 'contact_phone', width: 140 },
  { title: '套餐编码', dataIndex: 'package_code', key: 'package_code', width: 120 },
  { title: '到期时间', dataIndex: 'expired_at', key: 'expired_at', width: 180 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 100 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const tableScrollX = computed(() => buildTableScrollX(columns.value, { selectionWidth: 60 }));
const recycleTableScrollX = computed(() => buildTableScrollX(recycleColumns.value, {
  selectionWidth: 60,
}));

function tenantActions(record: TenantType) {
  return [
    { label: '编辑', visible: canUpdateTenants.value, onClick: () => handleEdit(record) },
    { label: '查看', onClick: () => handleView(record) },
    { label: '删除', visible: canDeleteTenants.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function tenantRecycleActions(record: TenantType) {
  return [
    { label: '恢复', visible: canRecoveryTenants.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteTenants.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'code', title: '租户编码', width: 140 },
  { key: 'name', title: '租户名称', width: 180 },
  { key: 'contact_name', title: '联系人', width: 120 },
  { key: 'contact_phone', title: '联系电话', width: 140 },
  { key: 'contact_email', title: '联系邮箱', width: 180 },
  { key: 'package_code', title: '套餐编码', width: 120 },
  { key: 'expired_at', title: '到期时间', width: 160 },
  { key: 'status', title: '状态', width: 90, formatter: (record: TenantType) => statusText(record.status) },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const importColumns = [
  { key: 'code', title: '租户编码', required: true, example: 'tenant_demo', rule: '租户编码需唯一，建议使用英文、数字或下划线。' },
  { key: 'name', title: '租户名称', required: true, example: '演示租户', rule: '租户显示名称。' },
  { key: 'contact_name', title: '联系人', required: true, example: '李四', rule: '租户联系人姓名。' },
  { key: 'contact_phone', title: '联系电话', example: '13800000003', rule: '可选，填写联系人手机号。' },
  { key: 'contact_email', title: '联系邮箱', example: 'tenant@example.com', rule: '可选，填写联系人邮箱。' },
  { key: 'package_code', title: '套餐编码', example: 'basic', rule: '留空默认 basic；需与系统套餐编码保持一致。' },
  { key: 'expired_at', title: '到期时间', example: '2026-12-31 23:59:59', rule: '可选，建议使用 YYYY-MM-DD HH:mm:ss。' },
  { key: 'status', title: '状态', example: '启用', parser: (value: any) => parseStatus(value, 1), rule: '支持 启用/禁用 或 1/0，留空默认启用。' },
  { key: 'admin_username', title: '管理员用户名', required: true, example: 'tenant_admin_demo', rule: '新建租户必须同步创建管理员账号，用户名全局唯一。' },
  { key: 'admin_password', title: '管理员初始密码', required: true, example: 'Admin@123456', rule: '至少 6 位，导入后请提示租户管理员尽快修改。' },
  { key: 'admin_nickname', title: '管理员昵称', example: '租户管理员', rule: '可选，留空默认租户管理员。' },
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
  if (!canDeleteTenants.value) {
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
  if (!canRecoveryTenants.value && !canRealDeleteTenants.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const loadTenantList = async () => {
  if (loading.value) return;
  try {
    loading.value = true;
    const [response, statisticsResp] = await Promise.all([
      tenantApiService.getTenantList({
        page: pagination.current,
        pageSize: pagination.pageSize,
        keyword: searchForm.keyword,
        status: searchForm.status,
      }),
      tenantApiService.getStatistics({
        keyword: searchForm.keyword,
        status: searchForm.status,
      }),
    ]);

    tenantData.value = (response?.items || []) as TenantType[];
    pagination.total = response?.pageInfo?.total || response?.total || 0;
    statistics.value = statisticsResp || statistics.value;
  } catch (error) {
    console.error('加载租户列表失败:', error);
    message.error('获取租户列表失败');
  } finally {
    loading.value = false;
  }
};

const loadRecycleList = async () => {
  if (!canRecoveryTenants.value && !canRealDeleteTenants.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await tenantApiService.getRecycleList({
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      keyword: searchForm.keyword,
      status: searchForm.status,
    });

    recycleData.value = (response?.items || []) as TenantType[];
    recyclePagination.total = response?.pageInfo?.total || response?.total || 0;
  } catch (error) {
    console.error('加载租户回收站失败:', error);
    message.error('获取租户回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
};

const handleSearch = () => {
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadTenantList(), loadRecycleList()]);
};

const handleReset = () => {
  searchForm.keyword = '';
  searchForm.status = undefined;
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadTenantList(), loadRecycleList()]);
};

const handleTableChange = (pag: any) => {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  loadTenantList();
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

const handleEdit = (record: TenantType) => {
  formData.value = record;
  modalVisible.value = true;
};

const handleView = (record: TenantType) => {
  currentTenant.value = record;
  detailOpen.value = true;
};

const handleStatusChange = async (record: TenantType, checked: boolean) => {
  try {
    await tenantApiService.updateTenantStatus(record.id, checked ? 1 : 0);
    message.success('状态更新成功');
    loadTenantList();
  } catch (error) {
    console.error('更新租户状态失败:', error);
    message.error('状态更新失败');
  }
};

const handleDelete = (record: TenantType) => {
  Modal.confirm({
    title: '确认删除',
    content: `确定要删除租户 "${record.name}" 吗？`,
    onOk: async () => {
      await tenantApiService.deleteTenant(record.id);
      message.success('删除成功');
      Promise.all([loadTenantList(), loadRecycleList()]);
    },
  });
};

const handleBatchDelete = () => {
  if (selectedRowKeys.value.length === 0) {
    message.warning('请选择要删除的租户');
    return;
  }

  const selectedTenants = tenantData.value.filter((tenant) => selectedRowKeys.value.includes(tenant.id));
  const tenantNames = selectedTenants.map((tenant) => tenant.name).join('、');

  Modal.confirm({
    title: '确认批量删除',
    content: `确定要删除选中的 ${selectedRowKeys.value.length} 个租户吗？\n\n租户列表：${tenantNames}`,
    okType: 'danger',
    onOk: async () => {
      await tenantApiService.batchDeleteTenants(selectedRowKeys.value);
      message.success(`成功删除 ${selectedRowKeys.value.length} 个租户`);
      selectedRowKeys.value = [];
      Promise.all([loadTenantList(), loadRecycleList()]);
    },
  });
};

const handleRecovery = (record: TenantType) => {
  Modal.confirm({
    title: '确认恢复',
    content: `确定要恢复租户 "${record.name}" 吗？`,
    onOk: async () => {
      await tenantApiService.recoveryTenants([record.id]);
      message.success('恢复成功');
      Promise.all([loadTenantList(), loadRecycleList()]);
    },
  });
};

const handleRealDelete = (record: TenantType) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: `确定要彻底删除租户 "${record.name}" 吗？此操作不可恢复。`,
    okType: 'danger',
    onOk: async () => {
      await tenantApiService.realDeleteTenants([record.id]);
      message.success('彻底删除成功');
      loadRecycleList();
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的租户');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 个租户吗？`,
    onOk: async () => {
      await tenantApiService.recoveryTenants(selectedRecycleRowKeys.value);
      message.success(`成功恢复 ${selectedRecycleRowKeys.value.length} 个租户`);
      selectedRecycleRowKeys.value = [];
      Promise.all([loadTenantList(), loadRecycleList()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的租户');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `确定要彻底删除选中的 ${selectedRecycleRowKeys.value.length} 个租户吗？此操作不可恢复。`,
    okType: 'danger',
    onOk: async () => {
      await tenantApiService.realDeleteTenants(selectedRecycleRowKeys.value);
      message.success(`成功彻底删除 ${selectedRecycleRowKeys.value.length} 个租户`);
      selectedRecycleRowKeys.value = [];
      loadRecycleList();
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<TenantType>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => tenantApiService.getTenantList({
      keyword: searchForm.keyword,
      page,
      pageSize,
      status: searchForm.status,
    }),
    filename: `tenants-${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '租户管理',
    });
  } finally {
    exporting.value = false;
  }
};

const handleImport = async () => {
  await openCrudImport<Record<string, any>>({
    afterDone: loadTenantList,
    columns: importColumns,
    moduleName: '租户',
    rules: [
      '租户编码需满足后端唯一性校验，重复行会在结果中标记失败。',
      '套餐编码留空时默认使用 basic。',
      '导入会同步创建租户管理员，管理员用户名必须全局唯一。',
    ],
    submit: async (payload) => {
      await tenantApiService.createTenant({
        admin_nickname: String(payload.admin_nickname || '租户管理员'),
        admin_password: String(payload.admin_password || ''),
        admin_username: String(payload.admin_username || ''),
        code: String(payload.code || ''),
        contact_email: String(payload.contact_email || ''),
        contact_name: String(payload.contact_name || ''),
        contact_phone: String(payload.contact_phone || ''),
        expired_at: String(payload.expired_at || '') || undefined,
        name: String(payload.name || ''),
        package_code: String(payload.package_code || 'basic'),
        remark: String(payload.remark || ''),
        status: parseStatus(payload.status, 1),
      });
    },
  });
};

const handleFormSuccess = () => {
  modalVisible.value = false;
  Promise.all([loadTenantList(), loadRecycleList()]);
};

function tenantStatusText(status: number) {
  return status === 1 ? '启用' : '禁用';
}

onMounted(() => {
  Promise.all([loadTenantList(), loadRecycleList()]);
});
</script>
