<template>
  <Page title="文章管理">
    <template #extra>
      <Space wrap>
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="canSave" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增文章</Button>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card class="mb-5">
        <Row class="crud-search-grid" :gutter="[16, 16]">
          <Col :xs="24" :sm="12" :xl="5"><SearchField label="搜索内容"><Input v-model:value="keyword" allow-clear placeholder="标题 / 作者 / 发布ID" /></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="4"><SearchField label="接口账号"><InputNumber v-model:value="accountId" :min="1" class="w-full" placeholder="账号 ID" /></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="5">
            <SearchField label="发布状态">
              <Select v-model:value="publishStatus" allow-clear class="w-full" placeholder="请选择">
                <SelectOption value="draft">本地草稿</SelectOption>
                <SelectOption value="draft_uploaded">已上传草稿</SelectOption>
                <SelectOption value="publishing">发布中</SelectOption>
                <SelectOption value="publish_success">发布成功</SelectOption>
              </Select>
            </SearchField>
          </Col>
          <Col :xs="24" :sm="12" :xl="4"><SearchField label="当前状态"><Select v-model:value="statusFilter" allow-clear class="w-full" placeholder="请选择"><SelectOption :value="1">启用</SelectOption><SelectOption :value="0">禁用</SelectOption></Select></SearchField></Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6"><Space wrap><Button type="primary" :loading="loading" @click="handleSearch">搜索</Button><Button :disabled="loading" @click="handleReset">重置</Button></Space></Col>
        </Row>
        <CrudFilterSummary class="mt-4" :items="activeFilterItems" empty-text="当前显示全部文章，可按账号 ID、发布状态、启用状态、标题、作者或发布 ID 筛选。" />
      </Card>
      <Card>
        <CrudTableHeader title="图文文章" description="维护本地图文，上传微信草稿后可发布，并可作为菜单文章资源。" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="columns" :data-source="items" :loading="loading" :locale="buildCrudTableLocale('暂无图文文章')" :pagination="pagination" row-key="id" :scroll="tableScroll" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'title'"><div class="max-w-[260px] truncate">{{ record.title }}</div><div class="text-xs text-gray-400">{{ record.author || '未设置作者' }}</div></template>
            <template v-else-if="column.key === 'status'"><Tag :color="statusColor(record.publish_status)">{{ statusLabel(record.publish_status) }}</Tag></template>
            <template v-else-if="column.key === 'enabled'"><Tag :color="Number(record.status) === 1 ? 'success' : 'default'">{{ Number(record.status) === 1 ? '启用' : '禁用' }}</Tag></template>
            <template v-else-if="column.key === 'ids'"><div class="max-w-[220px] truncate text-xs">草稿：{{ record.draft_media_id || '-' }}</div><div class="max-w-[220px] truncate text-xs">发布：{{ record.publish_id || '-' }}</div></template>
            <template v-else-if="column.key === 'action'">
              <CrudTableActions :actions="articleActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </Card>

    <AppDrawer
      :confirm-loading="saving || editorUploading"
      :open="modalOpen"
      :title="editingId ? '编辑文章' : '新增文章'"
      destroy-on-close
      ok-text="确定"
      width-size="wide"
      @close="closeArticleDrawer"
      @ok="save"
    >
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :md="8" :span="24"><FormItem label="账号 ID"><InputNumber v-model:value="form.account_id" :min="1" class="w-full" /></FormItem></Col>
          <Col :md="8" :span="24"><FormItem label="作者"><Input v-model:value="form.author" :maxlength="80" /></FormItem></Col>
          <Col :md="8" :span="24"><FormItem label="状态"><Select v-model:value="form.status"><SelectOption :value="1">启用</SelectOption><SelectOption :value="0">禁用</SelectOption></Select></FormItem></Col>
          <Col :span="24"><FormItem label="标题"><Input v-model:value="form.title" :maxlength="180" /></FormItem></Col>
          <Col :md="12" :span="24">
            <FormItem label="封面图片" extra="封面图片上传统一走系统文件上传入口；上传微信草稿仍需填写微信官方封面 MediaID。">
              <AdminImageUpload
                v-model="coverFieldValue"
                :allow-select-existing="true"
                :clearable="true"
                button-text="上传/选择封面"
                scene="image"
              />
            </FormItem>
          </Col>
          <Col :md="12" :span="24"><FormItem label="封面 MediaID" extra="请先在素材管理上传图片永久素材，或同步官方素材后填入微信 MediaID。"><Input v-model:value="form.thumb_media_id" placeholder="微信官方封面 MediaID" /></FormItem></Col>
          <Col :span="24"><FormItem label="原文链接"><Input v-model:value="form.content_source_url" placeholder="可选，填写后会提交给微信图文原文链接" /></FormItem></Col>
          <Col :span="24"><FormItem label="摘要"><Textarea v-model:value="form.digest" :rows="2" :maxlength="500" /></FormItem></Col>
          <Col :span="24">
            <FormItem label="正文内容" extra="正文图片和视频统一走系统上传入口，避免 base64 大字段和绕过文件治理；可选择或粘贴图片/视频。">
              <AdminRichTextEditor
                v-model="form.content"
                :allow-video="true"
                :uploadable="canUploadSystemFile"
                :visible="modalOpen"
                description="支持标题、样式、链接、表格、代码块、图片和视频；上传媒体会自动插入正文。"
                footer-text="可通过工具栏上传或直接粘贴剪贴板图片/视频；视频建议使用 mp4、webm 等浏览器可播放格式。"
                placeholder="请输入图文正文，可通过工具栏选择或粘贴图片/视频。"
                title="正文富文本"
                @uploading-change="editorUploading = $event"
              />
            </FormItem>
          </Col>
        </Row>
      </Form>
    </AppDrawer>
  </Page>
