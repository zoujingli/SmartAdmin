<template>
  <Page title="通知中心">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data' && canManageNotices">
          <Button v-if="canCreateNotices" type="primary" @click="handleAdd">
            <span class="i-lucide-plus" />
            新增公告
          </Button>
          <Button v-if="canCreateNotices" @click="handleImport">
            <span class="i-lucide-upload" />
            导入
          </Button>
          <Button v-if="canExportNotices" :loading="exporting" @click="handleExport">
            <span class="i-lucide-download" />
            导出
          </Button>
        </template>
        <template v-else-if="activeTab === 'inbox'">
          <Button @click="handleReadAll">全部已读</Button>
          <Button @click="handleArchiveAll">清空收件箱</Button>
        </template>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane v-if="canManageNotices" key="data" tab="数据列表">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容"><Input v-model:value="manageSearch.keyword" allow-clear placeholder="请输入公告标题或内容" /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="公告级别">
                  <Select v-model:value="manageSearch.level" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption value="info">信息</SelectOption>
                    <SelectOption value="success">成功</SelectOption>
                    <SelectOption value="warning">警告</SelectOption>
                    <SelectOption value="error">错误</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="当前状态">
                  <Select v-model:value="manageSearch.status" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption :value="1">启用</SelectOption>
                    <SelectOption :value="0">停用</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6" class="flex flex-wrap items-center gap-2 crud-search-grid__actions">
                <Button type="primary" :loading="loadingManage" @click="handleManageSearch"><span class="i-lucide-search" />搜索</Button>
                <Button :disabled="loadingManage" @click="handleManageReset"><span class="i-lucide-refresh-cw" />重置</Button>
              </Col>
            </Row>
            <CrudFilterSummary
              :items="manageFilterItems"
              empty-text="当前显示全部公告，可按标题、级别和状态快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="manageSummaryCards" />

          <Card>
            <CrudTableHeader
              title="公告列表"
              description="维护公告投放、发布状态和收件范围，支持新增、编辑、发布与删除。"
              :count-text="`${managePagination.total} 条记录`"
            />
            <Table
              :columns="manageColumns"
              :data-source="manageData"
              :loading="loadingManage"
              :locale="buildCrudTableLocale('暂无公告记录')"
              :pagination="managePagination"
              :scroll="manageTableScroll"
              row-key="id"
              @change="handleManageTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'level'">
                  <CrudToneTag :color="getLevelColor(record.level)" :text="getLevelLabel(record.level)" />
                </template>
                <template v-else-if="column.key === 'title'">
                  <Tooltip :title="record.title" placement="topLeft">
                    <div class="truncate">{{ record.title }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'status'">
                  <Switch :checked="record.status === 1" :disabled="!canStatusNotices" @change="(checked) => handleStatusChange(record.id, checked === true)" />
                </template>
                <template v-else-if="column.key === 'published_at'">
                  {{ record.published_at || '未发布' }}
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="noticeManageActions(record)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="canManageNotices && (canRecoveryNotices || canRealDeleteNotices)" key="recycle" tab="回收站">
          <Card>
            <CrudTableHeader
              title="已删除公告"
              description="回收站中的公告可恢复到主列表；彻底删除后将不可恢复。"
              count-color="warning"
              :count-text="`${recyclePagination.total} 条记录`"
            />
            <Table
              :columns="recycleColumns"
              :data-source="recycleData"
              :loading="loadingRecycle"
              :locale="buildCrudTableLocale('回收站为空')"
              :pagination="recyclePagination"
              :scroll="recycleTableScroll"
              row-key="id"
              @change="handleRecycleTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'title'">
                  <Tooltip :title="record.title" placement="topLeft">
                    <div class="truncate">{{ record.title }}</div>
                  </Tooltip>
                </template>
                <template v-if="column.key === 'action'">
                  <CrudTableActions :actions="noticeRecycleActions(record)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane key="inbox" tab="我的通知">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容"><Input v-model:value="inboxSearch.keyword" allow-clear placeholder="请输入标题或内容关键字" /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="公告级别">
                  <Select v-model:value="inboxSearch.level" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption value="info">信息</SelectOption>
                    <SelectOption value="success">成功</SelectOption>
                    <SelectOption value="warning">警告</SelectOption>
                    <SelectOption value="error">错误</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6" class="flex flex-wrap items-center gap-2 crud-search-grid__actions">
                <Button type="primary" :loading="loadingInbox" @click="handleInboxSearch"><span class="i-lucide-search" />搜索</Button>
                <Button :disabled="loadingInbox" @click="handleInboxReset"><span class="i-lucide-refresh-cw" />重置</Button>
              </Col>
            </Row>
            <CrudFilterSummary
              :items="inboxFilterItems"
              empty-text="当前显示全部收件通知，可按关键字和级别快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="inboxSummaryCards" />

          <Card>
            <CrudTableHeader
              title="我的收件箱"
              description="查看当前账号收到的系统公告，支持标记已读、跳转查看与移除通知。"
              count-color="processing"
              :count-text="`${inboxPagination.total} 条记录`"
            />
            <Table
              :columns="inboxColumns"
              :data-source="inboxData"
              :loading="loadingInbox"
              :locale="buildCrudTableLocale('暂无通知')"
              :pagination="inboxPagination"
              :scroll="inboxTableScroll"
              row-key="id"
              @change="handleInboxTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'level'">
                  <CrudToneTag :color="getLevelColor(record.level)" :text="getLevelLabel(record.level)" />
                </template>
                <template v-else-if="column.key === 'title'">
                  <Tooltip :title="record.title" placement="topLeft">
                    <div class="truncate">{{ record.title }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'status'">
                  <CrudStatusTag
                    :value="Boolean(record.is_read)"
                    true-text="已读"
                    false-text="未读"
                    false-color="orange"
                  />
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="noticeInboxActions(record)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>
      </Tabs>
    </Card>

    <Drawer
      :open="modalVisible"
      :title="formState.id ? '编辑公告' : '新增公告'"
      :body-style="{ padding: '20px 24px 8px' }"
      :width="popupWidth.md"
      placement="right"
      @close="modalVisible = false"
    >
      <Form ref="formRef" :model="formState" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :span="24">
            <FormItem label="公告标题" name="title" required>
              <Input v-model:value="formState.title" :maxlength="120" placeholder="请输入公告标题" />
            </FormItem>
          </Col>
          <Col :span="12">
            <FormItem label="公告级别" name="level" required>
              <Select v-model:value="formState.level">
                <SelectOption value="info">信息</SelectOption>
                <SelectOption value="success">成功</SelectOption>
                <SelectOption value="warning">警告</SelectOption>
                <SelectOption value="error">错误</SelectOption>
              </Select>
            </FormItem>
          </Col>
          <Col :span="12">
            <FormItem label="状态" name="status">
              <Select v-model:value="formState.status">
                <SelectOption :value="1">启用</SelectOption>
                <SelectOption :value="0">停用</SelectOption>
              </Select>
            </FormItem>
          </Col>
          <Col :span="12">
            <FormItem label="过期时间" name="expired_at">
              <Input v-model:value="formState.expired_at" placeholder="留空表示不过期，如 2026-04-30 23:59:59" />
            </FormItem>
          </Col>
          <Col :span="12">
            <FormItem label="跳转链接" name="link">
              <Input v-model:value="formState.link" placeholder="可选，支持内部路径或外部链接" />
            </FormItem>
          </Col>
          <Col :span="24">
            <FormItem label="接收用户" name="recipient_ids" required>
              <Select
                v-model:value="formState.recipient_ids"
                mode="multiple"
                allow-clear
                show-search
                :filter-option="false"
                :options="recipientOptions"
                placeholder="请输入用户昵称或用户名搜索"
                @search="loadUserOptions"
              />
            </FormItem>
          </Col>
          <Col :span="24">
            <FormItem label="公告内容" name="content" required>
              <Input.TextArea v-model:value="formState.content" :rows="6" placeholder="请输入公告内容" />
            </FormItem>
          </Col>
        </Row>
      </Form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button @click="modalVisible = false">取消</Button>
          <Button type="primary" :loading="saving" @click="handleSubmit">确定</Button>
        </div>
      </template>
    </Drawer>

    <Modal
      :open="detailVisible"
      title="公告详情"
      :width="popupWidth.lg"
      ok-text="关闭"
      @cancel="detailVisible = false"
      @ok="detailVisible = false"
    >
      <CrudDetailPanel v-if="currentNotice">
        <CrudDetailHero
          icon="i-lucide-bell-ring"
          :lines="[
            `发布时间：${currentNotice.published_at || '未发布'}`,
            `过期时间：${currentNotice.expired_at || '不过期'}`,
            currentNotice.link ? `跳转链接：${currentNotice.link}` : undefined,
          ]"
          :tags="[
            { color: getLevelColor(currentNotice.level), label: getLevelLabel(currentNotice.level) },
            { color: currentNotice.status === 1 ? 'success' : 'default', label: currentNotice.status === 1 ? '启用' : '停用' },
            { label: `${currentNotice.recipient_count || 0} 位接收人` },
          ]"
          :title="currentNotice.title"
        />

        <CrudDetailDescriptions>
          <DescriptionsItem label="公告 ID">{{ currentNotice.id }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentNotice.created_at || '-' }}</DescriptionsItem>
          <DescriptionsItem label="公告标题">{{ currentNotice.title }}</DescriptionsItem>
          <DescriptionsItem label="公告级别">{{ getLevelLabel(currentNotice.level) }}</DescriptionsItem>
          <DescriptionsItem label="发布状态">{{ currentNotice.status === 1 ? '启用' : '停用' }}</DescriptionsItem>
          <DescriptionsItem label="接收人数">{{ currentNotice.recipient_count || 0 }}</DescriptionsItem>
          <DescriptionsItem label="发布时间">{{ currentNotice.published_at || '未发布' }}</DescriptionsItem>
          <DescriptionsItem label="过期时间">{{ currentNotice.expired_at || '不过期' }}</DescriptionsItem>
          <DescriptionsItem label="跳转链接" :span="2">
            <CrudDetailLink :href="currentNotice.link || ''" copy-label="跳转链接" />
          </DescriptionsItem>
          <DescriptionsItem label="公告内容" :span="2">{{ currentNotice.content }}</DescriptionsItem>
        </CrudDetailDescriptions>
      </CrudDetailPanel>
    </Modal>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useAccess } from '@vben/access';
