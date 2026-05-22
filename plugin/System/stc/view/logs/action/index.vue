<template>
  <Page title="操作日志">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button v-if="canAccessActionLogAnalysis" @click="activeTab = 'extra'">
            <span class="i-lucide-chart-column mr-1" />分析告警
          </Button>
          <Button
            v-if="canDeleteActionLogs"
            danger
            :disabled="selectedRowKeys.length === 0"
            @click="handleBatchDelete"
          >
            <span class="i-lucide-trash-2 mr-1" />批量删除
          </Button>
          <Button v-if="canExportActionLogs" :loading="exporting" @click="handleExport">
            <span class="i-lucide-download mr-1" />导出
          </Button>
          <Button v-if="canClearActionLogs" danger @click="handleClear">
            <span class="i-lucide-trash-2 mr-1" />清空日志
          </Button>
        </template>
        <template v-else-if="activeTab === 'recycle'">
          <Button
            v-if="canRecoveryActionLogs"
            :disabled="selectedRecycleRowKeys.length === 0"
            @click="handleBatchRecovery"
          >
            批量恢复
          </Button>
          <Button
            v-if="canRealDeleteActionLogs"
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
          <Card class="logs-search-card mb-5" :body-style="{ padding: '20px 24px' }">
            <div class="logs-search-block logs-filter-block">
              <div class="logs-search-block-title">筛选条件</div>
              <Row class="crud-search-grid" :gutter="[16, 16]">
                <Col :xs="24" :sm="12" :md="8" :xl="6">
                  <SearchField label="搜索内容"><Input v-model:value="searchForm.keyword" placeholder="用户 / 业务 / 变更内容 / 路由 / IP" allow-clear /></SearchField>
                </Col>
                <Col :xs="24" :sm="12" :md="8" :xl="6">
                  <SearchField label="操作用户"><Input v-model:value="searchForm.username" placeholder="请输入操作用户" allow-clear /></SearchField>
                </Col>
                <Col :xs="24" :sm="12" :md="8" :xl="6">
                  <SearchField label="响应代码">
                    <Select
                      v-model:value="searchForm.response_code"
                      placeholder="请选择"
                      allow-clear
                      class="w-full"
                    >
                      <SelectOption value="200">200</SelectOption>
                      <SelectOption value="400">400</SelectOption>
                      <SelectOption value="401">401</SelectOption>
                      <SelectOption value="403">403</SelectOption>
                      <SelectOption value="404">404</SelectOption>
                      <SelectOption value="500">500</SelectOption>
                    </Select>
                  </SearchField>
                </Col>
                <Col :xs="24" :sm="12" :md="8" :xl="5">
                  <SearchField label="创建时间">
                    <RangePicker
                      v-model:value="searchForm.created_at"
                      :placeholder="['开始日期', '结束日期']"
                      class="w-full max-w-full"
                      format="YYYY-MM-DD"
                    />
                  </SearchField>
                </Col>
              </Row>
              <Row :gutter="[16, 16]" class="logs-filter-secondary-row crud-search-grid">
                <Col :xs="24" :sm="12" :md="8" :xl="6">
                  <SearchField label="业务名称"><Input v-model:value="searchForm.name" placeholder="请输入业务名称" allow-clear /></SearchField>
                </Col>
                <Col :xs="24" :sm="12" :md="8" :xl="6">
                  <SearchField label="请求路由"><Input v-model:value="searchForm.router" placeholder="请输入请求路由" allow-clear /></SearchField>
                </Col>
                <Col :xs="24" :sm="12" :md="8" :xl="6">
                  <SearchField label="来源地址"><Input v-model:value="searchForm.ip" placeholder="IP 地址" allow-clear /></SearchField>
                </Col>
                <Col :xs="24" :sm="24" :md="24" :xl="6" class="flex flex-wrap items-center gap-3 crud-search-grid__actions">
                  <Button type="primary" :loading="loading" @click="handleSearch">
                    <span class="i-lucide-search mr-1" />搜索
                  </Button>
                  <Button :disabled="loading" @click="handleReset">
                    <span class="i-lucide-refresh-cw mr-1" />重置
                  </Button>
                </Col>
              </Row>
            </div>

            <CrudFilterSummary
              :items="activeFilterItems"
              empty-text="当前显示全部日志记录，可按用户、业务、变更内容、路由、IP、响应码和日期范围快速筛选。"
            />

            <Divider class="logs-search-divider" />

            <div class="logs-search-block logs-refresh-panel">
              <div class="logs-search-block-title">自动刷新</div>
              <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="flex flex-wrap items-center gap-3">
                  <span class="inline-flex items-center gap-2 text-sm">
                    <span
                      class="inline-block h-2 w-2 shrink-0 rounded-full"
                      :class="autoRefresh ? 'logs-live-dot logs-live-on' : 'logs-live-off'"
                    />
                    <span class="logs-search-text-muted">{{ autoRefresh ? '监控中' : '未开启' }}</span>
                  </span>
                  <Switch v-model:checked="autoRefresh" checked-children="开" un-checked-children="关" />
                  <Select
                    v-model:value="refreshIntervalSec"
                    class="logs-refresh-interval-select"
                    :disabled="!autoRefresh"
                  >
                    <SelectOption :value="5">每 5 秒</SelectOption>
                    <SelectOption :value="10">每 10 秒</SelectOption>
                    <SelectOption :value="15">每 15 秒</SelectOption>
                    <SelectOption :value="30">每 30 秒</SelectOption>
                    <SelectOption :value="60">每 60 秒</SelectOption>
                  </Select>
                </div>
                <div v-if="lastRefreshedAt" class="logs-search-text-subtle sm:ml-auto">
                  上次刷新：{{ lastRefreshedAt }}
                </div>
              </div>
            </div>

          </Card>

          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card class="mb-5" size="small" title="近实时监控（与当前筛选条件一致）">
            <CrudStatCards :items="realtimeMetricCards" />
            <Row :gutter="[16, 16]" class="mt-4">
              <Col :xs="24" :md="12">
                <div class="logs-text-label text-sm">最近一条</div>
                <div class="logs-text-body text-base">{{ metrics.last_activity_at || '—' }}</div>
                <div v-if="metrics.last_activity_at" class="logs-search-text-subtle">
                  {{ formatFromNow(metrics.last_activity_at) }}
                </div>
              </Col>
              <Col :xs="24" :md="12">
                <div class="logs-text-label mb-2 text-sm">近 5 分钟响应码</div>
                <div v-if="codeTagItems.length" class="flex flex-wrap gap-2">
                  <CrudToneTag
                    v-for="item in codeTagItems"
                    :key="item.code"
                    :color="item.color"
                    :text="`${item.code}：${item.count}`"
                  />
                </div>
                <div v-else class="logs-search-text-muted text-sm">无数据</div>
              </Col>
            </Row>
            <CrudNoticeAlert
              v-if="metrics.errors_last_5m > 0"
              custom-class="mt-4"
              type="warning"
              :message="`近 5 分钟内有 ${metrics.errors_last_5m} 条非成功响应。`"
            />
          </Card>

          <Card>
            <CrudTableHeader
              title="日志台账"
              description="展示当前有效日志记录，可查看详情、导出、删除，并结合近实时监控定位异常请求。"
              :count-text="`${pagination.total} 条记录`"
            />
            <Table
              :columns="columns"
              :data-source="logData"
              :loading="loading"
              :locale="buildCrudTableLocale('暂无日志记录')"
              :pagination="pagination"
              :row-selection="rowSelection"
              row-key="id"
              :scroll="tableScrollX"
              :row-class-name="getRowClassName"
              @change="handleTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'response_code'">
                  <CrudToneTag :color="record.response_code === '200' ? 'green' : 'red'" :text="record.response_code || '-'" />
                </template>
                <template v-else-if="column.key === 'remark'">
                  <div class="logs-change-cell"><Tooltip :title="getChangeSummary(record as LogsActionRow)" placement="topLeft"><div class="truncate">{{ getChangeSummary(record as LogsActionRow) }}</div></Tooltip></div>
                </template>
                <template v-else-if="column.key === 'router'">
                  <div class="max-w-xs"><Tooltip :title="record.router" placement="topLeft"><div class="truncate">{{ record.router }}</div></Tooltip></div>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="logActions(record as LogsActionRow)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canRecoveryActionLogs || canRealDeleteActionLogs" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除日志"
              description="回收站中的日志可恢复到主列表；彻底删除后将不可恢复，请谨慎操作。"
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
              row-key="id"
              :scroll="recycleTableScrollX"
              @change="handleRecycleTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'response_code'">
                  <CrudToneTag :color="record.response_code === '200' ? 'green' : 'red'" :text="record.response_code || '-'" />
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="logRecycleActions(record as LogsActionRow)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canAccessActionLogAnalysis" key="extra" tab="分析告警">
          <Card class="mb-5" title="告警中心">
            <template v-if="alertCenterItems.length > 0">
              <div class="grid gap-4 lg:grid-cols-2">
                <div
                  v-for="item in alertCenterItems"
                  :key="item.key"
                  class="logs-alert-card"
                  :class="`logs-alert-card--${item.level}`"
                >
                  <div class="logs-alert-card-header">
                    <div>
                      <div class="logs-alert-card-title">{{ item.title }}</div>
                      <div class="logs-alert-card-description">{{ item.description }}</div>
                    </div>
                    <CrudToneTag :color="item.tagColor" :text="item.levelText" />
                  </div>
                  <div class="logs-alert-card-footer">
                    <span class="logs-alert-card-value">{{ item.value }}</span>
                    <Button type="link" size="small" @click="handleAlertAction(item.action)">
                      {{ item.actionText }}
                    </Button>
                  </div>
                </div>
              </div>
            </template>
            <CrudNoticeAlert
              v-else
              type="success"
              message="当前筛选范围内暂无需要重点关注的日志告警。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="analysisSummaryCards" />

          <CrudNoticeAlert
            custom-class="mb-5"
            :message="`今日 ${analysisStatistics.today} 条日志，高频业务 ${topBusinessLabel || '暂无'}。`"
          />

          <Row :gutter="16" class="mb-5">
            <Col :span="12">
              <Card title="高频业务">
                <Table :columns="rankColumns" :data-source="businessRows" :pagination="false" :scroll="rankTableScroll" row-key="name" size="small" />
              </Card>
            </Col>
            <Col :span="12">
              <Card title="活跃用户">
                <Table :columns="rankColumns" :data-source="userRows" :pagination="false" :scroll="rankTableScroll" row-key="name" size="small" />
              </Card>
            </Col>
          </Row>

          <Row :gutter="16" class="mb-5">
            <Col :span="12">
              <Card title="小时分布">
                <Table :columns="timeColumns" :data-source="hourlyRows" :pagination="false" :scroll="timeTableScroll" row-key="name" size="small" />
              </Card>
            </Col>
            <Col :span="12">
              <Card title="星期分布">
                <Table :columns="timeColumns" :data-source="weeklyRows" :pagination="false" :scroll="timeTableScroll" row-key="name" size="small" />
              </Card>
            </Col>
          </Row>

          <Card title="最近异常日志">
            <Table :columns="errorColumns" :data-source="analysisReport.error_logs" :pagination="{ pageSize: 10 }" :scroll="errorTableScroll" row-key="id" size="small">
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'response_code'">
                  <CrudToneTag :color="record.response_code === '200' ? 'green' : 'red'" :text="record.response_code || '-'" />
                </template>
                <template v-else-if="column.key === 'remark'">
                  <span class="logs-analysis-remark line-clamp-2">{{ getChangeSummary(record as LogsActionRow) }}</span>
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>
      </Tabs>
    </Card>

    <Modal
      :open="detailOpen"
      title="日志详情"
      width="min(980px, calc(100vw - 32px))"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentActionLog">
        <CrudDetailHero
          icon="i-lucide-scroll-text"
          :lines="[
            `请求路由：${currentActionLog.router || '-'}`,
            `IP 地址：${formatIpLocation(currentActionLog.ip, currentActionLog.ip_location)}`,
            `记录时间：${currentActionLog.created_at || '-'}`,
          ]"
          :tags="[
            { label: currentActionLog.method || '-' },
            { color: currentActionLog.response_code === '200' ? 'success' : 'error', label: currentActionLog.response_code || '-' },
            { label: currentActionLog.username || '匿名用户' },
          ]"
          :title="currentActionLog.name || '未命名日志'"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="日志 ID">{{ currentActionLog.id }}</DescriptionsItem>
          <DescriptionsItem label="操作用户">{{ currentActionLog.username || '-' }}</DescriptionsItem>
          <DescriptionsItem label="请求方式">{{ currentActionLog.method || '-' }}</DescriptionsItem>
          <DescriptionsItem label="响应码">{{ currentActionLog.response_code || '-' }}</DescriptionsItem>
          <DescriptionsItem label="业务名称">{{ currentActionLog.name || '-' }}</DescriptionsItem>
          <DescriptionsItem label="IP 地址">{{ currentActionLog.ip || '-' }}</DescriptionsItem>
          <DescriptionsItem label="IP 归属地">{{ currentActionLog.ip_location || '-' }}</DescriptionsItem>
          <DescriptionsItem label="操作系统">{{ currentActionLog.os || '-' }}</DescriptionsItem>
          <DescriptionsItem label="浏览器">{{ currentActionLog.browser || '-' }}</DescriptionsItem>
          <DescriptionsItem label="请求路由" :span="2">{{ currentActionLog.router || '-' }}</DescriptionsItem>
          <DescriptionsItem label="变更内容" :span="2">{{ currentChangeSummary }}</DescriptionsItem>
        </CrudDetailDescriptions>

        <Card v-if="currentChanges.length > 0" class="mb-4" size="small" title="变更日志">
          <div class="logs-change-summary">{{ currentChangeSummary }}</div>
          <Table
            v-if="changeFieldRows.length > 0"
            :columns="changeFieldColumns"
            :data-source="changeFieldRows"
            :pagination="false"
            :scroll="changeFieldTableScroll"
            row-key="key"
            size="small"
          />
          <CrudDetailSection v-else title="结构化变更数据" :content="changePayload" preformatted />
        </Card>

        <CrudDetailSection title="请求数据" :content="requestPayload" preformatted />

        <CrudDetailSection title="响应数据" :content="responsePayload" preformatted />
      </CrudDetailPanel>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/zh-cn';

