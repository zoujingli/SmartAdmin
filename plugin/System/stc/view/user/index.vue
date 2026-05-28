<template>
  <Page title="用户管理">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button
            v-if="canDeleteUsers"
            danger
            :disabled="selectedRowKeys.length === 0"
            @click="handleBatchDelete"
          >
            <span class="i-lucide-trash-2" />
            批量删除
          </Button>
          <Button v-if="canCreateUsers" type="primary" @click="handleAdd">
            <span class="i-lucide-plus" />
            新增用户
          </Button>
          <Button v-if="canCreateUsers" @click="handleImport">
            <span class="i-lucide-upload" />
            导入
          </Button>
          <Button v-if="canExportUsers" :loading="exporting" @click="handleExport">
            <span class="i-lucide-download" />
            导出
          </Button>
        </template>
        <template v-else>
          <Button
            v-if="canRecoveryUsers"
            :disabled="selectedRecycleRowKeys.length === 0"
            @click="handleBatchRecovery"
          >
            批量恢复
          </Button>
          <Button
            v-if="canRealDeleteUsers"
            danger
            :disabled="selectedRecycleRowKeys.length === 0"
            @click="handleBatchRealDelete"
          >
            批量彻底删除
          </Button>
        </template>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="data" tab="数据列表">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="用户名称"><Input v-model:value="searchForm.username" placeholder="请输入用户名" allow-clear /></SearchField>
              </Col>
              <Col v-if="canAccessDeptTree" :xs="24" :sm="12" :xl="6">
                <SearchField label="所属部门">
                  <TreeSelect
                    v-model:value="searchForm.deptId"
                    :tree-data="deptTreeOptions"
                    :field-names="{ children: 'children', label: 'name', value: 'id' }"
                    allow-clear
                    class="w-full"
                    placeholder="请选择"
                    show-search
                    tree-default-expand-all
                    tree-node-filter-prop="name"
                  />
                </SearchField>
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
                  <Button type="primary" :loading="loading" @click="handleSearch">
                    <span class="i-lucide-search" />
                    搜索
                  </Button>
                  <Button :disabled="loading" @click="handleReset">
                    <span class="i-lucide-refresh-cw" />
                    重置
                  </Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary
              :items="activeFilterItems"
              empty-text="当前显示全部用户记录，可按用户名、部门和状态快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card>
            <CrudTableHeader
              title="用户列表"
              description="维护系统用户、部门归属、角色岗位和账号状态，支持重置密码与批量删除。"
              :count-text="`${pagination.total} 条记录`"
            />
            <Table
              :columns="columns"
              :data-source="userData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无用户记录')"
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
                <template v-else-if="column.key === 'username'">
                  <Tooltip :title="record.username" placement="topLeft">
                    <div class="truncate">{{ record.username }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'nickname'">
                  <Tooltip :title="record.nickname" placement="topLeft">
                    <div class="truncate">{{ record.nickname }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'roleNames'">
                  <CrudTagList :items="record.roleNames || []" color="blue" />
                </template>
                <template v-else-if="column.key === 'postNames'">
                  <CrudTagList :items="record.postNames || []" color="purple" />
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="userActions(record as UserType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryUsers || canRealDeleteUsers" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除用户"
              description="回收站中的用户可恢复到主列表；彻底删除后将不可恢复，请谨慎操作。"
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
                <template v-else-if="column.key === 'username'">
                  <Tooltip :title="record.username" placement="topLeft">
                    <div class="truncate">{{ record.username }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'nickname'">
                  <Tooltip :title="record.nickname" placement="topLeft">
                    <div class="truncate">{{ record.nickname }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'roleNames'">
                  <CrudTagList :items="record.roleNames || []" color="blue" />
                </template>
                <template v-else-if="column.key === 'postNames'">
                  <CrudTagList :items="record.postNames || []" color="purple" />
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="userRecycleActions(record as UserType)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>
      </Tabs>
    </Card>

    <FormModal ref="formModalRef" @success="handleFormSuccess" />

    <AppDrawer
      :open="detailOpen"
      :show-footer="false"
      title="用户详情"
      width-size="xl"
      @close="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentUser">
        <CrudDetailHero
          icon="i-lucide-user-round"
          :lines="[
            `邮箱：${currentUser.email || '-'}`,
            `手机：${currentUser.phone || '-'}`,
            `创建时间：${currentUser.created_at || currentUser.createdAt || '-'}`,
          ]"
          :tags="[
            { label: currentUser.username },
            { color: currentUser.status === 1 ? 'success' : 'default', label: userStatusText(currentUser.status) },
            { label: currentUser.deptName || '未分配部门' },
          ]"
          :title="currentUser.nickname || currentUser.username"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="用户 ID">{{ currentUser.id }}</DescriptionsItem>
          <DescriptionsItem label="所属部门">{{ currentUser.deptName || '-' }}</DescriptionsItem>
          <DescriptionsItem label="用户名">{{ currentUser.username || '-' }}</DescriptionsItem>
          <DescriptionsItem label="昵称">{{ currentUser.nickname || '-' }}</DescriptionsItem>
          <DescriptionsItem label="邮箱">{{ currentUser.email || '-' }}</DescriptionsItem>
          <DescriptionsItem label="手机">{{ currentUser.phone || '-' }}</DescriptionsItem>
          <DescriptionsItem label="状态">{{ userStatusText(currentUser.status) }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentUser.created_at || currentUser.createdAt || '-' }}</DescriptionsItem>
          <DescriptionsItem label="角色" :span="2">
            <CrudDetailTagList :items="currentUser.roleNames || []" />
          </DescriptionsItem>
          <DescriptionsItem label="岗位" :span="2">
            <CrudDetailTagList :items="currentUser.postNames || []" />
          </DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentUser.remark || '-' }}</DescriptionsItem>
        </CrudDetailDescriptions>
      </CrudDetailPanel>
    </AppDrawer>
  </Page>