import {
  CrudDetailDescriptions,
  CrudDetailHero,
  CrudDetailLink,
  CrudDetailPanel,
  CrudFilterSummary,
  buildCrudTableLocale,
  CrudStatusTag,
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
  Drawer,
  Form,
  FormItem,
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

import { noticeApiService } from '#/api/system/notice';
import type { NoticeApi } from '#/api/system/notice';
import { userApiService } from '#/api/system/user';
import { exportCrudXlsx, openCrudImport, parseStatus, parseStringList } from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { popupWidth } from '#/utils/popup';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

const route = useRoute();
const router = useRouter();
const { hasAccessByCodes } = useAccess();

const canManageNotices = computed(() => hasAccessByCodes(['system.notice.index']));
const canCreateNotices = computed(() => hasAccessByCodes(['system.notice.create']));
const canUpdateNotices = computed(() => hasAccessByCodes(['system.notice.update']));
const canDeleteNotices = computed(() => hasAccessByCodes(['system.notice.delete']));
const canPublishNotices = computed(() => hasAccessByCodes(['system.notice.publish']));
const canRecoveryNotices = computed(() => hasAccessByCodes(['system.notice.recovery']));
const canRealDeleteNotices = computed(() => hasAccessByCodes(['system.notice.real-delete']));
const canStatusNotices = computed(() => hasAccessByCodes(['system.notice.status']));
const canExportNotices = computed(() => hasAccessByCodes(['system.notice.export']));

const levelLabels = {
  info: '信息',
  success: '成功',
  warning: '警告',
  error: '错误',
} as const;

const levelColors = {
  info: 'processing',
  success: 'success',
  warning: 'warning',
  error: 'error',
} as const;

type NoticeTab = 'data' | 'inbox' | 'recycle';
type RecipientOption = { label: string; value: number };
type NoticeFormState = {
  id: number;
  title: string;
  content: string;
  level: NoticeApi.NoticeLevel;
  status: number;
  expired_at: string;
  link: string;
  recipient_ids: number[];
};

const activeTab = ref<NoticeTab>(canManageNotices.value ? 'data' : 'inbox');
const loadingInbox = ref(false);
const exporting = ref(false);
const loadingManage = ref(false);
const loadingRecycle = ref(false);
const saving = ref(false);
const modalVisible = ref(false);
const detailVisible = ref(false);
const recipientOptions = ref<RecipientOption[]>([]);
const currentNotice = ref<NoticeApi.NoticeInfo | null>(null);

const inboxSearch = reactive({
  keyword: '',
  level: undefined as string | undefined,
});
const manageSearch = reactive({
  keyword: '',
  level: undefined as string | undefined,
  status: undefined as number | undefined,
});
const formState = reactive<NoticeFormState>({
  id: 0,
  title: '',
  content: '',
  level: 'info',
  status: 1,
  expired_at: '',
  link: '',
  recipient_ids: [],
});

const inboxData = ref<NoticeApi.InboxItem[]>([]);
const manageData = ref<NoticeApi.NoticeInfo[]>([]);
const recycleData = ref<NoticeApi.NoticeInfo[]>([]);

const inboxSummary = computed(() => ({
  total: inboxPagination.total,
  unread: inboxData.value.filter((item) => !item.is_read).length,
  read: inboxData.value.filter((item) => item.is_read).length,
}));
const manageSummary = computed(() => ({
  total: managePagination.total,
  published: manageData.value.filter((item) => !!item.published_at).length,
  enabled: manageData.value.filter((item) => item.status === 1).length,
  expiring: manageData.value.filter((item) => item.expired_at).length,
}));
const manageSummaryCards = computed(() => [
  {
    desc: '当前公告列表中的有效公告数量。',
    icon: 'i-lucide-megaphone',
    label: '公告总数',
    value: String(manageSummary.value.total),
  },
  {
    desc: '已经执行过发布动作的公告数量。',
    icon: 'i-lucide-send',
    label: '已发布',
    value: String(manageSummary.value.published),
  },
  {
    desc: '当前处于启用状态的公告数量。',
    icon: 'i-lucide-badge-check',
    label: '启用公告',
    value: String(manageSummary.value.enabled),
  },
  {
    desc: '设置了过期时间的公告数量。',
    icon: 'i-lucide-timer',
    label: '即将过期',
    value: String(manageSummary.value.expiring),
  },
]);
const inboxSummaryCards = computed(() => [
  {
    desc: '当前收件箱中可见的通知总数。',
    icon: 'i-lucide-inbox',
    label: '收件箱总数',
    value: String(inboxSummary.value.total),
  },
  {
    desc: '尚未标记为已读的通知数量。',
    icon: 'i-lucide-mail-warning',
    label: '未读通知',
    value: String(inboxSummary.value.unread),
  },
  {
    desc: '已经阅读过的通知数量。',
    icon: 'i-lucide-mail-check',
    label: '已读通知',
    value: String(inboxSummary.value.read),
  },
]);
const manageFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const keyword = manageSearch.keyword.trim();
  if (keyword !== '') {
    items.push({ label: '关键字', value: keyword });
  }
  if (manageSearch.level) {
    items.push({ label: '级别', value: getLevelLabel(manageSearch.level as NoticeApi.NoticeLevel) });
  }
  if (typeof manageSearch.status === 'number') {
    items.push({ label: '状态', value: manageSearch.status === 1 ? '启用' : '停用' });
  }
  return items;
});
const inboxFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];
  const keyword = inboxSearch.keyword.trim();
  if (keyword !== '') {
    items.push({ label: '关键字', value: keyword });
  }
  if (inboxSearch.level) {
    items.push({ label: '级别', value: getLevelLabel(inboxSearch.level as NoticeApi.NoticeLevel) });
  }
  return items;
});

const inboxPagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
});
const managePagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
});
const recyclePagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
});

const inboxActionColumnWidth = computed(() => estimateVisibleActionColumnWidth(inboxData.value.length > 0 ? inboxData.value.map(noticeInboxActions) : [noticeInboxActions({})], { maxWidth: 220 }));
const manageActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([noticeManageActions({})], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([noticeRecycleActions({})], { maxWidth: 180 }));
const inboxColumns = computed(() => [
  { title: '标题', dataIndex: 'title', key: 'title', width: 220 },
  { title: '内容', dataIndex: 'content', key: 'content', ellipsis: true },
  { title: '级别', dataIndex: 'level', key: 'level', width: 100 },
  { title: '发布时间', dataIndex: 'published_at', key: 'published_at', width: 180 },
  { title: '状态', key: 'status', width: 100 },
  { title: '操作', key: 'action', width: inboxActionColumnWidth.value, fixed: 'right' as const },
]);
const manageColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '标题', dataIndex: 'title', key: 'title', width: 220 },
  { title: '级别', dataIndex: 'level', key: 'level', width: 100 },
  { title: '收件人数', dataIndex: 'recipient_count', key: 'recipient_count', width: 100 },
  { title: '发布时间', key: 'published_at', width: 180 },
  { title: '过期时间', dataIndex: 'expired_at', key: 'expired_at', width: 180 },
  { title: '状态', key: 'status', width: 110 },
  { title: '操作', key: 'action', width: manageActionColumnWidth.value, fixed: 'right' as const },
]);
const recycleColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
  { title: '标题', dataIndex: 'title', key: 'title', width: 220 },
  { title: '级别', dataIndex: 'level', key: 'level', width: 100 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value },
]);