import './logs-action-shared.css';

import { useAccess } from '@vben/access';
import {
  CrudNoticeAlert,
  CrudDetailDescriptions,
  CrudDetailPanel,
  CrudDetailSection,
  CrudDetailHero,
  CrudFilterSummary,
  buildCrudTableLocale,
  CrudStatCards,
  CrudToneTag,
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import {
  Button,
  Card,
  Col,
  DescriptionsItem,
  Divider,
  Input,
  message,
  Modal,
  RangePicker,
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

import { logsActionApiService } from '#/api';
import { exportCrudXlsx } from '#/utils/crud-excel';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';

import type { LogsActionChangeField, LogsActionChangeRow, LogsActionRealtimeMetrics, LogsActionSearchForm, LogsActionRow } from './types';

dayjs.extend(relativeTime);
dayjs.locale('zh-cn');

const searchForm = reactive<LogsActionSearchForm>({
  keyword: '',
  username: '',
  name: '',
  router: '',
  ip: '',
  response_code: undefined,
  created_at: undefined,
});
const activeTab = ref('data');
const loading = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const logData = ref<LogsActionRow[]>([]);
const selectedRowKeys = ref<number[]>([]);
const recycleData = ref<LogsActionRow[]>([]);
const selectedRecycleRowKeys = ref<number[]>([]);
const currentActionLog = ref<LogsActionRow | null>(null);
const currentChanges = ref<LogsActionChangeRow[]>([]);
const detailOpen = ref(false);
const { hasAccessByCodes } = useAccess();
const canDeleteActionLogs = computed(() => hasAccessByCodes(['system.logs.action.delete']));
const canClearActionLogs = computed(() => hasAccessByCodes(['system.logs.action.clear']));
const canExportActionLogs = computed(() => hasAccessByCodes(['system.logs.action.export']));
const canAccessActionLogAnalysis = computed(() => hasAccessByCodes(['system.logs.action.index']));
const canRecoveryActionLogs = computed(() => hasAccessByCodes(['system.logs.action.recovery']));
const canRealDeleteActionLogs = computed(() => hasAccessByCodes(['system.logs.action.real-delete']));

const autoRefresh = ref(false);
const refreshIntervalSec = ref(15);
const lastRefreshedAt = ref('');
const streamMaxId = ref(0);
const highlightIds = ref<Set<number>>(new Set());
let highlightTimer: ReturnType<typeof setTimeout> | null = null;
let pollTimer: ReturnType<typeof setInterval> | null = null;

const metrics = ref<LogsActionRealtimeMetrics>({
  server_time: '',
  last_activity_at: null,
  count_last_1m: 0,
  count_last_5m: 0,
  count_last_15m: 0,
  errors_last_5m: 0,
  events_per_minute_5m: 0,
  by_response_code_last_5m: {},
});

const codeTagItems = computed(() => {
  const m = metrics.value.by_response_code_last_5m || {};
  return Object.entries(m)
    .map(([code, count]) => ({
      code,
      count: Number(count),
      color: code === '200' ? 'success' : 'error',
    }))
    .sort((a, b) => b.count - a.count);
});

const analysisStatistics = ref({
  total: 0,
  today: 0,
  success_count: 0,
  warning_count: 0,
  error_count: 0,
  by_response_code: {} as Record<string, number>,
  by_user: {} as Record<string, number>,
  by_business: {} as Record<string, number>,
  by_time: {} as Record<string, number>,
});
const summaryCards = computed(() => [
  {
    desc: '当前筛选范围内的日志总量。',
    icon: 'i-lucide-scroll-text',
    label: '总日志数',
    value: String(stats.value.totalLogs),
  },
  {
    desc: '当前筛选范围内响应成功的日志数量。',
    icon: 'i-lucide-badge-check',
    label: '成功日志',
    value: String(stats.value.infoLogs),
  },
  {
    desc: '当前筛选范围内标记为警告的日志数量。',
    icon: 'i-lucide-triangle-alert',
    label: '警告日志',
    value: String(stats.value.warningLogs),
  },
  {
    desc: '当前筛选范围内错误日志和异常响应数量。',
    icon: 'i-lucide-octagon-alert',
    label: '错误日志',
    value: String(stats.value.errorLogs),
  },
]);

const realtimeMetricCards = computed(() => [
  {
    desc: '最近 1 分钟写入的日志数量。',
    icon: 'i-lucide-timer',
    label: '近 1 分钟',
    tone: 'info' as const,
    value: String(metrics.value.count_last_1m),
  },
  {
    desc: '最近 5 分钟写入的日志数量。',
    icon: 'i-lucide-clock-3',
    label: '近 5 分钟',
    tone: 'primary' as const,
    value: String(metrics.value.count_last_5m),
  },
  {
    desc: '最近 5 分钟内非 200 响应数量。',
    icon: 'i-lucide-octagon-alert',
    label: '近 5 分钟非 200',
    tone: metrics.value.errors_last_5m > 0 ? ('danger' as const) : ('success' as const),
    value: String(metrics.value.errors_last_5m),
  },
  {
    desc: '最近 5 分钟平均每分钟写入量。',
    icon: 'i-lucide-activity',
    label: '5 分钟吞吐（条/分）',
    tone: 'warning' as const,
    value: metrics.value.events_per_minute_5m.toFixed(2),
  },
]);
const analysisSummaryCards = computed(() => [
  {
    desc: '分析视图中的日志总量。',
    icon: 'i-lucide-chart-column',
    label: '日志总量',
    value: String(analysisStatistics.value.total),
  },
  {
    desc: '分析视图中的成功日志数量。',
    icon: 'i-lucide-circle-check',
    label: '成功日志',
    value: String(analysisStatistics.value.success_count),
  },
  {
    desc: '分析视图中的警告日志数量。',
    icon: 'i-lucide-badge-alert',
    label: '警告日志',
    value: String(analysisStatistics.value.warning_count),
  },
  {
    desc: '分析视图中的错误日志数量。',
    icon: 'i-lucide-bug',
    label: '错误日志',
    value: String(analysisStatistics.value.error_count),
  },
]);
const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const keyword = (searchForm.keyword || '').trim();
  const username = (searchForm.username || '').trim();
  const name = (searchForm.name || '').trim();
  const router = (searchForm.router || '').trim();
  const ip = (searchForm.ip || '').trim();
  if (keyword !== '') items.push({ label: '关键字', value: keyword });
  if (username !== '') items.push({ label: '操作用户', value: username });
  if (name !== '') items.push({ label: '业务名称', value: name });
  if (router !== '') items.push({ label: '请求路由', value: router });
  if (ip !== '') items.push({ label: 'IP', value: ip });
  if (searchForm.response_code) items.push({ label: '响应码', value: searchForm.response_code });
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

const analysisReport = ref({
  hourly_stats: {} as Record<string, number>,
  weekly_stats: {} as Record<string, number>,
  error_logs: [] as LogsActionRow[],
});

const rankColumns = [
  { title: '名称', dataIndex: 'name', key: 'name' },
  { title: '次数', dataIndex: 'count', key: 'count', width: 120 },
];

const timeColumns = [
  { title: '维度', dataIndex: 'name', key: 'name' },
  { title: '次数', dataIndex: 'count', key: 'count', width: 120 },
];

const errorColumns = [
  { title: '用户', dataIndex: 'username', key: 'username', width: 120 },
  { title: '路由', dataIndex: 'router', key: 'router', width: 220 },
  { title: '业务', dataIndex: 'name', key: 'name', width: 160 },
  { title: '变更内容', dataIndex: 'remark', key: 'remark', width: 320 },
  { title: '响应码', dataIndex: 'response_code', key: 'response_code', width: 100 },
  { title: '时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
];

const rankTableScroll = buildTableScrollX(rankColumns, { minWidth: 360 });
const timeTableScroll = buildTableScrollX(timeColumns, { minWidth: 360 });
const errorTableScroll = buildTableScrollX(errorColumns, { minWidth: 960 });

const businessRows = computed(() => {
  return Object.entries(analysisStatistics.value.by_business || {})
    .sort((left, right) => Number(right[1]) - Number(left[1]))
    .slice(0, 10)
    .map(([name, count]) => ({ name, count: Number(count) }));
});

const userRows = computed(() => {
  return Object.entries(analysisStatistics.value.by_user || {})
    .sort((left, right) => Number(right[1]) - Number(left[1]))
    .slice(0, 10)
    .map(([name, count]) => ({ name, count: Number(count) }));
});

const hourlyRows = computed(() => {
  return Array.from({ length: 24 }).map((_item, index) => ({
    name: `${index}:00`,
    count: Number(analysisReport.value.hourly_stats[String(index)] || 0),
  }));
});

const weeklyRows = computed(() => {
  const labels = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
  return labels.map((name, index) => ({
    name,
    count: Number(analysisReport.value.weekly_stats[String(index)] || 0),
  }));
});

const topBusinessLabel = computed(() => {
  return businessRows.value.slice(0, 3).map((item) => `${item.name}(${item.count})`).join('、');
});

const errorRate = computed(() => {
  if (analysisStatistics.value.total <= 0) {
    return 0;
  }

  return ((analysisStatistics.value.error_count || 0) / analysisStatistics.value.total) * 100;
});

const topBusinessConcentration = computed(() => {
  if (analysisStatistics.value.total <= 0 || businessRows.value.length === 0) {
    return 0;
  }

  return (Number(businessRows.value[0]?.count || 0) / analysisStatistics.value.total) * 100;
});

const alertCenterItems = computed(() => {
  const items: Array<{
    action: 'focus-error-code' | 'focus-hot-business' | 'refresh-monitor' | 'show-recent-errors';
    actionText: string;
    description: string;
    key: string;
    level: 'danger' | 'info' | 'warning';
    levelText: string;
    tagColor: string;
    title: string;
    value: string;
  }> = [];

  if (metrics.value.errors_last_5m > 0) {
    items.push({
      key: 'recent-errors',
      title: '近 5 分钟异常响应',
      description: '近实时窗口内非 200 响应持续出现，建议先回到数据列表聚焦异常请求。',
      value: `${metrics.value.errors_last_5m} 条`,
      action: 'focus-error-code',
      actionText: '定位异常日志',
      level: 'danger',
      levelText: '高风险',
      tagColor: 'error',
    });
  }

  if (errorRate.value >= 10) {
    items.push({
      key: 'error-rate',
      title: '错误占比偏高',
      description: '当前筛选范围内错误日志占比超过 10%，建议优先检查高频业务和异常接口。',
      value: `${errorRate.value.toFixed(1)}%`,
      action: 'show-recent-errors',
      actionText: '查看异常分布',
      level: 'warning',
      levelText: '关注',
      tagColor: 'warning',
    });
  }

  if (topBusinessConcentration.value >= 40 && businessRows.value.length > 0) {
    items.push({
      key: 'hot-business',
      title: '业务流量过于集中',
      description: `业务「${businessRows.value[0]?.name || '未知'}」占当前日志总量比重过高，建议检查是否有热点或异常重试。`,
      value: `${topBusinessConcentration.value.toFixed(1)}%`,
      action: 'focus-hot-business',
      actionText: '筛到该业务',
      level: 'info',
      levelText: '提示',
      tagColor: 'processing',
    });
  }

  const lastActivity = metrics.value.last_activity_at ? dayjs(metrics.value.last_activity_at) : null;
  if (!lastActivity || dayjs().diff(lastActivity, 'minute') >= 15) {
    items.push({
      key: 'stale-activity',
      title: '日志活跃度偏低',
      description: '近 15 分钟没有观察到新的日志活动，若当前系统本应有流量，建议确认采集链路。',
      value: lastActivity ? formatFromNow(lastActivity.format('YYYY-MM-DD HH:mm:ss')) : '暂无记录',
      action: 'refresh-monitor',
      actionText: '刷新监控',
      level: 'warning',
      levelText: '关注',
      tagColor: 'warning',
    });
  }

  return items;
});

const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([logActions({} as LogsActionRow)], { maxWidth: 180 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([logRecycleActions({} as LogsActionRow)], { maxWidth: 180 }));
const columns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '用户', dataIndex: 'username', key: 'username', width: 120 },
  { title: '请求方式', dataIndex: 'method', key: 'method', width: 100 },
  { title: '路由', dataIndex: 'router', key: 'router', width: 220 },
  { title: '操作名称', dataIndex: 'name', key: 'name', width: 140 },
  { title: '变更内容', dataIndex: 'remark', key: 'remark', width: 360 },
  { title: 'IP', dataIndex: 'ip', key: 'ip', width: 130 },
  { title: '归属地', dataIndex: 'ip_location', key: 'ip_location', width: 150 },
  { title: '响应码', dataIndex: 'response_code', key: 'response_code', width: 100 },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '用户', dataIndex: 'username', key: 'username', width: 120 },
  { title: '请求方式', dataIndex: 'method', key: 'method', width: 100 },
  { title: '路由', dataIndex: 'router', key: 'router' },
  { title: '操作名称', dataIndex: 'name', key: 'name', width: 140 },
  { title: '响应码', dataIndex: 'response_code', key: 'response_code', width: 100 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const changeFieldColumns = [
  { title: '记录', dataIndex: 'record', key: 'record', width: 180 },
  { title: '字段', dataIndex: 'field', key: 'field', width: 180 },
  { title: '原值', dataIndex: 'old_text', key: 'old_text', width: 220 },
  { title: '新值', dataIndex: 'new_text', key: 'new_text', width: 220 },
];

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'username', title: '用户', width: 120 },
  { key: 'method', title: '请求方式', width: 100 },
  { key: 'router', title: '路由', width: 240 },
  { key: 'name', title: '操作名称', width: 160 },
  { key: 'remark', title: '变更内容', width: 360, formatter: (record: LogsActionRow) => getChangeSummary(record) },
  { key: 'ip', title: 'IP', width: 130 },
  { key: 'ip_location', title: '归属地', width: 160 },
  { key: 'response_code', title: '响应码', width: 100 },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const tableScrollX = computed(() => buildTableScrollX(columns.value, { selectionWidth: 60 }));
const recycleTableScrollX = computed(() => buildTableScrollX(recycleColumns.value, {
  selectionWidth: 60,
}));
const changeFieldTableScroll = buildTableScrollX(changeFieldColumns, { minWidth: 800 });

function logActions(record: LogsActionRow) {
  return [
    { label: '查看', onClick: () => handleView(record) },
    { label: '删除', visible: canDeleteActionLogs.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function logRecycleActions(record: LogsActionRow) {
  return [
    { label: '恢复', visible: canRecoveryActionLogs.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteActionLogs.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const pagination = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const recyclePagination = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total: number) => `共 ${total} 条记录`,
});

const stats = ref({
  totalLogs: 0,
  infoLogs: 0,
  warningLogs: 0,
  errorLogs: 0,
});

const rowSelection = computed(() => {
  if (!canDeleteActionLogs.value) {
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
  if (!canRecoveryActionLogs.value && !canRealDeleteActionLogs.value) {
    return undefined;
  }

  return {
    selectedRowKeys: selectedRecycleRowKeys.value,
    onChange: (keys: (number | string)[]) => {
      selectedRecycleRowKeys.value = keys.map((key) => Number(key));
    },
  };
});

const resetSelection = () => {
  selectedRowKeys.value = [];
};

const buildQueryParams = () => {
  const [start, end] = (searchForm.created_at || []) as any[];
  return {
    page: pagination.current,
    pageSize: pagination.pageSize,
    keyword: searchForm.keyword,
    username: searchForm.username,
    name: searchForm.name,
    router: searchForm.router,
    ip: searchForm.ip,
    response_code: searchForm.response_code,
    startDate: start ? dayjs(start).format('YYYY-MM-DD') : undefined,
    endDate: end ? dayjs(end).format('YYYY-MM-DD') : undefined,
  };
};

async function loadMonitorMetrics() {
  try {
    const data = await logsActionApiService.getActionLogRealtimeMetrics(buildQueryParams());
    metrics.value = data;
  } catch (e) {
    console.error(e);
  }
}

async function loadAnalysisReport() {
  if (!canAccessActionLogAnalysis.value) {
    return;
  }

  try {
    const params = buildQueryParams();
    const [statisticsResp, analysisResp] = await Promise.all([
      logsActionApiService.getActionLogStatistics(params),
      logsActionApiService.getActionLogAnalysis(params),
    ]);

    analysisStatistics.value = statisticsResp;
    analysisReport.value = analysisResp;
  } catch (error) {
    console.error('加载日志分析报告失败:', error);
  }
}

function applyStreamHighlight(items: LogsActionRow[]) {
  if (items.length === 0) {
    streamMaxId.value = 0;
    return;
  }
  const maxId = Math.max(...items.map((i) => Number(i.id) || 0));
  if (streamMaxId.value > 0) {
    const next = new Set<number>();
    for (const row of items) {
      const id = Number(row.id);
      if (id > streamMaxId.value) next.add(id);
    }
    if (next.size > 0) {
      highlightIds.value = next;
      if (highlightTimer) clearTimeout(highlightTimer);
      highlightTimer = setTimeout(() => {
        highlightIds.value = new Set();
        highlightTimer = null;
      }, 2800);
    }
  }
  streamMaxId.value = maxId;
}

const loadLogList = async (options?: { isPoll?: boolean }) => {
  const isPoll = options?.isPoll === true;
  if (loading.value) return;
  try {
    loading.value = true;
    if (!isPoll) {
      resetSelection();
    }

    const [response] = await Promise.all([
      logsActionApiService.getActionLogList(buildQueryParams()),
      loadMonitorMetrics(),
    ]);

    if (response?.items) {
      const items = response.items as LogsActionRow[];
      if (isPoll && autoRefresh.value) {
        applyStreamHighlight(items);
      } else if (!isPoll) {
        const maxId = items.length ? Math.max(...items.map((i) => Number(i.id) || 0)) : 0;
        streamMaxId.value = maxId;
        highlightIds.value = new Set();
      }
      logData.value = items;
      pagination.total = response.pageInfo?.total || 0;

      const statistics = response.extra?.statistics;
      stats.value.totalLogs = statistics?.total || 0;
      stats.value.infoLogs = statistics?.success_count || 0;
      stats.value.errorLogs = statistics?.error_count || 0;
      stats.value.warningLogs = statistics?.warning_count || 0;
    } else {
      logData.value = [];
      pagination.total = 0;
    }
    lastRefreshedAt.value = dayjs().format('YYYY-MM-DD HH:mm:ss');
  } catch (error) {
    console.error('加载日志列表失败:', error);
    message.error('获取日志列表失败');
  } finally {
    loading.value = false;
  }
};

const loadRecycleList = async () => {
  if (!canRecoveryActionLogs.value && !canRealDeleteActionLogs.value) {
    recycleData.value = [];
    recyclePagination.total = 0;
    return;
  }

  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await logsActionApiService.getActionLogRecycleList({
      ...buildQueryParams(),
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
    });

    recycleData.value = (response?.items || []) as LogsActionRow[];
    recyclePagination.total = response?.pageInfo?.total || 0;
  } catch (error) {
    console.error('加载日志回收站失败:', error);
    message.error('获取日志回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
};

function getRowClassName(record: LogsActionRow) {
  return highlightIds.value.has(Number(record.id)) ? 'logs-row-new' : '';
}

const handleSearch = () => {
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadLogList(), loadRecycleList(), loadAnalysisReport()]);
};

const handleReset = () => {
  searchForm.keyword = '';
  searchForm.username = '';
  searchForm.name = '';
  searchForm.router = '';
  searchForm.ip = '';
  searchForm.response_code = undefined;
  searchForm.created_at = undefined;
  pagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadLogList(), loadRecycleList(), loadAnalysisReport()]);
};

const handleTableChange = (pag: any) => {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  loadLogList();
};

const handleRecycleTableChange = (pag: any) => {
  recyclePagination.current = pag.current;
  recyclePagination.pageSize = pag.pageSize;
  loadRecycleList();
};

const handleAlertAction = async (
  action: 'focus-error-code' | 'focus-hot-business' | 'refresh-monitor' | 'show-recent-errors',
) => {
  switch (action) {
    case 'focus-error-code': {
      searchForm.response_code = '500';
      activeTab.value = 'data';
      pagination.current = 1;
      await Promise.all([loadLogList(), loadAnalysisReport()]);
      return;
    }
    case 'focus-hot-business': {
      const businessName = businessRows.value[0]?.name;
      if (businessName) {
        searchForm.name = businessName;
        activeTab.value = 'data';
        pagination.current = 1;
        await Promise.all([loadLogList(), loadAnalysisReport()]);
      }
      return;
    }
    case 'refresh-monitor': {
      await Promise.all([loadMonitorMetrics(), loadAnalysisReport(), loadLogList()]);
      return;
    }
    case 'show-recent-errors': {
      activeTab.value = 'extra';
      return;
    }
  }
};

function formatFromNow(t: string) {
  return dayjs(t).fromNow();
}

const safeJson = (value: any) => {
  if (!value) return '-';
  try {
    return JSON.stringify(typeof value === 'string' ? JSON.parse(value) : value, null, 2);
  } catch {
    return String(value);
  }
};

const formatIpLocation = (ip?: string, location?: string) => {
  if (!location) return ip || '-';
  return ip ? `${ip}（${location}）` : location;
};

const getChangeSummary = (record?: LogsActionRow | null) => {
  if (!record) return '-';
  return record.remark || '-';
};

const parseChangeValues = (value: LogsActionChangeRow['change_values']): LogsActionChangeField[] => {
  if (!value) return [];
  if (Array.isArray(value)) return value;
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? (parsed as LogsActionChangeField[]) : [];
  } catch {
    return [];
  }
};

const currentChangeSummary = computed(() => {
  const changeRemarks = currentChanges.value
    .map((change) => (change.change_remark || '').trim())
    .filter(Boolean);
  return changeRemarks.length > 0 ? changeRemarks.join('；') : getChangeSummary(currentActionLog.value);
});
const changeFieldRows = computed(() => {
  return currentChanges.value.flatMap((change, changeIndex) => {
    const recordLabel = change.record_label || change.record_id || '-';
    const record = `${change.model_name || change.model || '记录'}(${recordLabel})`;

    return parseChangeValues(change.change_values).map((field, fieldIndex) => ({
      key: `${change.id || changeIndex}-${field.field}-${fieldIndex}`,
      record,
      field: `${field.label || field.field}(${field.field})`,
      old_text: field.old_text ?? '-',
      new_text: field.new_text ?? '-',
    }));
  });
});
const requestPayload = computed(() => safeJson(currentActionLog.value?.request_data));
const responsePayload = computed(() => safeJson(currentActionLog.value?.response_data));
const changePayload = computed(() => safeJson(currentChanges.value));

const handleView = async (record: LogsActionRow) => {
  currentActionLog.value = record;
  currentChanges.value = [];
  detailOpen.value = true;
  try {
    const [detail, changes] = await Promise.all([
      logsActionApiService.getActionLogDetail(record.id),
      logsActionApiService.getActionLogChanges(record.id),
    ]);
    currentActionLog.value = detail || record;
    currentChanges.value = Array.isArray(changes) ? (changes as LogsActionChangeRow[]) : [];
  } catch (error) {
    console.error('加载日志详情失败:', error);
    message.error('获取日志详情失败');
  }
};

const handleDelete = async (record: LogsActionRow) => {
  Modal.confirm({
    title: '确认删除',
    content: '确定要删除该条日志记录吗？',
    okText: '确认',
    cancelText: '取消',
    onOk: async () => {
      await logsActionApiService.deleteActionLog(record.id);
      message.success('删除成功');
      Promise.all([loadLogList(), loadRecycleList()]);
    },
  });
};

const handleBatchDelete = () => {
  if (selectedRowKeys.value.length === 0) {
    message.warning('请选择要删除的日志');
    return;
  }

  Modal.confirm({
    title: '确认批量删除',
    content: `确定要删除选中的 ${selectedRowKeys.value.length} 条日志记录吗？`,
    okText: '确认删除',
    cancelText: '取消',
    okType: 'danger',
    onOk: async () => {
      await logsActionApiService.batchDeleteActionLogs(selectedRowKeys.value);
      message.success(`成功删除 ${selectedRowKeys.value.length} 条日志`);
      selectedRowKeys.value = [];
      Promise.all([loadLogList(), loadRecycleList()]);
    },
  });
};

const handleExport = async () => {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<LogsActionRow>({
      columns: exportColumns,
      fetchPage: (page, pageSize) => logsActionApiService.getActionLogList({
        ...buildQueryParams(),
        page,
        pageSize,
      }) as any,
      filename: `logs_${new Date().toISOString().slice(0, 10)}.xlsx`,
      pageSize: 100,
      sheetName: '操作日志',
    });
  } finally {
    exporting.value = false;
  }
};

const handleClear = async () => {
  Modal.confirm({
    title: '确认清空',
    content: '确定清空全部日志吗？此操作不可恢复。',
    okText: '确认',
    cancelText: '取消',
    okType: 'danger',
    onOk: async () => {
      await logsActionApiService.clearActionLogs();
      message.success('清空成功');
      Promise.all([loadLogList(), loadRecycleList()]);
    },
  });
};

const handleRecovery = (record: LogsActionRow) => {
  Modal.confirm({
    title: '确认恢复',
    content: '确定要恢复该条日志记录吗？',
    onOk: async () => {
      await logsActionApiService.recoveryActionLogs([record.id]);
      message.success('恢复成功');
      Promise.all([loadLogList(), loadRecycleList()]);
    },
  });
};

const handleRealDelete = (record: LogsActionRow) => {
  Modal.confirm({
    title: '确认彻底删除',
    content: '确定要彻底删除该条日志记录吗？此操作不可恢复。',
    okType: 'danger',
    onOk: async () => {
      await logsActionApiService.realDeleteActionLogs([record.id]);
      message.success('彻底删除成功');
      loadRecycleList();
    },
  });
};

const handleBatchRecovery = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要恢复的日志');
    return;
  }

  Modal.confirm({
    title: '确认批量恢复',
    content: `确定要恢复选中的 ${selectedRecycleRowKeys.value.length} 条日志吗？`,
    onOk: async () => {
      await logsActionApiService.recoveryActionLogs(selectedRecycleRowKeys.value);
      message.success(`成功恢复 ${selectedRecycleRowKeys.value.length} 条日志`);
      selectedRecycleRowKeys.value = [];
      Promise.all([loadLogList(), loadRecycleList()]);
    },
  });
};

