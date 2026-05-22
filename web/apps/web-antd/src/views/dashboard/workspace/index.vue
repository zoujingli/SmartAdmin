<script lang="ts" setup>
import type {
  WorkbenchProjectItem,
  WorkbenchQuickNavItem,
  WorkbenchTodoItem,
  WorkbenchTrendItem,
} from '@vben/common-ui';

import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { useAccess } from '@vben/access';
import {
  AnalysisChartCard,
  CrudStatCards,
  WorkbenchHeader,
  WorkbenchProject,
  WorkbenchQuickNav,
  WorkbenchTodo,
  WorkbenchTrends,
} from '@vben/common-ui';
import { preferences } from '@vben/preferences';
import { useUserStore } from '@vben/stores';
import { openWindow } from '@vben/utils';

import { message } from 'ant-design-vue';

import { dataApiService, logsActionApiService, tenantApiService } from '#/api';

import AnalyticsVisitsSource from '../analytics/analytics-visits-source.vue';

const userStore = useUserStore();
const router = useRouter();
const { hasAccessByCodes } = useAccess();

const moduleColors = ['#1fdaca', '#3fb27f', '#e18525', '#bf0c2c', '#00d8ff', '#EBD94E'];
type QuickNavConfig = WorkbenchQuickNavItem & { code?: string };

const projectItems = ref<WorkbenchProjectItem[]>([]);
const todoItems = ref<WorkbenchTodoItem[]>([]);
const trendItems = ref<WorkbenchTrendItem[]>([]);
const visitSourceItems = ref<Array<{ name: string; value: number }>>([]);
const summary = ref({
  user_count: 0,
  log_count: 0,
  online_count: 0,
  online_session_count: 0,
  today_logs: 0,
  menu_count: 0,
  node_count: 0,
  dept_count: 0,
  post_count: 0,
});

const canAccessSystemData = computed(() => hasAccessByCodes(['system.data.index']));
const canAccessActionLogs = computed(() => hasAccessByCodes(['system.logs.action.index']));
const canAccessTenants = computed(() => hasAccessByCodes(['system.tenant.index']));

const quickNavSource: QuickNavConfig[] = [
  { code: 'dashboard.workspace', color: '#1fdaca', icon: 'ion:home-outline', title: '工作台', url: '/dashboard/workspace' },
  { code: 'dashboard.analytics', color: '#bf0c2c', icon: 'ion:grid-outline', title: '分析页', url: '/dashboard/analytics' },
  { code: 'system.user.index', color: '#e18525', icon: 'ion:people-outline', title: '用户管理', url: '/system/user' },
  { code: 'system.role.index', color: '#3fb27f', icon: 'ion:settings-outline', title: '角色管理', url: '/system/role' },
  { code: 'system.menu.index', color: '#4daf1bc9', icon: 'ion:key-outline', title: '菜单管理', url: '/system/menu' },
  { code: 'system.tenant.index', color: '#00d8ff', icon: 'ion:business-outline', title: '租户管理', url: '/system/tenant' },
];
const quickNavItems = computed<WorkbenchQuickNavItem[]>(() => {
  return quickNavSource.filter((item) => canAccessCode(item.code));
});

const greetingText = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 11) {
    return '早安';
  }
  if (hour >= 11 && hour < 14) {
    return '中午好';
  }
  if (hour >= 14 && hour < 18) {
    return '下午好';
  }

  return '晚上好';
});

const headerDescription = computed(() => {
  const hasMultiSession = summary.value.online_session_count > summary.value.online_count;
  const sessionHint = hasMultiSession ? '（含多端或多浏览器会话）' : '';

  return `当前在线用户 ${summary.value.online_count} 人，活跃会话 ${summary.value.online_session_count} 个${sessionHint}，今日日志 ${summary.value.today_logs} 条。`;
});
const summaryCards = computed(() => [
  {
    desc: `总用户 ${summary.value.user_count} 人`,
    icon: 'i-lucide-users',
    label: '在线用户',
    value: String(summary.value.online_count),
  },
  {
    desc: '当前多端与多浏览器活跃会话总量。',
    icon: 'i-lucide-activity',
    label: '活跃会话',
    value: String(summary.value.online_session_count),
  },
  {
    desc: `累计日志 ${summary.value.log_count} 条`,
    icon: 'i-lucide-file-text',
    label: '今日日志',
    value: String(summary.value.today_logs),
  },
  {
    desc: `菜单 ${summary.value.menu_count} / 部门 ${summary.value.dept_count} / 岗位 ${summary.value.post_count}`,
    icon: 'i-lucide-waypoints',
    label: '组织与菜单',
    value: String(summary.value.menu_count),
  },
]);

function canAccessCode(code?: string) {
  return !code || hasAccessByCodes([code]);
}

function navTo(nav: WorkbenchProjectItem | WorkbenchQuickNavItem) {
  const raw = nav.url?.trim() ?? '';
  if (!raw) {
    return;
  }
  if (raw.startsWith('http')) {
    openWindow(raw);
    return;
  }
  const path = raw.startsWith('/') ? raw : `/${raw}`;
  router.push(path).catch((error) => {
    console.error('Navigation failed:', error);
  });
}