const inboxTableScroll = computed(() => buildTableScrollX(inboxColumns.value));
const manageTableScroll = computed(() => buildTableScrollX(manageColumns.value));
const recycleTableScroll = computed(() => buildTableScrollX(recycleColumns.value));

function noticeManageActions(record: any) {
  return [
    { label: '查看', onClick: () => handleView(record.id) },
    { label: '编辑', visible: canUpdateNotices.value, onClick: () => handleEdit(record.id) },
    { label: '发布', visible: canPublishNotices.value, onClick: () => handlePublish(record.id) },
    { label: '删除', visible: canDeleteNotices.value, danger: true, onClick: () => handleDelete(record.id) },
  ];
}

function noticeRecycleActions(record: any) {
  return [
    { label: '恢复', visible: canRecoveryNotices.value, onClick: () => handleRecovery(record.id) },
    { label: '彻底删除', visible: canRealDeleteNotices.value, danger: true, onClick: () => handleRealDelete(record.id) },
  ];
}

function noticeInboxActions(record: any) {
  return [
    { label: '标记已读', visible: !record?.is_read, onClick: () => handleRead(record.id) },
    { label: '查看', onClick: () => handleOpenLink(record) },
    { label: '移除', danger: true, confirmTitle: '确认从收件箱移除该公告？', onClick: () => handleArchive(record.id) },
  ];
}

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'title', title: '标题', width: 220 },
  { key: 'content', title: '内容', width: 360 },
  { key: 'level', title: '级别', width: 100, formatter: (record: NoticeApi.NoticeInfo) => getLevelLabel(record.level) },
  { key: 'status', title: '状态', width: 90, formatter: (record: NoticeApi.NoticeInfo) => (record.status === 1 ? '启用' : '停用') },
  { key: 'recipient_count', title: '收件人数', width: 100 },
  { key: 'published_at', title: '发布时间', width: 180, formatter: (record: NoticeApi.NoticeInfo) => record.published_at || '未发布' },
  { key: 'expired_at', title: '过期时间', width: 180, formatter: (record: NoticeApi.NoticeInfo) => record.expired_at || '' },
  { key: 'link', title: '跳转链接', width: 220 },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const importColumns = [
  { key: 'title', title: '标题', required: true, example: '导入公告', rule: '公告标题，不能为空。' },
  { key: 'content', title: '内容', required: true, example: '这是一条导入公告内容。', rule: '公告正文，不能为空。' },
  { key: 'level', title: '级别', example: '普通', parser: (value: any) => parseNoticeLevel(value), rule: '支持 普通/重要/紧急 或对应等级值，留空默认普通。' },
  { key: 'status', title: '状态', example: '启用', parser: (value: any) => parseStatus(value, 1), rule: '支持 启用/禁用 或 1/0，留空默认启用。' },
  { key: 'expired_at', title: '过期时间', example: '2026-12-31 23:59:59', rule: '可选，建议使用 YYYY-MM-DD HH:mm:ss。' },
  { key: 'link', title: '跳转链接', example: '/system/notice', rule: '可选，可填写站内路径或 http(s) 链接。' },
  { key: 'recipient_ids', title: '接收用户ID', required: true, example: '1,2,3', parser: (value: any) => parseRecipientIds(value), rule: '必填，多个用户 ID 用英文逗号或中文逗号分隔。' },
];

