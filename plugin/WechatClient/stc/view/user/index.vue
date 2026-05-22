<template>
  <Page title="粉丝管理">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load">
          <span class="i-lucide-refresh-cw" />刷新
        </Button>
        <Button v-if="canSyncUsers" :disabled="!accountId || syncing" :loading="syncing" @click="syncUsers">
          <span class="i-lucide-refresh-cw" />同步粉丝
        </Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="OpenID / 昵称" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="接口账号"><InputNumber v-model:value="accountId" :min="1" class="w-full" placeholder="账号 ID" /></SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="4">
            <SearchField label="关注状态">
              <Select v-model:value="subscribeFilter" allow-clear class="w-full" placeholder="请选择">
                <SelectOption :value="1">已关注</SelectOption>
                <SelectOption :value="0">未关注</SelectOption>
              </Select>
            </SearchField>
          </Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
            <Space wrap>
              <Button type="primary" :loading="loading" @click="handleSearch">
                <span class="i-lucide-search" />搜索
              </Button>
              <Button :disabled="loading" @click="handleReset">
                <span class="i-lucide-refresh-cw" />重置
              </Button>
            </Space>
          </Col>
        </Row>
        <CrudFilterSummary
          :items="activeFilterItems"
          empty-text="当前显示全部粉丝记录，可按账号 ID、OpenID、昵称或关注状态筛选。"
        />
      </Card>

      <Card>
        <CrudTableHeader
          title="粉丝台账"
          description="展示公众号粉丝、本地标签和同步状态，粉丝同步需先指定接口账号 ID。"
          :count-text="`${pagination.total} 条记录`"
        />
        <Table
          :columns="columns"
          :data-source="items"
          :loading="loading"
          :locale="buildCrudTableLocale('暂无粉丝记录')"
          :pagination="pagination"
          :scroll="tableScroll"
          row-key="id"
          @change="onTableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'openid'">
              <Tooltip :title="record.openid" placement="topLeft">
                <div class="truncate">{{ record.openid || '-' }}</div>
              </Tooltip>
            </template>
            <template v-else-if="column.key === 'nickname'">
              <Tooltip :title="record.nickname" placement="topLeft">
                <div class="truncate">{{ record.nickname || '-' }}</div>
              </Tooltip>
            </template>
            <template v-else-if="column.key === 'unionid'">
              <Tooltip :title="record.unionid" placement="topLeft">
                <div class="truncate">{{ record.unionid || '-' }}</div>
              </Tooltip>
            </template>
            <template v-else-if="column.key === 'subscribe'">
              <Tag :color="Number(record.subscribe) === 1 ? 'success' : 'default'">
                {{ Number(record.subscribe) === 1 ? '已关注' : '未关注' }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'tagids'">
              <Space v-if="visibleTagids(record).length > 0" wrap size="small">
                <Tag v-for="tagId in visibleTagids(record)" :key="tagId" color="blue">{{ tagId }}</Tag>
              </Space>
              <span v-else>-</span>
            </template>
          </template>
        </Table>
      </Card>
    </Card>
  </Page>
</template>

<script setup lang="ts">
import type { CrudFilterSummaryItem } from '@vben/common-ui';

import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import {
  buildCrudTableLocale,
  CrudFilterSummary,
  CrudStatCards,
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import {
  Button,
  Card,
  Col,
  Input,
  InputNumber,
  message,
  Row,
  Select,
  SelectOption,
  Space,
  Table,
  Tag,
  Tooltip,
} from 'ant-design-vue';

import SearchField from '#/components/crud-search-field.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX } from '#/utils/table';

const { hasAccessByCodes } = useAccess();
const canSyncUsers = computed(() => hasAccessByCodes(['wechat.client.user.sync']));

const keyword = ref('');
const accountId = ref<number>();
const subscribeFilter = ref<number>();
const loading = ref(false);
const syncing = ref(false);
const items = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });

const columns = [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '账号ID', dataIndex: 'account_id', width: 90 },
  { title: 'OpenID', key: 'openid', dataIndex: 'openid', width: 260 },
  { title: '昵称', key: 'nickname', dataIndex: 'nickname', width: 160 },
  { title: 'UnionID', key: 'unionid', dataIndex: 'unionid', width: 240 },
  { title: '关注', key: 'subscribe', dataIndex: 'subscribe', width: 100 },
  { title: '标签ID', key: 'tagids', dataIndex: 'tagids', width: 180 },
  { title: '关注时间', dataIndex: 'subscribe_time', width: 180 },
  { title: '更新时间', dataIndex: 'updated_at', width: 180 },
];
const tableScroll = buildTableScrollX(columns, { minWidth: 1460 });

const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => {
  const filters: CrudFilterSummaryItem[] = [];
  if (keyword.value.trim()) {
    filters.push({ label: '关键字', value: keyword.value.trim() });
  }
  if (accountId.value) {
    filters.push({ label: '账号 ID', value: String(accountId.value) });
  }
  if (subscribeFilter.value !== undefined) {
    filters.push({ label: '关注状态', value: Number(subscribeFilter.value) === 1 ? '已关注' : '未关注' });
  }
  return filters;
});

const summaryCards = computed(() => {
  const subscribed = items.value.filter((item) => Number(item.subscribe) === 1).length;
  return [
    { label: '粉丝总数', value: String(pagination.total), desc: '当前筛选条件下的粉丝数量', icon: 'i-lucide-users', tone: 'primary' as const },
    { label: '本页关注', value: String(subscribed), desc: '当前页已关注粉丝数量', icon: 'i-lucide-user-check', tone: 'success' as const },
    { label: '本页取关', value: String(items.value.length - subscribed), desc: '当前页未关注或已取关粉丝', icon: 'i-lucide-user-x', tone: 'warning' as const },
    { label: '同步账号', value: accountId.value ? String(accountId.value) : '-', desc: '执行粉丝同步时使用的接口账号 ID', icon: 'i-lucide-refresh-cw', tone: 'info' as const },
  ];
});

function visibleTagids(record: any): string[] {
  return Array.isArray(record.tagids)
    ? record.tagids.map((tagId: unknown) => String(tagId)).filter(Boolean).slice(0, 6)
    : [];
}

async function load() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-client/user/index', { params: { page: pagination.current, pageSize: pagination.pageSize, keyword: keyword.value, account_id: accountId.value, subscribe: subscribeFilter.value } });
    items.value = data?.items || [];
    pagination.total = data?.pageInfo?.total || 0;
  } finally {
    loading.value = false;
  }
}

function handleSearch() {
  pagination.current = 1;
  load();
}

function handleReset() {
  keyword.value = '';
  accountId.value = undefined;
  subscribeFilter.value = undefined;
  handleSearch();
}

async function syncUsers() {
  if (syncing.value) return;
  if (!accountId.value) {
    message.warning('请先输入账号 ID');
    return;
  }
  syncing.value = true;
  try {
    await requestClient.post(`wechat-client/user/sync/${accountId.value}`, { max_pages: 20 });
    message.success('同步任务完成');
    await load();
  } finally {
    syncing.value = false;
  }
}

function onTableChange(pag: any) {
  pagination.current = pag.current;
  pagination.pageSize = pag.pageSize;
  load();
}

onMounted(load);
</script>