function buildProjectItems(modules: Array<any>) {
  return modules.slice(0, 6).map((module, index): WorkbenchProjectItem => {
    const route = String(module.path ?? '').trim();
    const displayPath =
      !route ? '未配置路径' : route.startsWith('/') || route.startsWith('http') ? route : `/${route}`;
    const url =
      !route || route.startsWith('http')
        ? route
        : route.startsWith('/')
          ? route
          : `/${route}`;

    return {
      color: moduleColors[index % moduleColors.length],
      content: module.summary || '系统模块能力页。',
      group: displayPath,
      icon: module.icon || 'lucide:blocks',
      title: module.name,
      url,
    };
  });
}

function buildTrendItems(
  recentUsers: Array<{ username: string; nickname: string; created_at: string }>,
  onlineUsers: Array<{ username: string; nickname: string; last_active_at: string }>,
): WorkbenchTrendItem[] {
  const userTrends = recentUsers.map((item, index) => ({
    avatar: `svg:avatar-${(index % 4) + 1}`,
    content: `新用户 <a>${item.nickname || item.username}</a> 已进入系统。`,
    date: item.created_at,
    title: item.username,
  }));

  const onlineTrends = onlineUsers.map((item, index) => ({
    avatar: `svg:avatar-${((index + userTrends.length) % 4) + 1}`,
    content: `在线会话用户 <a>${item.nickname || item.username}</a> 最近活跃。`,
    date: item.last_active_at,
    title: item.username,
  }));

  return [...userTrends, ...onlineTrends].slice(0, 8);
}

function getSettledValue<T>(result: PromiseSettledResult<T>, fallback: T): T {
  return result.status === 'fulfilled' ? result.value : fallback;
}

async function loadWorkspace() {
  const [statisticsResult, capabilitiesResult, logStatsResult, tenantStatsResult, todosResult] = await Promise.allSettled([
    canAccessSystemData.value ? dataApiService.getStatistics() : Promise.resolve(null),
    canAccessSystemData.value ? dataApiService.getCapabilities() : Promise.resolve(null),
    canAccessActionLogs.value ? logsActionApiService.getActionLogStatistics() : Promise.resolve(null),
    canAccessTenants.value ? tenantApiService.getStatistics() : Promise.resolve(null),
    canAccessSystemData.value ? dataApiService.getWorkbenchTodos() : Promise.resolve([]),
  ]);

  const statistics = getSettledValue(statisticsResult, null);
  const capabilities = getSettledValue(capabilitiesResult, null);
  const logStats = getSettledValue(logStatsResult, null);
  const todos = getSettledValue(todosResult, [] as WorkbenchTodoItem[]);

  if (statistics) {
    summary.value = {
      user_count: statistics.user_count,
      log_count: statistics.log_count,
      online_count: statistics.online_count,
      online_session_count: statistics.online_session_count,
      today_logs: statistics.today_logs,
      menu_count: statistics.menu_count,
      node_count: statistics.node_count,
      dept_count: statistics.dept_count,
      post_count: statistics.post_count,
    };
  }

  projectItems.value = buildProjectItems(capabilities?.modules || []);
  todoItems.value = todos;
  trendItems.value = buildTrendItems(statistics?.recent_users || [], capabilities?.online_users || []);
  visitSourceItems.value = Object.entries(logStats?.by_response_code || {}).map(([name, value]) => ({
    name: `${name} 响应`,
    value: Number(value),
  }));

  const failures = [
    { label: '统计概览', result: statisticsResult },
    { label: '模块能力', result: capabilitiesResult },
    { label: '日志统计', result: logStatsResult },
    { label: '租户统计', result: tenantStatsResult },
    { label: '待办中心', result: todosResult },
  ].filter((item) => item.result.status === 'rejected');

  if (failures.length > 0) {
    console.error(
      '部分工作台数据加载失败:',
      failures.map(({ label, result }) => ({ label, reason: (result as PromiseRejectedResult).reason })),
    );
    message.warning('部分工作台数据加载失败');
  }
}

onMounted(() => {
  loadWorkspace();
});
</script>

<template>
  <div class="p-5">
    <WorkbenchHeader :avatar="userStore.userInfo?.avatar || preferences.app.defaultAvatar">
      <template #title>
        {{ greetingText }}，{{ userStore.userInfo?.realName || userStore.userInfo?.username }}。
      </template>
      <template #description>{{ headerDescription }}</template>
    </WorkbenchHeader>

    <CrudStatCards class="mt-5" :items="summaryCards" />

    <div class="mt-5 flex flex-col lg:flex-row">
      <div class="mr-4 w-full lg:w-3/5">
        <WorkbenchProject :items="projectItems" title="模块矩阵" @click="navTo" />
        <WorkbenchTrends :items="trendItems" class="mt-5" title="最新动态" />
      </div>
      <div class="w-full lg:w-2/5">
        <WorkbenchQuickNav
          :items="quickNavItems"
          class="mt-5 lg:mt-0"
          title="快捷导航"
          @click="navTo"
        />
        <WorkbenchTodo :items="todoItems" class="mt-5" title="待处理事项" />
        <AnalysisChartCard class="mt-5" title="响应分布">
          <AnalyticsVisitsSource :items="visitSourceItems" />
        </AnalysisChartCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>