async function loadInbox() {
  if (loadingInbox.value) return;
  try {
    loadingInbox.value = true;
    const response = await noticeApiService.getInbox({
      page: inboxPagination.current,
      pageSize: inboxPagination.pageSize,
      keyword: inboxSearch.keyword,
      level: inboxSearch.level as any,
    });
    inboxData.value = response.items || [];
    inboxPagination.total = response.pageInfo?.total || response.total || 0;
  } catch (error) {
    console.error('load inbox failed', error);
    message.error('获取通知收件箱失败');
  } finally {
    loadingInbox.value = false;
  }
}

async function loadManage() {
  if (!canManageNotices.value) {
    manageData.value = [];
    managePagination.total = 0;
    return;
  }
  if (loadingManage.value) return;

  try {
    loadingManage.value = true;
    const response = await noticeApiService.getNoticeList({
      page: managePagination.current,
      pageSize: managePagination.pageSize,
      keyword: manageSearch.keyword,
      level: manageSearch.level as any,
      status: manageSearch.status,
    });
    manageData.value = response.items || [];
    managePagination.total = response.pageInfo?.total || response.total || 0;
  } catch (error) {
    console.error('load notice list failed', error);
    message.error('获取公告列表失败');
  } finally {
    loadingManage.value = false;
  }
}

