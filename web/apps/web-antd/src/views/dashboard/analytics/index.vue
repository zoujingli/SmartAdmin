<script lang="ts" setup>
import type { TabOption } from '@vben/types';

import { onMounted, ref } from 'vue';

import { useAccess } from '@vben/access';
import {
  AnalysisChartCard,
  AnalysisChartsTabs,
  CrudStatCards,
} from '@vben/common-ui';

import { message } from 'ant-design-vue';

import { dataApiService, logsActionApiService, tenantApiService } from '#/api';

import AnalyticsTrends from './analytics-trends.vue';
import AnalyticsVisitsData from './analytics-visits-data.vue';
import AnalyticsVisitsSales from './analytics-visits-sales.vue';
import AnalyticsVisitsSource from './analytics-visits-source.vue';
import AnalyticsVisits from './analytics-visits.vue';

const { hasAccessByCodes } = useAccess();
const summaryCards = ref<Array<{ desc: string; icon: string; label: string; value: string }>>([]);
const trendLabels = ref<string[]>([]);
const trendSeries = ref<Array<{ name: string; data: number[]; color?: string }>>([]);
const weeklyLabels = ref<string[]>([]);
const weeklyData = ref<number[]>([]);
const responseCodeItems = ref<Array<{ name: string; value: number }>>([]);
const businessItems = ref<Array<{ name: string; value: number }>>([]);
const radarIndicators = ref<Array<{ name: string; max: number }>>([]);
const radarSeries = ref<Array<{ name: string; value: number[]; color?: string }>>([]);
const RADAR_SPLIT_NUMBER = 6;
const canAccessSystemData = () => hasAccessByCodes(['system.data.index']);
const canAccessActionLogs = () => hasAccessByCodes(['system.logs.action.index']);
const canAccessTenants = () => hasAccessByCodes(['system.tenant.index']);

const chartTabs: TabOption[] = [
  {
    label: '小时趋势',
    value: 'trends',
  },
  {
    label: '星期分布',
    value: 'visits',
  },
];

function toHourLabels() {
  return Array.from({ length: 24 }).map((_item, index) => `${index}:00`);
}

function toHourValues(hourlyStats: Record<string, number>) {
  return Array.from({ length: 24 }).map((_item, index) => Number(hourlyStats[String(index)] || 0));
}

function toWeeklyData(weeklyStats: Record<string, number>) {
  const weekLabels = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
  weeklyLabels.value = weekLabels;
  weeklyData.value = weekLabels.map((_label, index) => Number(weeklyStats[String(index)] || 0));
}

function toPieItems(data: Record<string, number>, suffix = '') {
  return Object.entries(data)
    .sort((left, right) => Number(right[1]) - Number(left[1]))
    .slice(0, 6)
    .map(([name, value]) => ({
      name: suffix ? `${name}${suffix}` : name,
      value: Number(value),
    }));
}

function calcRadarMax(values: number[]) {
  const maxValue = Math.max(...values, 1);
  const roughStep = Math.ceil((maxValue * 1.2) / RADAR_SPLIT_NUMBER);
  const magnitude = 10 ** Math.floor(Math.log10(roughStep));
  const normalizedStep = roughStep / magnitude;
  // 雷达图按固定分段展示，最大值使用 1/2/5/10 阶梯，避免 ECharts 生成不可读刻度告警。
  const niceStep = normalizedStep <= 1 ? 1 : normalizedStep <= 2 ? 2 : normalizedStep <= 5 ? 5 : 10;

  return niceStep * magnitude * RADAR_SPLIT_NUMBER;
}

function getSettledValue<T>(result: PromiseSettledResult<T>, fallback: T): T {
  return result.status === 'fulfilled' ? result.value : fallback;
}

