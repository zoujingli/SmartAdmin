<template>
  <Page title="自动回复">
    <template #extra>
      <Space wrap>
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="canSave" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增规则</Button>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Alert class="mb-5" type="info" show-icon message="订阅延时回复采用 Swoole 内存协程发送，服务重启会丢失尚未执行的延时任务；如需强可靠请后续切换为数据库队列。" />
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row class="crud-search-grid" :gutter="[16,16]">
          <Col :xs="24" :sm="12" :xl="4"><SearchField label="接口账号"><InputNumber v-model:value="accountId" :min="1" class="w-full" placeholder="账号 ID" /></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="5"><SearchField label="规则类型"><Select v-model:value="ruleType" allow-clear class="w-full" placeholder="请选择"><SelectOption value="subscribe">订阅回复</SelectOption><SelectOption value="default">默认回复</SelectOption><SelectOption value="keyword">关键词</SelectOption><SelectOption value="menu_click">菜单点击</SelectOption></Select></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="5"><SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="请输入关键词" /></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="4"><SearchField label="当前状态"><Select v-model:value="statusFilter" allow-clear class="w-full" placeholder="请选择"><SelectOption :value="1">启用</SelectOption><SelectOption :value="0">禁用</SelectOption></Select></SearchField></Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6"><Space wrap><Button type="primary" :loading="loading" @click="handleSearch">搜索</Button><Button :disabled="loading" @click="handleReset">重置</Button></Space></Col>
        </Row>
        <CrudFilterSummary class="mt-4" :items="activeFilterItems" empty-text="当前显示全部自动回复规则，可按账号 ID、规则类型、状态或关键词筛选。" />
      </Card>
      <Card>
        <CrudTableHeader title="回复规则" description="维护订阅、关键词、默认和菜单点击回复；订阅回复支持多条按排序和延迟发送。" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="columns" :data-source="items" :loading="loading" :locale="buildCrudTableLocale('暂无自动回复规则')" :pagination="pagination" row-key="id" :scroll="tableScroll" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'rule_type'"><Tag color="blue">{{ ruleTypeLabel(record.rule_type) }}</Tag></template>
            <template v-else-if="column.key === 'reply_type'"><Tag>{{ replyTypeLabel(record.reply_type) }}</Tag></template>
            <template v-else-if="column.key === 'keyword'">{{ record.rule_type === 'keyword' ? record.keyword : '-' }}</template>
            <template v-else-if="column.key === 'delay'">{{ record.rule_type === 'subscribe' ? `${record.delay_seconds || 0} 秒` : '-' }}</template>
            <template v-else-if="column.key === 'status'">
              <Tag :color="Number(record.status) === 1 ? 'success' : 'default'">
                {{ Number(record.status) === 1 ? '启用' : '禁用' }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'action'"><CrudTableActions :actions="replyActions(record)" /></template>
          </template>
        </Table>
      </Card>
    </Card>

    <Drawer
      :open="modalOpen"
      :title="editingId ? '编辑回复规则' : '新增回复规则'"
      :body-style="{ padding: '20px 24px 8px' }"
      :width="popupWidth.md"
      placement="right"
      @close="modalOpen = false"
    >
      <Form :model="form" layout="vertical">
        <Row :gutter="[16,0]">
          <Col :span="8"><FormItem label="账号 ID"><InputNumber v-model:value="form.account_id" :min="1" class="w-full" /></FormItem></Col>
          <Col :span="8"><FormItem label="规则类型"><Select v-model:value="form.rule_type"><SelectOption value="subscribe">订阅回复</SelectOption><SelectOption value="default">默认回复</SelectOption><SelectOption value="keyword">关键词</SelectOption><SelectOption value="menu_click">菜单点击</SelectOption></Select></FormItem></Col>
          <Col :span="8"><FormItem label="回复类型"><Select v-model:value="form.reply_type"><SelectOption value="text">文本</SelectOption><SelectOption value="image">图片</SelectOption><SelectOption value="voice">语音</SelectOption><SelectOption value="video">视频</SelectOption><SelectOption value="news">图文</SelectOption></Select></FormItem></Col>
          <Col v-if="form.rule_type === 'keyword'" :span="12"><FormItem label="关键词"><Input v-model:value="form.keyword" :maxlength="120" /></FormItem></Col>
          <Col v-if="form.rule_type === 'keyword'" :span="12"><FormItem label="匹配模式"><Select v-model:value="form.match_mode"><SelectOption value="contains">包含</SelectOption><SelectOption value="exact">完全匹配</SelectOption></Select></FormItem></Col>
          <Col v-if="form.rule_type === 'subscribe'" :span="12"><FormItem label="延迟秒数"><InputNumber v-model:value="form.delay_seconds" :min="0" :max="86400" class="w-full" /></FormItem></Col>
          <Col :span="12"><FormItem label="排序"><InputNumber v-model:value="form.sort" class="w-full" /></FormItem></Col>
          <Col :span="12"><FormItem label="状态"><Select v-model:value="form.status"><SelectOption :value="1">启用</SelectOption><SelectOption :value="0">禁用</SelectOption></Select></FormItem></Col>
          <Col v-if="form.reply_type === 'text'" :span="24"><FormItem label="文本内容"><Textarea v-model:value="form.reply_content.content" :rows="4" /></FormItem></Col>
          <template v-else-if="['image','voice'].includes(form.reply_type)">
            <Col :span="12"><FormItem label="本地素材"><Select v-model:value="form.reply_content.media_local_id" allow-clear show-search option-filter-prop="label" placeholder="选择已同步或已上传素材"><SelectOption v-for="item in replyMediaOptions" :key="item.id" :label="`${item.name} (${item.media_type})`" :value="item.id">{{ item.name }} - {{ item.media_type }} - {{ item.media_id || '未上传' }}</SelectOption></Select></FormItem></Col>
            <Col :span="12"><FormItem label="微信 MediaID"><Input v-model:value="form.reply_content.media_id" placeholder="优先使用本字段" /></FormItem></Col>
          </template>
          <template v-else-if="form.reply_type === 'video'">
            <Col :span="12"><FormItem label="本地视频素材"><Select v-model:value="form.reply_content.media_local_id" allow-clear show-search option-filter-prop="label" placeholder="选择已同步或已上传视频"><SelectOption v-for="item in replyMediaOptions" :key="item.id" :label="`${item.name} (${item.media_type})`" :value="item.id">{{ item.name }} - {{ item.media_id || '未上传' }}</SelectOption></Select></FormItem></Col>
            <Col :span="12"><FormItem label="微信 MediaID"><Input v-model:value="form.reply_content.media_id" /></FormItem></Col>
            <Col :span="12"><FormItem label="视频标题"><Input v-model:value="form.reply_content.title" /></FormItem></Col>
            <Col :span="12"><FormItem label="视频描述"><Input v-model:value="form.reply_content.description" /></FormItem></Col>
          </template>
          <template v-else>
            <Col :span="12"><FormItem label="本地文章"><Select v-model:value="form.reply_content.article_id" allow-clear show-search option-filter-prop="label" placeholder="选择本地图文文章"><SelectOption v-for="item in articleOptions" :key="item.id" :label="item.title" :value="item.id">{{ item.title }} - {{ item.draft_media_id || '未上传草稿' }}</SelectOption></Select></FormItem></Col>
            <Col :span="12"><FormItem label="备用链接"><Input v-model:value="form.reply_content.url" /></FormItem></Col>
            <Col :span="24"><FormItem label="备用封面"><Input v-model:value="form.reply_content.picurl" /></FormItem></Col>
          </template>
        </Row>
      </Form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button @click="modalOpen = false">取消</Button>
          <Button type="primary" :loading="saving" @click="save">确定</Button>
        </div>
      </template>
    </Drawer>
  </Page>
</template>

<script setup lang="ts">
import type { CrudFilterSummaryItem } from '@vben/common-ui';

import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useAccess } from '@vben/access';
import {
  buildCrudTableLocale,
  CrudFilterSummary,
  CrudStatCards,
  CrudTableHeader,
  Page,
} from '@vben/common-ui';
import { Alert, Button, Card, Col, Drawer, Form, FormItem, Input, InputNumber, message, Row, Select, SelectOption, Space, Table, Tag, Textarea } from 'ant-design-vue';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { popupWidth } from '#/utils/popup';