const handleBatchRealDelete = () => {
  if (selectedRecycleRowKeys.value.length === 0) {
    message.warning('请选择要彻底删除的日志');
    return;
  }

  Modal.confirm({
    title: '确认批量彻底删除',
    content: `确定要彻底删除选中的 ${selectedRecycleRowKeys.value.length} 条日志吗？此操作不可恢复。`,
    okType: 'danger',
    onOk: async () => {
      await logsActionApiService.realDeleteActionLogs(selectedRecycleRowKeys.value);
      message.success(`成功彻底删除 ${selectedRecycleRowKeys.value.length} 条日志`);
      selectedRecycleRowKeys.value = [];
      loadRecycleList();
    },
  });
};

function clearPoll() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

function setupPoll() {
  clearPoll();
  if (!autoRefresh.value) return;
  const ms = Math.max(5, refreshIntervalSec.value) * 1000;
  pollTimer = setInterval(() => {
    if (typeof document !== 'undefined' && document.hidden) return;
    void loadLogList({ isPoll: true });
  }, ms);
}

watch([autoRefresh, refreshIntervalSec], () => {
  setupPoll();
});

onMounted(() => {
  Promise.all([loadLogList(), loadRecycleList(), loadAnalysisReport()]);
  setupPoll();
});

onUnmounted(() => {
  clearPoll();
  if (highlightTimer) clearTimeout(highlightTimer);
});
</script>