</template>

<script setup lang="ts">
import type { CrudFilterSummaryItem, UploadAsset, UploadFieldValue } from '@vben/common-ui';
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import { AdminImageUpload, AdminRichTextEditor, buildCrudTableLocale, CrudFilterSummary, CrudStatCards, CrudTableHeader, Page } from '@vben/common-ui';
import { Button, Card, Col, Form, FormItem, Input, InputNumber, message, Row, Select, SelectOption, Space, Table, Tag, Textarea } from 'ant-design-vue';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import AppDrawer from '#/components/app-drawer.vue';

const { hasAccessByCodes } = useAccess();
const canSave = computed(() => hasAccessByCodes(['wechat.client.article.save']));
const canUploadDraft = computed(() => hasAccessByCodes(['wechat.client.article.upload-draft']));
const canPublish = computed(() => hasAccessByCodes(['wechat.client.article.publish']));
const canQuery = computed(() => hasAccessByCodes(['wechat.client.article.query']));
const canDelete = computed(() => hasAccessByCodes(['wechat.client.article.delete']));
const canUploadSystemFile = computed(() => hasAccessByCodes(['system.file.upload']));
const keyword = ref('');
const accountId = ref<number>();
const publishStatus = ref<string>();
const statusFilter = ref<number>();
const loading = ref(false);
const saving = ref(false);
const editorUploading = ref(false);
const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const coverAsset = ref<UploadAsset | null>(null);
const items = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const form = reactive<any>(defaultForm());
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([articleActions({})], { maxWidth: 220 }));
const columns = computed<any[]>(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '账号', dataIndex: 'account_id', width: 90 },
  { title: '标题', key: 'title', width: 300 },
  { title: '发布状态', key: 'status', dataIndex: 'publish_status', width: 120 },
  { title: '状态', key: 'enabled', dataIndex: 'status', width: 100 },
  { title: '官方标识', key: 'ids', width: 260 },
  { title: '更新时间', dataIndex: 'updated_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' },
]);
const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1500 }));

function articleActions(record: any) {
  return [
    { label: '编辑', visible: canSave.value, onClick: () => openEdit(record) },
    { label: '上传草稿', visible: canUploadDraft.value, confirmTitle: '确认上传该文章为微信草稿？', onClick: () => uploadDraft(record) },
    { label: '发布', visible: canPublish.value, confirmTitle: '确认提交微信图文发布任务？', confirmContent: '发布结果需稍后查询确认。', onClick: () => publishArticle(record) },
    { label: '查状态', visible: canQuery.value, confirmTitle: '确认同步微信发布状态？', onClick: () => queryArticle(record) },
    { label: '删除', visible: canDelete.value, danger: true, confirmTitle: '确认删除该文章？', onClick: () => deleteRow(record.id) },
  ];
}

const coverFieldValue = computed<UploadFieldValue>({
  get: () => coverAsset.value,
  set: (value) => {
    const asset = Array.isArray(value) ? (value[0] ?? null) : value;
    coverAsset.value = asset;
    form.thumb_url = asset?.url || asset?.preview_url || '';
  },
});

function defaultForm() {
  return { account_id: accountId.value, title: '', author: '', thumb_media_id: '', thumb_url: '', digest: '', content_source_url: '', content: '', status: 1 };
}