const { hasAccessByCodes } = useAccess();
const canSave = computed(() => hasAccessByCodes(['wechat.client.reply.save']));
const canDelete = computed(() => hasAccessByCodes(['wechat.client.reply.delete']));
const loading = ref(false);
const saving = ref(false);
const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const accountId = ref<number>();
const ruleType = ref<string>();
const keyword = ref('');
const statusFilter = ref<number>();
const items = ref<any[]>([]);
const mediaOptions = ref<any[]>([]);
const articleOptions = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const form = reactive<any>(defaultForm());
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([replyActions({})], { maxWidth: 180 }));
const columns = computed<any[]>(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '账号', dataIndex: 'account_id', width: 90 },
  { title: '类型', key: 'rule_type', dataIndex: 'rule_type', width: 120 },
  { title: '关键词', key: 'keyword', dataIndex: 'keyword', width: 160 },
  { title: '回复', key: 'reply_type', dataIndex: 'reply_type', width: 100 },
  { title: '延迟', key: 'delay', dataIndex: 'delay_seconds', width: 100 },
  { title: '排序', dataIndex: 'sort', width: 90 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
  { title: '更新时间', dataIndex: 'updated_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' },
]);
const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1280 }));

function replyActions(record: any) {
  return [
    { label: '编辑', visible: canSave.value, onClick: () => openEdit(record) },
    { label: '删除', visible: canDelete.value, danger: true, confirmTitle: '确认删除该规则？', onClick: () => deleteRow(record.id) },
  ];
}

const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => {
  const filters: CrudFilterSummaryItem[] = [];
  if (accountId.value) {
    filters.push({ label: '账号 ID', value: String(accountId.value) });
  }
  if (ruleType.value) {
    filters.push({ label: '规则类型', value: ruleTypeLabel(ruleType.value) });
  }
  if (keyword.value.trim()) {
    filters.push({ label: '关键词', value: keyword.value.trim() });
  }
  if (statusFilter.value !== undefined) {
    filters.push({ label: '状态', value: Number(statusFilter.value) === 1 ? '启用' : '禁用' });
  }
  return filters;
});