async function loadRecycle() {
  if (!canManageNotices.value || (!canRecoveryNotices.value && !canRealDeleteNotices.value)) {
    recycleData.value = [];
    recyclePagination.total = 0;
    return;
  }
  if (loadingRecycle.value) return;

  try {
    loadingRecycle.value = true;
    const response = await noticeApiService.getRecycleList({
      page: recyclePagination.current,
      pageSize: recyclePagination.pageSize,
      keyword: manageSearch.keyword,
      level: manageSearch.level as any,
      status: manageSearch.status,
    });
    recycleData.value = response.items || [];
    recyclePagination.total = response.pageInfo?.total || response.total || 0;
  } catch (error) {
    console.error('load notice recycle failed', error);
    message.error('获取公告回收站失败');
  } finally {
    loadingRecycle.value = false;
  }
}

async function loadUserOptions(keyword = '') {
  try {
    const items = await userApiService.getUserOptions({ keyword, limit: 30 });
    recipientOptions.value = items.map((item) => ({
      label: item.label,
      value: item.id,
    }));
  } catch (error) {
    console.error('load user options failed', error);
  }
}

function getLevelColor(level: NoticeApi.NoticeLevel) {
  return levelColors[level];
}

function getLevelLabel(level: NoticeApi.NoticeLevel) {
  return levelLabels[level];
}