const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => {
  const filters: CrudFilterSummaryItem[] = [];
  if (keyword.value.trim()) {
    filters.push({ label: '关键字', value: keyword.value.trim() });
  }
  if (accountId.value) {
    filters.push({ label: '账号 ID', value: String(accountId.value) });
  }
  if (publishStatus.value) {
    filters.push({ label: '发布状态', value: statusLabel(publishStatus.value) });
  }
  if (statusFilter.value !== undefined) {
    filters.push({ label: '状态', value: Number(statusFilter.value) === 1 ? '启用' : '禁用' });
  }
  return filters;
});

const summaryCards = computed(() => {
  const uploaded = items.value.filter((item) => String(item.draft_media_id || '').trim() !== '').length;
  const published = items.value.filter((item) => String(item.publish_status || '') === 'publish_success').length;
  const enabled = items.value.filter((item) => Number(item.status) === 1).length;
  return [
    { label: '文章总数', value: String(pagination.total), desc: '当前筛选条件下的图文文章数量', icon: 'i-lucide-newspaper', tone: 'primary' as const },
    { label: '本页已上传', value: String(uploaded), desc: '当前页已有微信草稿 MediaID 的文章', icon: 'i-lucide-file-check-2', tone: 'success' as const },
    { label: '发布成功', value: String(published), desc: '当前页已确认发布成功的文章', icon: 'i-lucide-send', tone: 'info' as const },
    { label: '本页启用', value: String(enabled), desc: '当前页启用状态文章', icon: 'i-lucide-circle-check', tone: 'warning' as const },
  ];
});

function statusLabel(v: string) { return ({ draft: '本地草稿', draft_uploaded: '已上传草稿', publishing: '发布中', publish_success: '发布成功' } as Record<string,string>)[v] || v || '本地草稿'; }
function statusColor(v: string) { return v === 'publish_success' ? 'success' : v === 'publishing' ? 'processing' : v === 'draft_uploaded' ? 'blue' : 'default'; }
async function load() { if (loading.value) return; loading.value = true; try { const data = await requestClient.get<any>('wechat-client/article/index', { params: { page: pagination.current, pageSize: pagination.pageSize, keyword: keyword.value, account_id: accountId.value, publish_status: publishStatus.value, status: statusFilter.value } }); items.value = data?.items || []; pagination.total = data?.pageInfo?.total || 0; } finally { loading.value = false; } }
function handleSearch() { pagination.current = 1; load(); }
function handleReset() { keyword.value = ''; accountId.value = undefined; publishStatus.value = undefined; statusFilter.value = undefined; handleSearch(); }
function openCreate() { editingId.value = null; Object.assign(form, defaultForm()); coverAsset.value = null; editorUploading.value = false; modalOpen.value = true; }
function openEdit(record: any) { editingId.value = record.id; Object.assign(form, { ...defaultForm(), ...record, content: record.content || '' }); coverAsset.value = form.thumb_url ? createUploadAsset(form.thumb_url) : null; editorUploading.value = false; modalOpen.value = true; }
async function save() { if (editorUploading.value) { message.warning('媒体上传中，请稍后保存'); return; } saving.value = true; try { if (editingId.value) await requestClient.put(`wechat-client/article/update/${editingId.value}`, form); else await requestClient.post('wechat-client/article/create', form); message.success('保存成功'); modalOpen.value = false; await load(); } finally { saving.value = false; } }
function closeArticleDrawer() { modalOpen.value = false; editorUploading.value = false; }
async function uploadDraft(record: any) { await requestClient.post(`wechat-client/article/upload-draft/${record.id}`); message.success('草稿已上传'); await load(); }
async function publishArticle(record: any) { await requestClient.post(`wechat-client/article/publish/${record.id}`); message.success('发布已提交'); await load(); }
async function queryArticle(record: any) { await requestClient.get(`wechat-client/article/query/${record.id}`); message.success('状态已同步'); await load(); }
async function deleteRow(id: number) { await requestClient.delete(`wechat-client/article/delete/${id}`); message.success('删除成功'); await load(); }
function onTableChange(pag: any) { pagination.current = pag.current; pagination.pageSize = pag.pageSize; load(); }

function createUploadAsset(url: string): UploadAsset {
  const name = getFileName(url);
  return {
    id: 0,
    url,
    preview_url: url,
    download_url: url,
    hash: null,
    suffix: name.includes('.') ? name.split('.').pop() || '' : '',
    origin_name: name,
    object_name: name,
    storage_mode: 0,
    storage_path: '',
    mime_type: '',
    size_byte: 0,
    size_info: '',
  };
}

function getFileName(url: string) {
  try {
    const pathname = new URL(url).pathname;
    return pathname.split('/').filter(Boolean).pop() || 'image';
  } catch {
    return String(url || '').split('/').filter(Boolean).pop() || 'image';
  }
}

onMounted(load);
</script>