const summaryCards = computed(() => {
  const enabled = items.value.filter((item) => Number(item.status) === 1).length;
  const delayed = items.value.filter((item) => Number(item.delay_seconds || 0) > 0).length;
  return [
    { label: '规则总数', value: String(pagination.total), desc: '当前筛选条件下的自动回复规则', icon: 'i-lucide-message-square-reply', tone: 'primary' as const },
    { label: '本页启用', value: String(enabled), desc: '当前页启用状态规则', icon: 'i-lucide-circle-check', tone: 'success' as const },
    { label: '延时回复', value: String(delayed), desc: '当前页设置了延迟秒数的规则', icon: 'i-lucide-timer', tone: 'warning' as const },
    { label: '筛选账号', value: accountId.value ? String(accountId.value) : '-', desc: '当前筛选使用的接口账号 ID', icon: 'i-lucide-badge-check', tone: 'info' as const },
  ];
});
const replyMediaOptions = computed(() => mediaOptions.value.filter((item) => String(item.media_type || '') === String(form.reply_type || '')));
function defaultForm() { return { account_id: accountId.value, rule_type: 'keyword', keyword: '', match_mode: 'contains', reply_type: 'text', reply_content: { content: '' }, delay_seconds: 0, sort: 0, status: 1 }; }
function ruleTypeLabel(v: string) { return ({ subscribe: '订阅回复', default: '默认回复', keyword: '关键词', menu_click: '菜单点击' } as Record<string,string>)[v] || v; }
function replyTypeLabel(v: string) { return ({ text: '文本', image: '图片', voice: '语音', video: '视频', news: '图文' } as Record<string,string>)[v] || v; }
watch(() => form.reply_type, (type) => { if (!form.reply_content || typeof form.reply_content !== 'object') form.reply_content = {}; if (type === 'text' && !('content' in form.reply_content)) form.reply_content = { content: '' }; });
watch(() => form.account_id, (value, oldValue) => { if (modalOpen.value && value !== oldValue) void loadResources(Number(value || 0) || undefined); });
async function load() { if (loading.value) return; loading.value = true; try { const data = await requestClient.get<any>('wechat-client/reply/index', { params: { page: pagination.current, pageSize: pagination.pageSize, account_id: accountId.value, rule_type: ruleType.value, keyword: keyword.value, status: statusFilter.value } }); items.value = data?.items || []; pagination.total = data?.pageInfo?.total || 0; } finally { loading.value = false; } }
async function loadResources(accountId?: number) {
  const params = { page: 1, pageSize: 100, account_id: accountId };
  const [media, articles] = await Promise.all([
    requestClient.get<any>('wechat-client/media/index', { params }),
    requestClient.get<any>('wechat-client/article/index', { params }),
  ]);
  mediaOptions.value = media?.items || [];
  articleOptions.value = articles?.items || [];
}
function handleSearch() { pagination.current = 1; load(); }
function handleReset() { accountId.value = undefined; ruleType.value = undefined; keyword.value = ''; statusFilter.value = undefined; handleSearch(); }
async function openCreate() { editingId.value = null; Object.assign(form, defaultForm()); modalOpen.value = true; await loadResources(accountId.value); }
async function openEdit(record: any) { editingId.value = record.id; Object.assign(form, { ...defaultForm(), ...record, reply_content: record.reply_content || {} }); modalOpen.value = true; await loadResources(record.account_id); }
function validateReplyForm() {
  if (!form.account_id) {
    message.error('接口账号不能为空');
    return false;
  }
  if (form.rule_type === 'keyword' && !String(form.keyword || '').trim()) {
    message.error('关键词规则必须填写关键词');
    return false;
  }
  if (form.reply_type === 'text' && !String(form.reply_content?.content || '').trim()) {
    message.error('文本回复内容不能为空');
    return false;
  }
  if (['image', 'voice', 'video'].includes(form.reply_type) && !String(form.reply_content?.media_id || '').trim() && !Number(form.reply_content?.media_local_id || 0)) {
    message.error('素材回复必须选择本地素材或填写 MediaID');
    return false;
  }
  if (form.reply_type === 'news' && !Number(form.reply_content?.article_id || 0)) {
    message.error('图文回复必须选择本地文章');
    return false;
  }
  return true;
}
async function save() { if (!validateReplyForm()) return; saving.value = true; try { if (editingId.value) await requestClient.put(`wechat-client/reply/update/${editingId.value}`, form); else await requestClient.post('wechat-client/reply/create', form); message.success('保存成功'); modalOpen.value = false; await load(); } finally { saving.value = false; } }
async function deleteRow(id: number) { await requestClient.delete(`wechat-client/reply/delete/${id}`); message.success('删除成功'); await load(); }
function onTableChange(pag: any) { pagination.current = pag.current; pagination.pageSize = pag.pageSize; load(); }
onMounted(load);
</script>