function parseNoticeLevel(value: unknown): NoticeApi.NoticeLevel {
  const text = String(value ?? '').trim();
  const map: Record<string, NoticeApi.NoticeLevel> = {
    error: 'error',
    info: 'info',
    success: 'success',
    warning: 'warning',
    信息: 'info',
    成功: 'success',
    警告: 'warning',
    错误: 'error',
  };
  return map[text] || 'info';
}

function parseRecipientIds(value: unknown): number[] {
  return parseStringList(value as any)
    .map((item) => Number(item))
    .filter((item) => Number.isFinite(item) && item > 0);
}

function resetForm() {
  formState.id = 0;
  formState.title = '';
  formState.content = '';
  formState.level = 'info';
  formState.status = 1;
  formState.expired_at = '';
  formState.link = '';
  formState.recipient_ids = [];
}

async function handleAdd() {
  resetForm();
  await loadUserOptions();
  modalVisible.value = true;
}

async function handleView(id: number) {
  const detail = await noticeApiService.getNoticeDetail(id);
  currentNotice.value = detail;
  detailVisible.value = true;
}

async function handleEdit(id: number) {
  const detail = await noticeApiService.getNoticeDetail(id);
  formState.id = detail.id;
  formState.title = detail.title;
  formState.content = detail.content;
  formState.level = detail.level;
  formState.status = detail.status;
  formState.expired_at = detail.expired_at || '';
  formState.link = detail.link || '';
  formState.recipient_ids = detail.recipient_ids || [];
  await loadUserOptions();
  modalVisible.value = true;
}

async function handleSubmit() {
  if (!formState.title.trim() || !formState.content.trim()) {
    message.error('请填写公告标题和内容');
    return;
  }
  if (formState.recipient_ids.length === 0) {
    message.error('请至少选择一个接收用户');
    return;
  }

  try {
    saving.value = true;
    const payload = {
      title: formState.title.trim(),
      content: formState.content.trim(),
      level: formState.level,
      status: formState.status,
      expired_at: formState.expired_at.trim() || null,
      link: formState.link.trim(),
      recipient_ids: formState.recipient_ids,
    };
    if (formState.id > 0) {
      await noticeApiService.updateNotice(formState.id, payload);
      message.success('公告更新成功');
    } else {
      await noticeApiService.createNotice(payload);
      message.success('公告创建成功');
    }
    modalVisible.value = false;
    await Promise.all([loadManage(), loadRecycle(), loadInbox()]);
  } catch (error) {
    console.error('save notice failed', error);
  } finally {
    saving.value = false;
  }
}

async function handleExport() {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<NoticeApi.NoticeInfo>({
      columns: exportColumns,
      fetchPage: (page, pageSize) => noticeApiService.getNoticeList({
        page,
        pageSize,
        keyword: manageSearch.keyword,
        level: manageSearch.level as any,
        status: manageSearch.status,
      }),
      filename: `notices_${new Date().toISOString().slice(0, 10)}.xlsx`,
      sheetName: '通知公告',
    });
  } finally {
    exporting.value = false;
  }
}

async function handleImport() {
  await openCrudImport<NoticeApi.NoticeFormData>({
    columns: importColumns,
    moduleName: '公告',
    rules: [
      '公告导入会按接收用户 ID 创建接收关系，接收用户 ID 不能为空。',
      '多个接收用户 ID 支持英文逗号、中文逗号或斜杠分隔。',
    ],
    buildPayload(row) {
      const recipientIds = parseRecipientIds(row['接收用户ID'] ?? row.recipient_ids);
      if (recipientIds.length === 0) {
        throw new Error('接收用户ID不能为空，多个用户 ID 可用逗号分隔');
      }

      return {
        title: String(row['标题'] ?? row.title ?? '').trim(),
        content: String(row['内容'] ?? row.content ?? '').trim(),
        level: parseNoticeLevel(row['级别'] ?? row.level),
        status: parseStatus(row['状态'] ?? row.status, 1),
        expired_at: String(row['过期时间'] ?? row.expired_at ?? '').trim() || null,
        link: String(row['跳转链接'] ?? row.link ?? '').trim(),
        recipient_ids: recipientIds,
      };
    },
    submit: (payload) => noticeApiService.createNotice(payload),
    async afterDone() {
      managePagination.current = 1;
      await Promise.all([loadManage(), loadRecycle(), loadInbox()]);
    },
  });
}