<style scoped>
.logs-search-prefix-icon {
  color: hsl(var(--muted-foreground) / 0.72);
}

.logs-search-text-muted {
  color: hsl(var(--muted-foreground));
}

.logs-search-text-subtle {
  margin-top: 2px;
  font-size: 12px;
  line-height: 1.5;
  color: hsl(var(--muted-foreground) / 0.72);
}

.logs-live-on {
  background-color: hsl(var(--success));
}

.logs-live-off {
  background-color: hsl(var(--border));
}

.logs-search-block-title {
  margin-bottom: 12px;
  font-size: 13px;
  font-weight: 600;
  color: hsl(var(--muted-foreground));
}

.logs-filter-block {
  margin-bottom: 18px;
}

.logs-filter-secondary-row {
  /* 两段式筛选表单需要固定行距，避免日期范围控件与下一行输入框视觉贴合。 */
  margin-top: 16px;
}

.logs-search-divider {
  margin: 20px 0 !important;
}

.logs-search-card :deep(.ant-divider-horizontal) {
  border-top-color: hsl(var(--border) / 0.65);
}

.logs-refresh-panel {
  padding: 16px 20px;
  border-radius: 8px;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--muted));
}

.logs-search-card :deep(.ant-picker),
.logs-search-card :deep(.ant-select) {
  width: 100%;
}

.logs-search-card :deep(.logs-refresh-interval-select.ant-select) {
  width: auto;
  min-width: 128px;
}

.logs-analysis-remark {
  color: hsl(var(--foreground));
}

.logs-change-cell {
  min-width: 280px;
  max-width: 520px;
}

.logs-change-summary {
  margin-bottom: 12px;
  line-height: 1.7;
  color: hsl(var(--foreground));
  word-break: break-word;
}

.logs-live-dot {
  animation: logs-dot-breathe 1.6s ease-in-out infinite;
}

@keyframes logs-dot-breathe {
  0%,
  100% {
    opacity: 1;
    box-shadow: 0 0 0 0 color-mix(in srgb, hsl(var(--success)) 45%, transparent);
  }
  50% {
    opacity: 0.85;
    box-shadow: 0 0 0 6px transparent;
  }
}

:deep(.logs-row-new) > td {
  animation: logs-row-flash 2.4s ease-out 1;
}

@keyframes logs-row-flash {
  0% {
    background-color: color-mix(in srgb, hsl(var(--warning)) 32%, transparent);
  }
  100% {
    background-color: transparent;
  }
}

</style>