async function loadAnalytics() {
  const [statisticsResult, capabilitiesResult, tenantStatsResult, logStatsResult, logAnalysisResult] =
    await Promise.allSettled([
      canAccessSystemData() ? dataApiService.getStatistics() : Promise.resolve(null),
      canAccessSystemData() ? dataApiService.getCapabilities() : Promise.resolve(null),
      canAccessTenants() ? tenantApiService.getStatistics() : Promise.resolve(null),
      canAccessActionLogs() ? logsActionApiService.getActionLogStatistics() : Promise.resolve(null),
      canAccessActionLogs() ? logsActionApiService.getActionLogAnalysis() : Promise.resolve(null),
    ]);

  const statistics = getSettledValue(statisticsResult, null);
  const capabilities = getSettledValue(capabilitiesResult, null);
  const tenantStats = getSettledValue(tenantStatsResult, null);
  const logStats = getSettledValue(logStatsResult, null);
  const logAnalysis = getSettledValue(logAnalysisResult, null);

  summaryCards.value = [
    statistics
      ? {
          desc: `总用户 ${statistics.user_count} 人`,
          icon: 'i-lucide-users',
          label: '在线用户',
          value: String(statistics.online_count),
        }
      : null,
    tenantStats
      ? {
          desc: `租户总数 ${tenantStats.total} 个`,
          icon: 'i-lucide-building-2',
          label: '活跃租户',
          value: String(tenantStats.active),
        }
      : null,
    statistics
      ? {
          desc: `累计日志 ${statistics.log_count} 条`,
          icon: 'i-lucide-file-text',
          label: '今日日志',
          value: String(statistics.today_logs),
        }
      : null,
    statistics && capabilities
      ? {
          desc: `公共能力 ${capabilities.summary.common_capability_count} 项`,
          icon: 'i-lucide-activity',
          label: '在线会话',
          value: String(statistics.online_session_count),
        }
      : null,
  ].filter(Boolean) as Array<{ desc: string; icon: string; label: string; value: string }>;

  trendLabels.value = toHourLabels();
  trendSeries.value = logAnalysis
    ? [
        {
          name: '日志趋势',
          color: '#5ab1ef',
          data: toHourValues(logAnalysis.hourly_stats || {}),
        },
        {
          name: '错误日志',
          color: '#ef5350',
          data: toHourValues(
            (logAnalysis.error_logs || []).reduce((carry: Record<string, number>, item: { created_at?: string }) => {
              const hour = String(new Date(item.created_at || '').getHours());
              carry[hour] = Number(carry[hour] || 0) + 1;
              return carry;
            }, {}),
          ),
        },
      ]
    : [];

  toWeeklyData(logAnalysis?.weekly_stats || {});
  responseCodeItems.value = toPieItems(logStats?.by_response_code || {}, ' 响应');
  businessItems.value = toPieItems(logStats?.by_business || {});

  const radarValues = [
    statistics?.user_count || 0,
    tenantStats?.total || 0,
    statistics?.menu_count || 0,
    statistics?.node_count || 0,
    statistics?.log_count || 0,
    capabilities?.summary.common_capability_count || 0,
  ];

  radarIndicators.value = [
    { name: '用户', max: calcRadarMax(radarValues) },
    { name: '租户', max: calcRadarMax(radarValues) },
    { name: '菜单', max: calcRadarMax(radarValues) },
    { name: '节点', max: calcRadarMax(radarValues) },
    { name: '日志', max: calcRadarMax(radarValues) },
    { name: '能力', max: calcRadarMax(radarValues) },
  ];
  radarSeries.value = [
    {
      name: '系统规模',
      color: '#5ab1ef',
      value: radarValues,
    },
  ];

  // 分析页由多个接口并行组成，单项失败不阻断页面，但提示必须指明具体模块便于排查。
  const failures = [
    { label: '统计概览', result: statisticsResult },
    { label: '模块能力', result: capabilitiesResult },
    { label: '租户统计', result: tenantStatsResult },
    { label: '日志统计', result: logStatsResult },
    { label: '日志分析', result: logAnalysisResult },
  ].filter((item) => item.result.status === 'rejected');

  if (failures.length > 0) {
    console.error(
      '分析数据加载失败:',
      failures.map(({ label, result }) => ({ label, reason: (result as PromiseRejectedResult).reason })),
    );
    message.warning(`${failures.map((item) => item.label).join('、')}加载失败`);
  }
}

onMounted(() => {
  loadAnalytics();
});
</script>

<template>
  <div class="p-5">
    <CrudStatCards :items="summaryCards" />
    <AnalysisChartsTabs :tabs="chartTabs" class="mt-5">
      <template #trends>
        <AnalyticsTrends :labels="trendLabels" :series="trendSeries" />
      </template>
      <template #visits>
        <AnalyticsVisits :data="weeklyData" :labels="weeklyLabels" series-name="周日志量" />
      </template>
    </AnalysisChartsTabs>

    <div class="mt-5 w-full md:flex">
      <AnalysisChartCard class="mt-5 md:mr-4 md:mt-0 md:w-1/3" title="系统规模雷达">
        <AnalyticsVisitsData :indicators="radarIndicators" :series="radarSeries" />
      </AnalysisChartCard>
      <AnalysisChartCard class="mt-5 md:mr-4 md:mt-0 md:w-1/3" title="响应码分布">
        <AnalyticsVisitsSource :items="responseCodeItems" />
      </AnalysisChartCard>
      <AnalysisChartCard class="mt-5 md:mt-0 md:w-1/3" title="业务热度">
        <AnalyticsVisitsSales :items="businessItems" />
      </AnalysisChartCard>
    </div>
  </div>
</template>

<style scoped>
</style>