function handleOpenLink(record: { link?: string }) {
  if (record.link) {
    if (record.link.startsWith('http')) {
      window.open(record.link, '_blank');
      return;
    }
    router.push(record.link);
    return;
  }
  router.push({ path: '/system/notice', query: { tab: 'inbox' } });
}

async function handlePublish(id: number) {
  Modal.confirm({
    title: '确认发布该公告？',
    async onOk() {
      await noticeApiService.publishNotice(id);
      message.success('公告已发布');
      await Promise.all([loadManage(), loadInbox()]);
    },
  });
}

async function handleDelete(id: number) {
  Modal.confirm({
    title: '确认删除该公告？',
    async onOk() {
      await noticeApiService.deleteNotice(id);
      message.success('公告已删除');
      await Promise.all([loadManage(), loadRecycle()]);
    },
  });
}

async function handleRecovery(id: number) {
  await noticeApiService.recoveryNotices([id]);
  message.success('公告已恢复');
  await Promise.all([loadManage(), loadRecycle()]);
}

async function handleRealDelete(id: number) {
  Modal.confirm({
    title: '确认彻底删除该公告？',
    async onOk() {
      await noticeApiService.realDeleteNotices([id]);
      message.success('公告已彻底删除');
      await loadRecycle();
    },
  });
}

async function handleStatusChange(id: number, checked: boolean) {
  await noticeApiService.updateNoticeStatus(id, checked ? 1 : 0);
  message.success('公告状态已更新');
  await Promise.all([loadManage(), loadInbox()]);
}

async function handleRead(id: number) {
  await noticeApiService.read([id]);
  await loadInbox();
}

async function handleReadAll() {
  await noticeApiService.readAll();
  message.success('已全部标记为已读');
  await loadInbox();
}

async function handleArchive(id: number) {
  await noticeApiService.archive([id]);
  await loadInbox();
}

async function handleArchiveAll() {
  await noticeApiService.archiveAll();
  message.success('已清空收件箱');
  await loadInbox();
}

function handleInboxSearch() {
  inboxPagination.current = 1;
  loadInbox();
}

function handleInboxReset() {
  inboxSearch.keyword = '';
  inboxSearch.level = undefined;
  inboxPagination.current = 1;
  loadInbox();
}

function handleManageSearch() {
  managePagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadManage(), loadRecycle()]);
}

function handleManageReset() {
  manageSearch.keyword = '';
  manageSearch.level = undefined;
  manageSearch.status = undefined;
  managePagination.current = 1;
  recyclePagination.current = 1;
  Promise.all([loadManage(), loadRecycle()]);
}

function handleInboxTableChange(pagination: any) {
  inboxPagination.current = pagination.current;
  inboxPagination.pageSize = pagination.pageSize;
  loadInbox();
}

function handleManageTableChange(pagination: any) {
  managePagination.current = pagination.current;
  managePagination.pageSize = pagination.pageSize;
  loadManage();
}

function handleRecycleTableChange(pagination: any) {
  recyclePagination.current = pagination.current;
  recyclePagination.pageSize = pagination.pageSize;
  loadRecycle();
}

watch(
  () => route.query.tab,
  (value) => {
    const nextTab = String(value || '');
    if ((nextTab === 'data' || nextTab === 'manage') && canManageNotices.value) {
      activeTab.value = 'data';
      return;
    }
    if (nextTab === 'recycle' && canManageNotices.value) {
      activeTab.value = 'recycle';
      return;
    }
    if (nextTab === 'inbox') {
      activeTab.value = 'inbox';
      return;
    }
    activeTab.value = canManageNotices.value ? 'data' : 'inbox';
  },
  { immediate: true },
);

onMounted(() => {
  Promise.all([loadInbox(), loadManage(), loadRecycle()]);
});
</script>
