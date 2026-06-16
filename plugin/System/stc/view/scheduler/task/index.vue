<template>
  <Page title="定时任务">
    <template #extra>
      <Space wrap class="justify-end">
        <Button v-if="activeTab === 'tasks' && canCreateTasks" type="primary" @click="handleAdd">
          <span class="i-lucide-plus" />
          新增任务
        </Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs" @change="handleTabChange">
        <TabPane key="tasks" tab="定时任务">
          <Card class="mb-5">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容">
                  <Input v-model:value="taskSearch.keyword" allow-clear placeholder="任务名称 / 编码 / 所属模块" />
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="启用状态">
                  <Select v-model:value="taskSearch.status" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption :value="1">启用</SelectOption>
                    <SelectOption :value="0">禁用</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="最近结果">
                  <Select v-model:value="taskSearch.last_status" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption value="pending">待执行</SelectOption>
                    <SelectOption value="running">执行中</SelectOption>
                    <SelectOption value="success">成功</SelectOption>
                    <SelectOption value="failed">失败</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6" class="crud-search-grid__actions">
                <Space wrap>
                  <Button type="primary" :loading="loadingTasks" @click="handleTaskSearch"><span class="i-lucide-search" />搜索</Button>
                  <Button :disabled="loadingTasks" @click="handleTaskReset"><span class="i-lucide-refresh-cw" />重置</Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary :items="taskFilterItems" empty-text="当前显示全部定时任务，可按任务名称、状态和最近执行结果筛选。" />
          </Card>

          <Card>
            <CrudTableHeader title="定时任务" description="管理系统自动执行的清理、缓存、优化等任务；业务插件任务只能查看，规则请到对应业务页面编辑。" :count-text="`${taskPagination.total} 条记录`" />
            <Table
              :columns="taskColumns"
              :data-source="taskData"
              :loading="loadingTasks"
              :locale="buildCrudTableLocale('暂无定时任务')"
              :pagination="taskPagination"
              :scroll="taskTableScroll"
              row-key="id"
              @change="handleTaskTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft">
                    <div class="truncate">{{ record.name || '-' }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'owner'">
                  <div class="scheduler-owner-cell">
                    <span class="truncate">{{ ownerText(record as SchedulerApi.ScheduledTask) }}</span>
                    <Tag v-if="!isSystemOwned(record as SchedulerApi.ScheduledTask)" color="blue">业务任务</Tag>
                  </div>
                </template>
                <template v-else-if="column.key === 'schedule'">
                  {{ scheduleText(record as SchedulerApi.ScheduledTask) }}
                </template>
                <template v-else-if="column.key === 'next_run_at'">
                  {{ record.next_run_at || '-' }}
                </template>
                <template v-else-if="column.key === 'status'">
                  <Tag :color="record.status === 1 ? 'success' : 'default'">{{ record.status === 1 ? '启用' : '禁用' }}</Tag>
                </template>
                <template v-else-if="column.key === 'last_status'">
                  <Tag :color="runStatusColor(record.last_status)">{{ runStatusText(record.last_status) }}</Tag>
                </template>
                <template v-else-if="column.key === 'last_run_at'">
                  {{ record.last_run_at || '-' }}
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="taskActions(record as SchedulerApi.ScheduledTask)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canViewLogs" key="logs" tab="执行日志">
          <Card class="mb-5">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容">
                  <Input v-model:value="logSearch.keyword" allow-clear placeholder="任务名称 / 编码 / 结果消息" />
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="执行结果">
                  <Select v-model:value="logSearch.status" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption value="running">执行中</SelectOption>
                    <SelectOption value="success">成功</SelectOption>
                    <SelectOption value="failed">失败</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="触发方式">
                  <Select v-model:value="logSearch.trigger_type" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption value="auto">自动</SelectOption>
                    <SelectOption value="manual">手动</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6" class="crud-search-grid__actions">
                <Space wrap>
                  <Button type="primary" :loading="loadingLogs" @click="handleLogSearch"><span class="i-lucide-search" />搜索</Button>
                  <Button :disabled="loadingLogs" @click="handleLogReset"><span class="i-lucide-refresh-cw" />重置</Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary :items="logFilterItems" empty-text="当前显示全部执行日志，可按任务、结果和触发方式筛选。" />
          </Card>

          <Card>
            <CrudTableHeader title="执行日志" description="查看自动执行和手动执行的结果，失败时优先看结果消息。" :count-text="`${logPagination.total} 条记录`" />
            <Table
              :columns="logColumns"
              :data-source="logData"
              :loading="loadingLogs"
              :locale="buildCrudTableLocale('暂无执行日志')"
              :pagination="logPagination"
              :scroll="logTableScroll"
              row-key="id"
              @change="handleLogTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'task_name'">
                  <Tooltip :title="record.task_name" placement="topLeft">
                    <div class="truncate">{{ record.task_name || '-' }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'status'">
                  <Tag :color="runStatusColor(record.status)">{{ runStatusText(record.status) }}</Tag>
                </template>
                <template v-else-if="column.key === 'trigger_type'">
                  <Tag :color="record.trigger_type === 'manual' ? 'blue' : 'default'">{{ record.trigger_type === 'manual' ? '手动' : '自动' }}</Tag>
                </template>
                <template v-else-if="column.key === 'started_at'">
                  {{ record.started_at || '-' }}
                </template>
                <template v-else-if="column.key === 'message'">
                  <Tooltip :title="record.message" placement="topLeft">
                    <div class="truncate">{{ record.message || '-' }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'duration_ms'">
                  {{ durationText(record.duration_ms) }}
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="logActions(record as SchedulerApi.ScheduledTaskLog)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>
      </Tabs>
    </Card>

    <AppDrawer
      :confirm-loading="saving"
      :open="formOpen"
      :title="formState.id ? '编辑任务' : '新增任务'"
      ok-text="确定"
      width-size="md"
      @close="formOpen = false"
      @ok="handleSubmit"
    >
      <Form layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :span="24">
            <FormItem label="任务类型" required>
              <Select v-model:value="formState.code" :disabled="Boolean(formState.id)" placeholder="请选择任务" @change="handleDefinitionChange">
                <SelectOption v-for="item in taskOptions" :key="item.code" :value="item.code">
                  <div class="scheduler-option">
                    <div class="scheduler-option__title">{{ item.description ? `${item.name} - ${item.description}` : item.name }}</div>
                    <div class="scheduler-option__desc">{{ item.code }}</div>
                  </div>
                </SelectOption>
              </Select>
            </FormItem>
          </Col>
          <Col :span="24">
            <FormItem label="任务名称" required>
              <Input v-model:value="formState.name" :maxlength="120" placeholder="请输入任务名称" />
            </FormItem>
          </Col>
          <Col :span="12">
            <FormItem label="周期类型" required>
              <Select v-model:value="formState.schedule_type" @change="resetScheduleConfig">
                <SelectOption v-for="item in scheduleTypes" :key="item.value" :value="item.value">{{ item.label }}</SelectOption>
              </Select>
            </FormItem>
          </Col>

          <Col v-if="formState.schedule_type === 'every_minutes'" :span="12">
            <FormItem label="间隔分钟" required>
              <InputNumber v-model:value="formState.schedule_config.interval" :min="1" :max="1440" class="w-full" />
            </FormItem>
          </Col>
          <Col v-if="formState.schedule_type === 'hourly'" :span="12">
            <FormItem label="分钟" required>
              <InputNumber v-model:value="formState.schedule_config.minute" :min="0" :max="59" class="w-full" />
            </FormItem>
          </Col>
          <template v-if="['daily', 'weekly', 'monthly'].includes(formState.schedule_type)">
            <Col v-if="formState.schedule_type === 'weekly'" :span="12">
              <FormItem label="星期" required>
                <Select v-model:value="formState.schedule_config.weekday">
                  <SelectOption :value="1">星期一</SelectOption>
                  <SelectOption :value="2">星期二</SelectOption>
                  <SelectOption :value="3">星期三</SelectOption>
                  <SelectOption :value="4">星期四</SelectOption>
                  <SelectOption :value="5">星期五</SelectOption>
                  <SelectOption :value="6">星期六</SelectOption>
                  <SelectOption :value="7">星期日</SelectOption>
                </Select>
              </FormItem>
            </Col>
            <Col v-if="formState.schedule_type === 'monthly'" :span="12">
              <FormItem label="日期" required>
                <InputNumber v-model:value="formState.schedule_config.day" :min="1" :max="31" class="w-full" />
              </FormItem>
            </Col>
            <Col :span="12">
              <FormItem label="小时" required>
                <InputNumber v-model:value="formState.schedule_config.hour" :min="0" :max="23" class="w-full" />
              </FormItem>
            </Col>
            <Col :span="12">
              <FormItem label="分钟" required>
                <InputNumber v-model:value="formState.schedule_config.minute" :min="0" :max="59" class="w-full" />
              </FormItem>
            </Col>
          </template>
          <Col :span="24">
            <FormItem label="状态">
              <RadioGroup v-model:value="formState.status" option-type="button" :options="statusOptions" />
            </FormItem>
          </Col>

          <Col :span="24">
            <Collapse v-model:activeKey="advancedKeys" ghost class="scheduler-advanced">
              <CollapsePanel key="advanced" header="高级设置">
                <Row :gutter="[16, 0]">
                  <Col :span="12">
                    <FormItem label="超时时间">
                      <InputNumber v-model:value="formState.timeout" :min="1" :max="86400" class="w-full" addon-after="秒" />
                    </FormItem>
                  </Col>
                  <Col :span="24">
                    <FormItem label="执行参数">
                      <Textarea v-model:value="paramsJson" :rows="5" placeholder='例如 {"days": 180}' />
                    </FormItem>
                  </Col>
                </Row>
              </CollapsePanel>
            </Collapse>
          </Col>
          <Col :span="24">
            <FormItem label="备注">
              <Textarea v-model:value="formState.remark" :maxlength="1000" :rows="3" placeholder="请输入备注" />
            </FormItem>
          </Col>
        </Row>
      </Form>
    </AppDrawer>

    <Modal :open="taskDetailOpen" title="任务详情" :width="popupWidth.lg" ok-text="关闭" @cancel="taskDetailOpen = false" @ok="taskDetailOpen = false">
      <CrudDetailPanel v-if="currentTask">
        <CrudDetailHero
          icon="i-lucide-calendar-clock"
          :title="currentTask.name || '-'"
          :lines="[`下次执行：${currentTask.next_run_at || '-'}`, `最近执行：${currentTask.last_run_at || '-'}`]"
          :tags="[
            { label: currentTask.status === 1 ? '启用' : '禁用', color: currentTask.status === 1 ? 'success' : 'default' },
            { label: runStatusText(currentTask.last_status), color: runStatusColor(currentTask.last_status) },
          ]"
        />
        <CrudDetailDescriptions>
          <DescriptionsItem label="所属模块">{{ ownerText(currentTask) }}</DescriptionsItem>
          <DescriptionsItem label="执行周期">{{ scheduleText(currentTask) }}</DescriptionsItem>
          <DescriptionsItem label="最近执行">{{ currentTask.last_run_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="最近消息">{{ currentTask.last_message || '-' }}</DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentTask.remark || '-' }}</DescriptionsItem>
        </CrudDetailDescriptions>
        <div class="scheduler-detail-section">技术信息</div>
        <CrudDetailDescriptions>
          <DescriptionsItem label="任务 ID">{{ currentTask.id }}</DescriptionsItem>
          <DescriptionsItem label="任务编码">{{ currentTask.code }}</DescriptionsItem>
          <DescriptionsItem label="任务分组">{{ currentTask.group_name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="归属插件">{{ currentTask.owner_plugin || 'system' }}</DescriptionsItem>
          <DescriptionsItem label="归属类型">{{ currentTask.owner_type || 'system' }}</DescriptionsItem>
          <DescriptionsItem label="归属资源ID">{{ currentTask.owner_id || 0 }}</DescriptionsItem>
          <DescriptionsItem label="超时时间">{{ currentTask.timeout }} 秒</DescriptionsItem>
          <DescriptionsItem label="执行参数" :span="2"><pre class="scheduler-json">{{ formatJson(currentTask.params) }}</pre></DescriptionsItem>
        </CrudDetailDescriptions>
      </CrudDetailPanel>
    </Modal>

    <Modal :open="logDetailOpen" title="执行日志详情" :width="popupWidth.lg" ok-text="关闭" @cancel="logDetailOpen = false" @ok="logDetailOpen = false">
      <CrudDetailPanel v-if="currentLog">
        <CrudDetailHero
          icon="i-lucide-list-checks"
          :title="currentLog.task_name || '-'"
          :lines="[`开始时间：${currentLog.started_at || '-'}`, `耗时：${durationText(currentLog.duration_ms)}`]"
          :tags="[
            { label: runStatusText(currentLog.status), color: runStatusColor(currentLog.status) },
            { label: currentLog.trigger_type === 'manual' ? '手动' : '自动' },
          ]"
        />
        <CrudDetailDescriptions>
          <DescriptionsItem label="日志 ID">{{ currentLog.id }}</DescriptionsItem>
          <DescriptionsItem label="任务 ID">{{ currentLog.task_id }}</DescriptionsItem>
          <DescriptionsItem label="任务编码">{{ currentLog.task_code }}</DescriptionsItem>
          <DescriptionsItem label="所属模块">{{ ownerText(currentLog) }}</DescriptionsItem>
          <DescriptionsItem label="开始时间">{{ currentLog.started_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="结束时间">{{ currentLog.finished_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="结果消息" :span="2">{{ currentLog.message || '-' }}</DescriptionsItem>
          <DescriptionsItem label="结果摘要" :span="2"><pre class="scheduler-json">{{ formatJson(currentLog.result) }}</pre></DescriptionsItem>
        </CrudDetailDescriptions>
      </CrudDetailPanel>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import {
  buildCrudTableLocale,
  CrudDetailDescriptions,
  CrudDetailHero,
  CrudDetailPanel,
  CrudFilterSummary,
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import {
  Button,
  Card,
  Col,
  Collapse,
  CollapsePanel,
  DescriptionsItem,
  Form,
  FormItem,
  Input,
  InputNumber,
  message,
  Modal,
  RadioGroup,
  Row,
  Select,
  SelectOption,
  Space,
  Table,
  Tabs,
  TabPane,
  Tag,
  Textarea,
  Tooltip,
} from 'ant-design-vue';

import { schedulerApiService, type SchedulerApi } from '@plugin/System/stc/view/api';
import AppDrawer from '#/components/app-drawer.vue';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { popupWidth } from '#/utils/popup';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';

const { hasAccessByCodes } = useAccess();
const canCreateTasks = computed(() => hasAccessByCodes(['system.scheduler.task.create']));
const canUpdateTasks = computed(() => hasAccessByCodes(['system.scheduler.task.update']));
const canDeleteTasks = computed(() => hasAccessByCodes(['system.scheduler.task.delete']));
const canStatusTasks = computed(() => hasAccessByCodes(['system.scheduler.task.status']));
const canRunTasks = computed(() => hasAccessByCodes(['system.scheduler.task.run']));
const canViewLogs = computed(() => hasAccessByCodes(['system.scheduler.log.index']));

const activeTab = ref('tasks');
const loadingTasks = ref(false);
const loadingLogs = ref(false);
const saving = ref(false);
const formOpen = ref(false);
const taskDetailOpen = ref(false);
const logDetailOpen = ref(false);
const taskData = ref<SchedulerApi.ScheduledTask[]>([]);
const logData = ref<SchedulerApi.ScheduledTaskLog[]>([]);
const taskOptions = ref<SchedulerApi.TaskDefinition[]>([]);
const scheduleTypes = ref<SchedulerApi.ScheduleTypeOption[]>([]);
const currentTask = ref<SchedulerApi.ScheduledTask | null>(null);
const currentLog = ref<SchedulerApi.ScheduledTaskLog | null>(null);
const paramsJson = ref('{}');
const advancedKeys = ref<string[]>([]);

const statusOptions = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];

const taskSearch = reactive({
  keyword: '',
  status: undefined as number | undefined,
  last_status: undefined as string | undefined,
});

const logSearch = reactive({
  keyword: '',
  status: undefined as string | undefined,
  trigger_type: undefined as string | undefined,
  task_id: undefined as number | undefined,
});

const taskPagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const logPagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const formState = reactive<SchedulerApi.TaskFormData & { id?: number }>({
  code: '',
  name: '',
  schedule_type: 'daily',
  schedule_config: { hour: 2, minute: 0 },
  params: {},
  timeout: 3600,
  status: 1,
  remark: '',
});

const taskFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  if (taskSearch.keyword.trim()) items.push({ label: '关键字', value: taskSearch.keyword.trim() });
  if (typeof taskSearch.status === 'number') items.push({ label: '状态', value: taskSearch.status === 1 ? '启用' : '禁用' });
  if (taskSearch.last_status) items.push({ label: '结果', value: runStatusText(taskSearch.last_status) });
  return items;
});

const logFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  if (logSearch.keyword.trim()) items.push({ label: '关键字', value: logSearch.keyword.trim() });
  if (logSearch.status) items.push({ label: '结果', value: runStatusText(logSearch.status) });
  if (logSearch.trigger_type) items.push({ label: '触发', value: logSearch.trigger_type === 'manual' ? '手动' : '自动' });
  if (logSearch.task_id) items.push({ label: '任务ID', value: String(logSearch.task_id) });
  return items;
});

const taskActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([taskActions({} as SchedulerApi.ScheduledTask)], { maxWidth: 260 }));
const logActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([logActions({} as SchedulerApi.ScheduledTaskLog)], { maxWidth: 120 }));

const taskColumns = computed(() => [
  { title: '任务名称', dataIndex: 'name', key: 'name', width: 220 },
  { title: '所属模块', key: 'owner', width: 180 },
  { title: '执行周期', key: 'schedule', width: 180 },
  { title: '下次执行', dataIndex: 'next_run_at', key: 'next_run_at', width: 180 },
  { title: '启用状态', dataIndex: 'status', key: 'status', width: 110 },
  { title: '最近结果', dataIndex: 'last_status', key: 'last_status', width: 110 },
  { title: '最近执行', dataIndex: 'last_run_at', key: 'last_run_at', width: 180 },
  { title: '操作', key: 'action', width: taskActionColumnWidth.value, fixed: 'right' as const },
]);

const logColumns = computed(() => [
  { title: '任务名称', dataIndex: 'task_name', key: 'task_name', width: 220 },
  { title: '触发方式', dataIndex: 'trigger_type', key: 'trigger_type', width: 100 },
  { title: '执行结果', dataIndex: 'status', key: 'status', width: 110 },
  { title: '开始时间', dataIndex: 'started_at', key: 'started_at', width: 180 },
  { title: '耗时', dataIndex: 'duration_ms', key: 'duration_ms', width: 110 },
  { title: '结果消息', dataIndex: 'message', key: 'message', width: 260 },
  { title: '操作', key: 'action', width: logActionColumnWidth.value, fixed: 'right' as const },
]);

const taskTableScroll = computed(() => buildTableScrollX(taskColumns.value));
const logTableScroll = computed(() => buildTableScrollX(logColumns.value));

function taskActions(record: SchedulerApi.ScheduledTask) {
  const enabled = Number(record.status || 0) === 1;
  const running = Number(record.running || 0) === 1;
  const systemOwned = isSystemOwned(record);
  return [
    { label: '查看', onClick: () => handleTaskDetail(record) },
    { label: '编辑', visible: canUpdateTasks.value && systemOwned, disabled: running, onClick: () => handleEdit(record) },
    { label: enabled ? '停用' : '启用', visible: canStatusTasks.value && systemOwned, onClick: () => handleStatus(record, enabled ? 0 : 1) },
    { label: '执行', visible: canRunTasks.value && systemOwned, disabled: !enabled || running, confirmTitle: '确认立即执行该任务？', onClick: () => handleRun(record) },
    { label: '日志', visible: canViewLogs.value, onClick: () => openTaskLogs(record) },
    { label: '删除', visible: canDeleteTasks.value && systemOwned, disabled: running, danger: true, confirmTitle: '确认删除该任务？', confirmContent: '删除后该计划不会继续自动执行。', onClick: () => handleDelete(record) },
  ];
}

function logActions(record: SchedulerApi.ScheduledTaskLog) {
  return [
    { label: '查看', onClick: () => handleLogDetail(record) },
  ];
}

async function loadOptions() {
  const data = await schedulerApiService.getTaskOptions();
  taskOptions.value = (data?.tasks || []).filter((item) => item.owner_plugin === 'system');
  scheduleTypes.value = data?.schedule_types || [];
}

async function loadTasks() {
  if (loadingTasks.value) return;
  loadingTasks.value = true;
  try {
    const response = await schedulerApiService.getTaskList({
      page: taskPagination.current,
      pageSize: taskPagination.pageSize,
      keyword: taskSearch.keyword,
      status: taskSearch.status,
      last_status: taskSearch.last_status,
    });
    taskData.value = response.items || [];
    taskPagination.total = response.pageInfo?.total || response.total || 0;
  } finally {
    loadingTasks.value = false;
  }
}

async function loadLogs() {
  if (!canViewLogs.value || loadingLogs.value) return;
  loadingLogs.value = true;
  try {
    const response = await schedulerApiService.getLogList({
      page: logPagination.current,
      pageSize: logPagination.pageSize,
      keyword: logSearch.keyword,
      status: logSearch.status,
      trigger_type: logSearch.trigger_type,
      task_id: logSearch.task_id,
    });
    logData.value = response.items || [];
    logPagination.total = response.pageInfo?.total || response.total || 0;
  } finally {
    loadingLogs.value = false;
  }
}

function handleTaskSearch() {
  taskPagination.current = 1;
  void loadTasks();
}

function handleTaskReset() {
  taskSearch.keyword = '';
  taskSearch.status = undefined;
  taskSearch.last_status = undefined;
  taskPagination.current = 1;
  void loadTasks();
}

function handleLogSearch() {
  logPagination.current = 1;
  void loadLogs();
}

function handleLogReset() {
  logSearch.keyword = '';
  logSearch.status = undefined;
  logSearch.trigger_type = undefined;
  logSearch.task_id = undefined;
  logPagination.current = 1;
  void loadLogs();
}

function handleTaskTableChange(pag: any) {
  taskPagination.current = pag.current;
  taskPagination.pageSize = pag.pageSize;
  void loadTasks();
}

function handleLogTableChange(pag: any) {
  logPagination.current = pag.current;
  logPagination.pageSize = pag.pageSize;
  void loadLogs();
}

function handleTabChange(key: string | number) {
  if (key === 'logs') void loadLogs();
}

function handleAdd() {
  const first = taskOptions.value[0];
  Object.assign(formState, {
    id: undefined,
    code: first?.code || '',
    name: first?.name || '',
    schedule_type: 'daily',
    schedule_config: { hour: 2, minute: 0 },
    params: {},
    timeout: first?.timeout || 3600,
    status: 1,
    remark: '',
  });
  paramsJson.value = '{}';
  advancedKeys.value = [];
  formOpen.value = true;
}

function handleEdit(record: SchedulerApi.ScheduledTask) {
  Object.assign(formState, {
    id: record.id,
    code: record.code,
    name: record.name,
    schedule_type: record.schedule_type,
    schedule_config: { ...(record.schedule_config || {}) },
    params: { ...(record.params || {}) },
    timeout: record.timeout || definitionByCode(record.code)?.timeout || 3600,
    status: record.status,
    remark: record.remark || '',
  });
  paramsJson.value = formatJson(formState.params || {});
  advancedKeys.value = [];
  formOpen.value = true;
}

function handleDefinitionChange() {
  const definition = definitionByCode(formState.code);
  if (!definition) return;
  formState.name = definition.name;
  formState.timeout = definition.timeout;
}

function resetScheduleConfig() {
  formState.schedule_config = defaultScheduleConfig(formState.schedule_type);
}

async function handleSubmit() {
  if (saving.value) return;
  if (!formState.code || !formState.name) {
    message.warning('请选择任务并填写任务名称');
    return;
  }
  const params = parseJson(paramsJson.value);
  if (params === null) return;

  saving.value = true;
  try {
    const payload: SchedulerApi.TaskFormData = {
      code: formState.code,
      name: formState.name,
      schedule_type: formState.schedule_type,
      schedule_config: formState.schedule_config || {},
      params,
      timeout: formState.timeout,
      status: formState.status,
      remark: formState.remark,
    };
    if (formState.id) {
      await schedulerApiService.updateTask(formState.id, payload);
      message.success('更新成功');
    } else {
      await schedulerApiService.createTask(payload);
      message.success('创建成功');
    }
    formOpen.value = false;
    void loadTasks();
  } finally {
    saving.value = false;
  }
}

async function handleStatus(record: SchedulerApi.ScheduledTask, status: number) {
  await schedulerApiService.updateTaskStatus(record.id, status);
  message.success('状态更新成功');
  await loadTasks();
}

async function handleRun(record: SchedulerApi.ScheduledTask) {
  const result = await schedulerApiService.runTask(record.id);
  if ((result as any)?.status === 'failed') {
    message.error((result as any)?.message || '执行失败');
  } else {
    message.success('执行完成');
  }
  await Promise.all([loadTasks(), canViewLogs.value ? loadLogs() : Promise.resolve()]);
}

async function handleDelete(record: SchedulerApi.ScheduledTask) {
  await schedulerApiService.deleteTask(record.id);
  message.success('删除成功');
  await loadTasks();
}

function handleTaskDetail(record: SchedulerApi.ScheduledTask) {
  currentTask.value = record;
  taskDetailOpen.value = true;
}

function handleLogDetail(record: SchedulerApi.ScheduledTaskLog) {
  currentLog.value = record;
  logDetailOpen.value = true;
}

function openTaskLogs(record: SchedulerApi.ScheduledTask) {
  logSearch.task_id = record.id;
  logPagination.current = 1;
  activeTab.value = 'logs';
  void loadLogs();
}

function definitionByCode(code: string) {
  return taskOptions.value.find((item) => item.code === code && item.owner_plugin === 'system');
}

type SchedulerOwner = {
  owner_id?: number | string | null;
  owner_name?: null | string;
  owner_plugin?: null | string;
  owner_type?: null | string;
};

function isSystemOwned(record: SchedulerOwner) {
  return (record.owner_plugin || 'system') === 'system' && (record.owner_type || 'system') === 'system' && Number(record.owner_id || 0) === 0;
}

function ownerText(record: SchedulerOwner) {
  if (isSystemOwned(record)) return '系统任务';
  return record.owner_name || '业务任务';
}

function durationText(value?: number) {
  const duration = Number(value || 0);
  if (duration <= 0) return '-';
  if (duration < 1000) return `${duration} ms`;
  return `${(duration / 1000).toFixed(duration >= 10_000 ? 0 : 1)} 秒`;
}

function defaultScheduleConfig(type: string) {
  if (type === 'every_minutes') return { interval: 5 };
  if (type === 'hourly') return { minute: 0 };
  if (type === 'weekly') return { weekday: 1, hour: 2, minute: 0 };
  if (type === 'monthly') return { day: 1, hour: 2, minute: 0 };
  return { hour: 2, minute: 0 };
}

function scheduleText(record: Pick<SchedulerApi.ScheduledTask, 'schedule_config' | 'schedule_type'>) {
  const config = record.schedule_config || {};
  if (record.schedule_type === 'every_minutes') return `每 ${config.interval || 5} 分钟`;
  if (record.schedule_type === 'hourly') return `每小时第 ${pad(config.minute || 0)} 分钟`;
  if (record.schedule_type === 'weekly') return `每周${weekdayText(config.weekday || 1)} ${pad(config.hour || 0)}:${pad(config.minute || 0)}`;
  if (record.schedule_type === 'monthly') return `每月 ${config.day || 1} 日 ${pad(config.hour || 0)}:${pad(config.minute || 0)}`;
  return `每天 ${pad(config.hour || 0)}:${pad(config.minute || 0)}`;
}

function runStatusText(status?: string) {
  if (status === 'success') return '成功';
  if (status === 'failed') return '失败';
  if (status === 'running') return '执行中';
  return '待执行';
}

function runStatusColor(status?: string) {
  if (status === 'success') return 'success';
  if (status === 'failed') return 'error';
  if (status === 'running') return 'processing';
  return 'default';
}

function weekdayText(value: number) {
  return ['一', '二', '三', '四', '五', '六', '日'][Math.max(1, Math.min(7, Number(value || 1))) - 1];
}

function pad(value: unknown) {
  return String(Math.max(0, Number(value || 0))).padStart(2, '0');
}

function formatJson(value: unknown) {
  return JSON.stringify(value || {}, null, 2);
}

function parseJson(value: string) {
  try {
    const parsed = value.trim() ? JSON.parse(value) : {};
    if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
      message.warning('执行参数必须是 JSON 对象');
      return null;
    }
    return parsed;
  } catch {
    message.warning('执行参数 JSON 格式错误');
    return null;
  }
}

onMounted(async () => {
  await Promise.all([loadOptions(), loadTasks()]);
});
</script>

<style scoped>
.scheduler-owner-cell {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 8px;
}

.scheduler-owner-cell .truncate {
  min-width: 0;
}

.scheduler-option {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 2px 0;
  line-height: 1.45;
}

.scheduler-option__title {
  color: var(--ant-colorText, hsl(var(--foreground)));
  font-weight: 500;
  white-space: normal;
}

.scheduler-option__desc {
  color: var(--ant-colorTextSecondary, hsl(var(--muted-foreground)));
  font-size: 12px;
  line-height: 1.35;
  word-break: break-all;
}

.scheduler-advanced {
  margin-bottom: 12px;
  border-top: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-bottom: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
}

.scheduler-detail-section {
  margin: 18px 0 12px;
  color: var(--ant-colorText, hsl(var(--foreground)));
  font-size: 14px;
  font-weight: 600;
}

.scheduler-json {
  margin: 0;
  max-height: 240px;
  overflow: auto;
  padding: 12px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 6px;
  background: var(--ant-colorFillQuaternary, hsl(var(--muted) / 38%));
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