</template>

<script setup lang="ts">
import { computed, h, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import {
  Button,
  Card,
  Col,
  DescriptionsItem,
  Input,
  InputPassword,
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
  TreeSelect,
} from 'ant-design-vue';

import { deptApiService, userApiService } from '#/api';
import {
  exportCrudXlsx,
  openCrudImport,
  parseStatus,
  statusText,
} from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import AppDrawer from '#/components/app-drawer.vue';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

import type { UserSearchForm, UserType } from './types';
import FormModal from './modules/form-simple.vue';

const searchForm = reactive<UserSearchForm>({
  username: '',
  deptId: undefined,
  status: undefined,
});
const route = useRoute();
const router = useRouter();
const activeTab = ref('data');
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const formModalRef = ref();
const userData = ref<UserType[]>([]);
const selectedRowKeys = ref<number[]>([]);
const recycleData = ref<UserType[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const currentUser = ref<UserType | null>(null);
const detailOpen = ref(false);
const deptTreeOptions = ref<any[]>([]);
const { hasAccessByCodes } = useAccess();
const canCreateUsers = computed(() => hasAccessByCodes(['system.user.create']));
const canUpdateUsers = computed(() => hasAccessByCodes(['system.user.update']));
const canDeleteUsers = computed(() => hasAccessByCodes(['system.user.delete']));
const canResetUserPassword = computed(() => hasAccessByCodes(['system.user.reset-password']));
const canExportUsers = computed(() => hasAccessByCodes(['system.user.export']));
const canRecoveryUsers = computed(() => hasAccessByCodes(['system.user.recovery']));
const canRealDeleteUsers = computed(() => hasAccessByCodes(['system.user.real-delete']));
const canAccessDeptTree = computed(() => hasAccessByCodes(['system.dept.index']));

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([userActions({} as UserType)], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([userRecycleActions({} as UserType)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '用户名', dataIndex: 'username', key: 'username' },
  { title: '昵称', dataIndex: 'nickname', key: 'nickname' },
  { title: '邮箱', dataIndex: 'email', key: 'email' },
  { title: '手机', dataIndex: 'phone', key: 'phone' },
  { title: '部门', dataIndex: 'deptName', key: 'deptName', width: 140 },
  { title: '角色', dataIndex: 'roleNames', key: 'roleNames' },
  { title: '岗位', dataIndex: 'postNames', key: 'postNames' },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '用户名', dataIndex: 'username', key: 'username' },
  { title: '昵称', dataIndex: 'nickname', key: 'nickname' },
  { title: '部门', dataIndex: 'deptName', key: 'deptName', width: 140 },
  { title: '角色', dataIndex: 'roleNames', key: 'roleNames' },
  { title: '岗位', dataIndex: 'postNames', key: 'postNames' },
  { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const tableScrollX = computed(() => buildTableScrollX(columns.value, { selectionWidth: 60 }));
const recycleTableScrollX = computed(() => buildTableScrollX(recycleColumns.value, {
  selectionWidth: 60,
}));

function userActions(record: UserType) {
  return [
    { label: '编辑', visible: canUpdateUsers.value, onClick: () => handleEdit(record) },
    { label: '查看', onClick: () => handleView(record) },
    { label: '重置密码', visible: canResetUserPassword.value, onClick: () => handleResetPassword(record) },
    { label: '删除', visible: canDeleteUsers.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function userRecycleActions(record: UserType) {
  return [
    { label: '恢复', visible: canRecoveryUsers.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteUsers.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'username', title: '用户名', width: 140 },
  { key: 'nickname', title: '昵称', width: 140 },
  { key: 'email', title: '邮箱', width: 180 },
  { key: 'phone', title: '手机', width: 140 },
  { key: 'deptName', title: '部门', width: 140 },
  { key: 'roleNames', title: '角色', width: 180, formatter: (record: UserType) => (record.roleNames || []).join(' / ') },
  { key: 'postNames', title: '岗位', width: 180, formatter: (record: UserType) => (record.postNames || []).join(' / ') },
  { key: 'status', title: '状态', width: 90, formatter: (record: UserType) => statusText(record.status) },
  { key: 'created_at', title: '创建时间', width: 180, formatter: (record: UserType) => record.created_at || record.createdAt || '' },
];

const importColumns = [
  { key: 'username', title: '用户名', required: true, example: 'import_user_001', rule: '账号唯一，建议使用字母、数字或下划线。' },
  { key: 'nickname', title: '昵称', required: true, example: '导入用户001', rule: '用于界面展示，未填写时无法创建用户。' },
  { key: 'email', title: '邮箱', required: true, example: 'import_user_001@example.com', rule: '请输入有效邮箱，邮箱需保持唯一。' },
  { key: 'phone', title: '手机', required: true, example: '13800000001', rule: '请输入有效手机号，手机号需保持唯一。' },
  { key: 'password', title: '密码', required: true, example: 'Admin@123456', rule: '导入时写入初始密码，请避免使用弱密码。' },
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
  totalUsers: 0,
  activeUsers: 0,
  inactiveUsers: 0,
  newUsers: 0,
});
const summaryCards = computed(() => [
  {
    desc: '当前用户列表中的有效用户数量。',
    icon: 'i-lucide-users',
    label: '总用户数',
    value: String(stats.value.totalUsers),
  },
  {
    desc: '当前处于启用状态的账号数量。',
    icon: 'i-lucide-badge-check',
    label: '启用用户',
    value: String(stats.value.activeUsers),
  },
  {
    desc: '当前处于禁用状态的账号数量。',
    icon: 'i-lucide-user-x',
    label: '禁用用户',
    value: String(stats.value.inactiveUsers),
  },
  {
    desc: '今天新增写入的用户记录数量。',
    icon: 'i-lucide-user-plus',
    label: '今日新增',
    value: String(stats.value.newUsers),
  },
]);
const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const username = (searchForm.username || '').trim();
  if (username !== '') {
    items.push({ label: '用户名', value: username });
  }
  if (searchForm.deptId) {
    const deptName =
      deptTreeOptions.value.find((item: any) => Number(item.id) === Number(searchForm.deptId))?.name ||
      String(searchForm.deptId);
    items.push({ label: '所属部门', value: deptName });
  }
  if (typeof searchForm.status === 'number') {
    items.push({ label: '状态', value: userStatusText(searchForm.status) });
  }
  return items;
});

const rowSelection = computed(() => {
  if (!canDeleteUsers.value) {
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
  if (!canRecoveryUsers.value && !canRealDeleteUsers.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const normalizeDeptId = (value: unknown): number | undefined => {
  const rawValue = Array.isArray(value) ? value[0] : value;
  const deptId = Number(rawValue);

  if (!Number.isFinite(deptId) || deptId <= 0) {
    return undefined;
  }

  return deptId;
};

const syncRouteDeptQuery = async () => {
  const currentDeptId = normalizeDeptId(route.query.deptId);
  const nextDeptId = searchForm.deptId;

  if (currentDeptId === nextDeptId) {
    return;
  }

  await router.replace({
    path: route.path,
    query: nextDeptId ? { deptId: String(nextDeptId) } : {},
  });
};

const loadDeptOptions = async () => {
  if (!canAccessDeptTree.value) {
    deptTreeOptions.value = [];
    return;
  }

  try {
    deptTreeOptions.value = await deptApiService.getDeptTree();
  } catch (error) {
    console.error('加载部门选项失败:', error);
    deptTreeOptions.value = [];
    message.error('获取部门选项失败');
  }
};

const loadUserList = async () => {
  if (loading.value) return;
  try {
    loading.value = true;
    const params = {
      page: pagination.current,
      pageSize: pagination.pageSize,
      ...searchForm,
    };

    const response = await userApiService.getUserList(params);
    if (response?.items) {
      userData.value = response.items as UserType[];
      pagination.total = response.pageInfo?.total || 0;
      const statistics = response.extra?.statistics;
      stats.value.totalUsers = statistics?.total || 0;
      stats.value.activeUsers = statistics?.active_count || 0;
      stats.value.inactiveUsers = statistics?.inactive_count || 0;
      stats.value.newUsers = statistics?.today || 0;
    } else {
      userData.value = [];
      pagination.total = 0;
    }
  } catch (error) {
    console.error('加载用户列表失败:', error);
    message.error('获取用户列表失败');
  } finally {
    loading.value = false;
  }
};

const loadRecycleList = async () => {
  if (!canRecoveryUsers.value && !canRealDeleteUsers.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    selectedRecycleRowKeys.value = [];
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const params = {
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      ...searchForm,
    };

    const response = await userApiService.getRecycleList(params);
    if (response?.items) {
      recycleData.value = response.items as UserType[];
      recyclePagination.total = response.pageInfo?.total || 0;
    } else {
      recycleData.value = [];
      recyclePagination.total = 0;
    }
  } catch (error) {
    console.error('加载用户回收站失败:', error);
    message.error('获取用户回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
};

const handleSearch = () => {
  pagination.current = 1;
  recyclePagination.current = 1;
  syncRouteDeptQuery().finally(() => {
    Promise.all([loadUserList(), loadRecycleList()]);
  });
};

const handleReset = () => {
  searchForm.username = '';
  searchForm.deptId = undefined;
  searchForm.status = undefined;
  pagination.current = 1;
  recyclePagination.current = 1;
  syncRouteDeptQuery().finally(() => {
    Promise.all([loadUserList(), loadRecycleList()]);
  });
};

const handleTableChange = (pag: any) => {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  loadUserList();
};

const handleRecycleTableChange = (pag: any) => {
  recyclePagination.current = pag.current;
  recyclePagination.pageSize = pag.pageSize;
  loadRecycleList();
};

const handleAdd = () => formModalRef.value?.open();
const handleEdit = (record: UserType) => formModalRef.value?.open(record);

const handleView = (record: UserType) => {
  currentUser.value = record;
  detailOpen.value = true;
};

const handleResetPassword = (record: UserType) => {
  let nextPassword = '';
  Modal.confirm({
    title: `重置密码：${record.username}`,
    content: h(InputPassword, {
      placeholder: '请输入新密码（至少6位）',
      onInput: (e: any) => {
        nextPassword = e?.target?.value ?? '';
      },
      onChange: (e: any) => {
        nextPassword = e?.target?.value ?? '';
      },
    }),
    okText: '确认',
    cancelText: '取消',
    onOk: async () => {
      const password = nextPassword.trim();
      if (password.length < 6) {
        message.error('密码长度不能小于 6 位');
        return Promise.reject(new Error('invalid password'));
      }
      await userApiService.resetUserPassword(record.id, password);
      message.success('密码重置成功');
    },
  });
};

const handleDelete = async (record: UserType) => {
  Modal.confirm({
    title: '确认删除',
    content: `确定要删除用户 "${record.username}" 吗？`,
    okText: '确认',
    cancelText: '取消',
    onOk: async () => {
      await userApiService.deleteUser(record.id);
      message.success('删除成功');
      Promise.all([loadUserList(), loadRecycleList()]);
    },
  });
};

const handleBatchDelete = () => {
  if (selectedRowKeys.value.length === 0) {
    message.warning('请选择要删除的用户');
    return;
  }

  const selectedUsers = userData.value.filter((user) => selectedRowKeys.value.includes(user.id));
  const userNames = selectedUsers.map((user) => user.username).join('、');

  Modal.confirm({
    title: '确认批量删除',
    content: `确定要删除选中的 ${selectedRowKeys.value.length} 个用户吗？\n\n用户列表：${userNames}`,
    okText: '确认删除',
    cancelText: '取消',
    okType: 'danger',
    onOk: async () => {
      await userApiService.batchDeleteUsers(selectedRowKeys.value);
      message.success(`成功删除 ${selectedRowKeys.value.length} 个用户`);
      selectedRowKeys.value = [];
      Promise.all([loadUserList(), loadRecycleList()]);
    },
  });
};

const handleRecovery = (record: UserType) => {
  Modal.confirm({
    title: '确认恢复',
    content: `确定要恢复用户 "${record.username}" 吗？`,
    onOk: async () => {
      await userApiService.recoveryUsers([record.id]);
      message.success('恢复成功');
      Promise.all([loadUserList(), loadRecycleList()]);
    },
  });
};

const handleRealDelete = (record: UserType) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: `彻底删除后不可恢复，确定删除用户 "${record.username}" 吗？`,
    okType: 'danger',
    onOk: async () => {
      await userApiService.realDeleteUsers([record.id]);
      message.success('彻底删除成功');
      Promise.all([loadUserList(), loadRecycleList()]);
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的用户');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 个用户吗？`,
    onOk: async () => {
      await userApiService.recoveryUsers(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量恢复成功');
      Promise.all([loadUserList(), loadRecycleList()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的用户');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `彻底删除后不可恢复，确定删除选中的 ${selectedRecycleRowKeys.value.length} 个用户吗？`,
    okType: 'danger',
    onOk: async () => {
      await userApiService.realDeleteUsers(selectedRecycleRowKeys.value);
      selectedRecycleRowKeys.value = [];
      message.success('批量彻底删除成功');
      Promise.all([loadUserList(), loadRecycleList()]);
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<UserType>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => userApiService.getUserList({
      deptId: searchForm.deptId,
      page,
      pageSize,
      status: searchForm.status,
      username: searchForm.username,
    }),
    filename: `users_${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '用户管理',
    });
  } finally {
    exporting.value = false;
  }
};

const handleImport = async () => {
  await openCrudImport<Record<string, any>>({
    afterDone: loadUserList,
    columns: importColumns,
    moduleName: '用户',
    rules: [
      '导入用户默认不分配部门、角色和岗位，请导入后在用户编辑页维护。',
      '用户名、邮箱、手机号需满足后端唯一性校验，重复行会在结果中标记失败。',
    ],
    submit: async (payload) => {
      await userApiService.createUser({
        dept_id: 0,
        email: String(payload.email || ''),
        nickname: String(payload.nickname || payload.username || ''),
        password: String(payload.password || ''),
        post_ids: [],
        phone: String(payload.phone || ''),
        remark: String(payload.remark || ''),
        role_ids: [],
        status: parseStatus(payload.status, 1),
        username: String(payload.username || ''),
      } as any);
    },
  });
};

const handleFormSuccess = () => {
  Promise.all([loadUserList(), loadRecycleList()]);
};

function userStatusText(status?: number) {
  return status === 1 ? '启用' : '禁用';
}

onMounted(() => {
  searchForm.deptId = normalizeDeptId(route.query.deptId);
  Promise.all([loadUserList(), loadRecycleList(), loadDeptOptions()]);
});

watch(
  () => route.query.deptId,
  (value) => {
    const nextDeptId = normalizeDeptId(value);
    if (searchForm.deptId === nextDeptId) {
      return;
    }

    searchForm.deptId = nextDeptId;
    pagination.current = 1;
    recyclePagination.current = 1;
    Promise.all([loadUserList(), loadRecycleList()]);
  },
);
</script>
