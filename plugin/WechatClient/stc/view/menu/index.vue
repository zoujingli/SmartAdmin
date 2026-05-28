<template>
  <Page title="菜单发布">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="canSaveMenu" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增方案</Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <CrudStatCards class="mb-5" :items="summaryCards" />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
        <Row class="crud-search-grid" :gutter="[16, 16]">
          <Col :xs="24" :sm="12" :xl="6"><SearchField label="方案名称"><Input v-model:value="keyword" allow-clear placeholder="请输入菜单方案名称" /></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="4"><SearchField label="接口账号"><InputNumber v-model:value="accountId" :min="1" class="w-full" placeholder="账号 ID" /></SearchField></Col>
          <Col :xs="24" :sm="12" :xl="4"><SearchField label="当前状态"><Select v-model:value="statusFilter" allow-clear class="w-full" placeholder="请选择"><SelectOption :value="1">启用</SelectOption><SelectOption :value="0">禁用</SelectOption></Select></SearchField></Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6"><Space wrap><Button type="primary" :loading="loading" @click="handleSearch">搜索</Button><Button :disabled="loading" @click="handleReset">重置</Button></Space></Col>
        </Row>
        <CrudFilterSummary class="mt-4" :items="activeFilterItems" empty-text="当前显示全部菜单方案，可按方案名称、账号 ID 或启用状态筛选。" />
      </Card>

      <Card>
        <CrudTableHeader title="菜单方案" description="可视化维护公众号自定义菜单，支持关联本地素材、文章、回复规则和外部链接。" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="columns" :data-source="items" :loading="loading" :locale="buildCrudTableLocale('暂无菜单方案')" :pagination="pagination" :scroll="tableScroll" row-key="id" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'published_at'"><Tag :color="record.published_at ? 'success' : 'default'">{{ record.published_at || '未发布' }}</Tag></template>
            <template v-else-if="column.key === 'status'"><Tag :color="Number(record.status) === 1 ? 'success' : 'default'">{{ Number(record.status) === 1 ? '启用' : '禁用' }}</Tag></template>
            <template v-else-if="column.key === 'count'">{{ countButtons(record.buttons || []) }} 个按钮</template>
            <template v-else-if="column.key === 'action'"><CrudTableActions :actions="menuActions(record)" /></template>
          </template>
        </Table>
      </Card>
    </Card>

    <Drawer
      :open="modalOpen"
      :title="editingId ? '设计菜单方案' : '新增菜单方案'"
      :body-style="{ padding: '20px 24px 8px' }"
      :width="popupWidth.wide"
      placement="right"
      @close="modalOpen = false"
    >
      <Alert class="mb-4" type="info" show-icon :message="officialMenuLimitTip" />
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :md="8" :span="24"><FormItem label="账号 ID"><InputNumber v-model:value="form.account_id" :min="1" class="w-full" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="方案名称"><Input v-model:value="form.name" :maxlength="120" /></FormItem></Col>
          <Col :md="4" :span="24"><FormItem label="状态"><Select v-model:value="form.status"><SelectOption :value="1">启用</SelectOption><SelectOption :value="0">禁用</SelectOption></Select></FormItem></Col>
        </Row>
      </Form>

      <Row :gutter="[16, 16]">
        <Col :lg="10" :span="24">
          <Card size="small" class="menu-designer-card">
            <template #title>
              <Space size="small" wrap>
                <span>菜单预览</span>
                <Tag color="blue">{{ form.buttons.length }}/{{ MENU_LIMITS.topMax }} 一级菜单</Tag>
                <Tag :color="previewMode === 'simulate' ? 'success' : 'default'">{{ previewMode === 'simulate' ? '模拟操作' : '编辑配置' }}</Tag>
              </Space>
            </template>
            <template #extra>
              <Space size="small">
                <Button size="small" :type="previewMode === 'edit' ? 'primary' : 'default'" @click="setPreviewMode('edit')">编辑</Button>
                <Button size="small" :type="previewMode === 'simulate' ? 'primary' : 'default'" @click="setPreviewMode('simulate')">模拟</Button>
              </Space>
            </template>
            <div class="menu-designer-phone">
              <div class="menu-designer-screen">
                <div class="menu-designer-status">
                  <span class="menu-designer-signal">
                    <i />
                    <i />
                    <i />
                    <i />
                    <i />
                    <span class="menu-designer-wifi" />
                  </span>
                  <span class="menu-designer-status-icons">
                    <span>100%</span>
                    <span class="menu-designer-battery" />
                  </span>
                </div>
                <div class="menu-designer-screen-header">
                  <span class="menu-designer-back">
                    <span class="menu-designer-back-icon" />返回
                  </span>
                  <span class="menu-designer-title">公众号</span>
                  <span class="menu-designer-user-icon i-lucide-user-round" />
                </div>
                <div class="menu-designer-screen-body" @click="handlePreviewBlankClick">
                  <template v-if="simulatedAction">
                    <div class="menu-designer-time">刚刚</div>
                    <div class="menu-designer-user-bubble">点击「{{ simulatedAction.name }}」</div>
                    <div class="menu-designer-result-card" :class="`menu-designer-result-${simulatedAction.tone}`">
                      <div class="menu-designer-result-title">{{ simulatedAction.title }}</div>
                      <div class="menu-designer-result-desc">{{ simulatedAction.description }}</div>
                      <div v-if="simulatedAction.detail" class="menu-designer-result-detail">{{ simulatedAction.detail }}</div>
                    </div>
                  </template>
                  <template v-else>
                    <div v-if="!hasVisibleSubList" class="menu-designer-empty-tip">{{ previewMode === 'simulate' ? '模拟模式：点击底部菜单查看真实展开与触发效果' : '编辑模式：点击菜单编辑，绿色边框表示当前选中项' }}</div>
                  </template>
                </div>
              </div>
              <div class="menu-designer-bar">
                <div class="menu-designer-keyboard" :class="{ active: keyboardMode }" @click="toggleKeyboardMode">
                  <span :class="keyboardMode ? 'i-lucide-menu' : 'i-lucide-keyboard'" />
                </div>
                <div v-if="keyboardMode && previewMode === 'simulate'" class="menu-designer-inputbar">
                  <div class="menu-designer-input-placeholder">输入消息...</div>
                  <button type="button" class="menu-designer-send-button" disabled>发送</button>
                </div>
                <div v-else class="menu-designer-slots" :class="{ editing: previewMode === 'edit', simulating: previewMode === 'simulate' }">
                  <div
                    v-for="(button, index) in form.buttons"
                    :key="button.uid"
                    class="menu-designer-top"
                    :class="{ active: isTopButtonActive(Number(index)), 'has-children': (button.children || []).length > 0 }"
                    :draggable="previewMode === 'edit'"
                    @click="handleTopButtonClick(Number(index))"
                    @dragstart="startDrag(`${index}`)"
                    @dragover.prevent
                    @drop="dropButton(`${index}`)"
                  >
                    <div v-if="shouldShowSubList(Number(index))" class="menu-designer-sub-list">
                      <div
                        v-for="(child, childIndex) in button.children || []"
                        :key="child.uid"
                        class="menu-designer-sub"
                        :class="{ active: isSubButtonActive(Number(index), Number(childIndex)) }"
                        :draggable="previewMode === 'edit'"
                        @click.stop="handleSubButtonClick(Number(index), Number(childIndex))"
                        @dragstart.stop="startDrag(`${index}-${childIndex}`)"
                        @dragover.prevent
                        @drop.stop="dropButton(`${index}-${childIndex}`)"
                      >{{ child.name || '请输入名称' }}</div>
                      <button
                        v-if="previewMode === 'edit' && (button.children || []).length < MENU_LIMITS.subMax"
                        type="button"
                        class="menu-designer-sub menu-designer-sub-add"
                        @click.stop="addSubButton(Number(index))"
                      >+</button>
                    </div>
                    <span v-if="(button.children || []).length" class="menu-designer-menu-dot" />
                    <span class="menu-designer-top-name">{{ button.name || '请输入名称' }}</span>
                  </div>
                  <Button v-if="previewMode === 'edit' && form.buttons.length < MENU_LIMITS.topMax" class="menu-designer-add" size="small" @click="addTopButton">
                    <span class="menu-designer-add-icon">+</span>
                    <span class="menu-designer-add-text">菜单</span>
                  </Button>
                  <div v-for="slot in previewMode === 'edit' ? emptyTopSlots : 0" :key="`empty-${slot}`" class="menu-designer-placeholder">未配置</div>
                </div>
              </div>
            </div>
            <div class="menu-designer-help">{{ previewMode === 'simulate' ? '模拟模式下点击一级菜单会展开/收起二级菜单；点击无子菜单的按钮或二级菜单会在会话区展示动作结果。' : '点击底部 + 创建一级菜单；选中主菜单后点击浮层 + 或右侧“子菜单”创建二级菜单。' }}</div>
          </Card>
        </Col>
        <Col :lg="14" :span="24">
          <Card size="small" class="menu-editor-card">
            <template #title>
              <Space size="small" wrap>
                <span>{{ selectedPanelTitle }}</span>
                <Tag v-if="selectedButton" color="processing">{{ selectedLocationText }}</Tag>
                <Tag v-if="selectedButton" :color="selectedNameTooLong ? 'error' : 'default'">{{ selectedNameWidth }}/{{ selectedNameLimit }} 宽度</Tag>
                <Tag v-if="selectedIsParentContainer" color="warning">容器菜单</Tag>
              </Space>
            </template>
            <template #extra>
              <Button
                v-if="selectedIsTopButton"
                size="small"
                type="primary"
                ghost
                :disabled="!canAddSubForSelected"
                @click="addSubForSelected"
              >
                <span class="i-lucide-plus" />子菜单
              </Button>
            </template>
            <Empty v-if="!selectedButton" description="请选择左侧菜单按钮" />
            <Form v-else :model="selectedButton" layout="vertical">
              <Alert
                v-if="selectedIsTopButton && !selectedIsParentContainer"
                class="mb-4"
                type="info"
                show-icon
                message="一级菜单没有子菜单时可直接配置动作；添加子菜单后，一级菜单会变为展开容器，动作字段不会发布到微信。"
              />
              <Alert
                v-if="selectedIsParentContainer"
                class="mb-4"
                type="warning"
                show-icon
                message="当前主菜单包含子菜单，发布到微信时仅作为二级菜单容器；请点击上方子菜单项编辑具体跳转或回复动作。"
              />
              <Row :gutter="[16, 0]">
                <Col :md="12" :span="24">
                  <FormItem label="按钮名称" :validate-status="selectedNameTooLong ? 'error' : undefined" :help="selectedNameHelp">
                    <Input
                      v-model:value="selectedButton.name"
                      allow-clear
                      :placeholder="selectedIsTopButton ? '请输入一级菜单名称' : '请输入二级菜单名称'"
                      :status="selectedNameTooLong ? 'error' : undefined"
                    />
                  </FormItem>
                </Col>
                <template v-if="!selectedIsParentContainer">
                  <Col :md="12" :span="24"><FormItem label="按钮类型"><Select v-model:value="selectedButton.type"><SelectOption value="view">网页链接</SelectOption><SelectOption value="local_media">本地素材</SelectOption><SelectOption value="local_article">本地文章</SelectOption><SelectOption value="reply">点击回复</SelectOption><SelectOption value="json">高级 JSON</SelectOption></Select></FormItem></Col>
                  <Col v-if="selectedButton.type === 'view'" :span="24"><FormItem label="网页 URL"><Input v-model:value="selectedButton.url" placeholder="https://" /></FormItem></Col>
                  <Col v-else-if="selectedButton.type === 'local_media'" :span="24"><FormItem label="关联素材"><Select v-model:value="selectedButton.media_local_id" show-search option-filter-prop="label"><SelectOption v-for="item in mediaOptions" :key="item.id" :label="`${item.name} (${item.media_type})`" :value="item.id">{{ item.name }} - {{ item.media_type }} - {{ item.media_id || '未上传' }}</SelectOption></Select></FormItem></Col>
                  <Col v-else-if="selectedButton.type === 'local_article'" :span="24"><FormItem label="关联文章"><Select v-model:value="selectedButton.article_id" show-search option-filter-prop="label"><SelectOption v-for="item in articleOptions" :key="item.id" :label="item.title" :value="item.id">{{ item.title }} - {{ item.draft_media_id || '未上传草稿' }}</SelectOption></Select></FormItem></Col>
                  <Col v-else-if="selectedButton.type === 'reply'" :span="24"><FormItem label="菜单点击回复规则"><Select v-model:value="selectedButton.reply_rule_id" show-search option-filter-prop="label"><SelectOption v-for="item in replyOptions" :key="item.id" :label="`#${item.id} ${item.keyword || '菜单点击'}`" :value="item.id">#{{ item.id }} {{ item.keyword || '菜单点击回复' }} - {{ item.reply_type }}</SelectOption></Select></FormItem></Col>
                  <Col v-else :span="24"><FormItem label="高级 JSON"><Textarea v-model:value="selectedButton.raw_json" :rows="8" placeholder='例如 {"type":"click","name":"按钮","key":"KEY"}' /></FormItem></Col>
                </template>
              </Row>
              <Space wrap class="menu-editor-actions">
                <Button :disabled="!canCopySelected" @click="copySelected">复制</Button>
                <Button :disabled="!canMoveSelectedUp" @click="moveSelected(-1)">上移</Button>
                <Button :disabled="!canMoveSelectedDown" @click="moveSelected(1)">下移</Button>
                <Popconfirm title="确认删除当前菜单按钮？删除后不可恢复。" @confirm="removeSelected">
                  <Button danger>删除</Button>
                </Popconfirm>
              </Space>
            </Form>
          </Card>
        </Col>
      </Row>
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
import { buildCrudTableLocale, CrudFilterSummary, CrudStatCards, CrudTableHeader, Page } from '@vben/common-ui';
import { Alert, Button, Card, Col, Drawer, Empty, Form, FormItem, Input, InputNumber, message, Popconfirm, Row, Select, SelectOption, Space, Table, Tag, Textarea } from 'ant-design-vue';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { popupWidth } from '#/utils/popup';

type PreviewMode = 'edit' | 'simulate';
type SimulatedActionTone = 'article' | 'empty' | 'json' | 'media' | 'reply' | 'view';

interface SimulatedAction {
  description: string;
  detail?: string;
  name: string;
  path: string;
  title: string;
  tone: SimulatedActionTone;
}

const { hasAccessByCodes } = useAccess();
const canSaveMenu = computed(() => hasAccessByCodes(['wechat.client.menu.save']));
const canPublishMenu = computed(() => hasAccessByCodes(['wechat.client.menu.publish']));
const canDeleteMenu = computed(() => hasAccessByCodes(['wechat.client.menu.delete']));
const keyword = ref('');
const accountId = ref<number>();
const statusFilter = ref<number>();
const loading = ref(false);
const saving = ref(false);
const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const selectedPath = ref('');
const dragPath = ref('');
const previewMode = ref<PreviewMode>('edit');
const openedTopIndex = ref<number | null>(null);
const keyboardMode = ref(false);
const simulatedAction = ref<SimulatedAction | null>(null);
const items = ref<any[]>([]);
const mediaOptions = ref<any[]>([]);
const articleOptions = ref<any[]>([]);
const replyOptions = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const form = reactive<any>({ account_id: undefined, name: '', status: 1, buttons: [] });
const MENU_LIMITS = {
  subMax: 5,
  subNameWidth: 16,
  topMax: 3,
  topNameWidth: 8,
} as const;
const officialMenuLimitTip = '微信官方自定义菜单限制：一级菜单最多 3 个，每个一级菜单最多 5 个二级菜单；一级菜单名称最多 4 个汉字，二级菜单名称最多 8 个汉字。素材、文章、回复规则会在发布时转换为微信官方菜单结构。';
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth([menuActions({})], { maxWidth: 220 }));
const columns = computed<any[]>(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '账号ID', dataIndex: 'account_id', width: 90 },
  { title: '方案名称', dataIndex: 'name', width: 220 },
  { title: '按钮数', key: 'count', width: 100 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
  { title: '发布时间', key: 'published_at', dataIndex: 'published_at', width: 200 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' },
]);
const tableScroll = computed(() => buildTableScrollX(columns.value, { minWidth: 1200 }));
function menuActions(record: any) {
  return [
    { label: '设计', visible: canSaveMenu.value, onClick: () => openEdit(record) },
    { label: '发布', visible: canPublishMenu.value, confirmTitle: '确认发布该菜单方案到微信？', confirmContent: '发布后将覆盖线上自定义菜单。', onClick: () => publish(record.id) },
    { label: '删除', visible: canDeleteMenu.value, danger: true, confirmTitle: '确认删除该菜单方案？', onClick: () => deleteRow(record.id) },
  ];
}
const selectedButton = computed<any | null>(() => getButtonByPath(selectedPath.value));
const selectedIsTopButton = computed(() => getPathParts(selectedPath.value).length === 1);
const selectedIsParentContainer = computed(() => selectedIsTopButton.value && (selectedButton.value?.children || []).length > 0);
const selectedTopIndex = computed(() => getPathParts(selectedPath.value)[0] ?? -1);
const selectedIndex = computed(() => getIndex(selectedPath.value));
const selectedSiblingCount = computed(() => getSiblingList(selectedPath.value).length);
const selectedNameWidth = computed(() => menuNameWidth(String(selectedButton.value?.name || '')));
const selectedNameLimit = computed(() => selectedIsTopButton.value ? MENU_LIMITS.topNameWidth : MENU_LIMITS.subNameWidth);
const selectedNameTooLong = computed(() => Boolean(selectedButton.value) && selectedNameWidth.value > selectedNameLimit.value);
const selectedNameHelp = computed(() => {
  const limitText = selectedIsTopButton.value ? '一级菜单最多 4 个汉字或 8 个英文字符' : '二级菜单最多 8 个汉字或 16 个英文字符';
  return `${limitText}，当前展示宽度 ${selectedNameWidth.value}/${selectedNameLimit.value}`;
});
const selectedPanelTitle = computed(() => selectedButton.value ? (selectedIsTopButton.value ? '主菜单属性' : '子菜单属性') : '按钮属性');
const selectedLocationText = computed(() => {
  const parts = getPathParts(selectedPath.value);
  const topIndex = parts[0] ?? -1;
  const childIndex = parts[1] ?? -1;
  if (parts.length === 1) return `第 ${topIndex + 1} 个一级菜单`;
  if (parts.length === 2) return `第 ${topIndex + 1}-${childIndex + 1} 个二级菜单`;
  return '';
});
const emptyTopSlots = computed(() => Math.max(MENU_LIMITS.topMax - form.buttons.length - (form.buttons.length < MENU_LIMITS.topMax ? 1 : 0), 0));
const hasVisibleSubList = computed(() => form.buttons.some((_button: any, index: number) => shouldShowSubList(index)));
const canAddSubForSelected = computed(() => selectedIsTopButton.value && selectedTopIndex.value >= 0 && ((selectedButton.value?.children || []).length < MENU_LIMITS.subMax));
const canCopySelected = computed(() => Boolean(selectedButton.value) && selectedSiblingCount.value < getSiblingLimit(selectedPath.value));
const canMoveSelectedUp = computed(() => selectedIndex.value > 0);
const canMoveSelectedDown = computed(() => selectedIndex.value >= 0 && selectedIndex.value < selectedSiblingCount.value - 1);
const activeFilterItems = computed<CrudFilterSummaryItem[]>(() => {
  const filters: CrudFilterSummaryItem[] = [];
  if (keyword.value.trim()) {
    filters.push({ label: '关键字', value: keyword.value.trim() });
  }
  if (accountId.value) {
    filters.push({ label: '账号 ID', value: String(accountId.value) });
  }
  if (statusFilter.value !== undefined) {
    filters.push({ label: '状态', value: Number(statusFilter.value) === 1 ? '启用' : '禁用' });
  }
  return filters;
});
const summaryCards = computed(() => {
  const published = items.value.filter((item) => String(item.published_at || '').trim() !== '').length;
  const enabled = items.value.filter((item) => Number(item.status) === 1).length;
  const buttons = items.value.reduce((sum, item) => sum + countButtons(item.buttons || []), 0);
  return [
    { label: '方案总数', value: String(pagination.total), desc: '当前筛选条件下的菜单方案数量', icon: 'i-lucide-list-tree', tone: 'primary' as const },
    { label: '本页已发布', value: String(published), desc: '当前页已经发布到微信的方案', icon: 'i-lucide-send', tone: 'success' as const },
    { label: '本页启用', value: String(enabled), desc: '当前页启用状态菜单方案', icon: 'i-lucide-circle-check', tone: 'info' as const },
    { label: '按钮合计', value: String(buttons), desc: '当前页菜单按钮数量合计', icon: 'i-lucide-mouse-pointer-click', tone: 'warning' as const },
  ];
});
function newButton(name = ''): any { return { uid: buildButtonUid(), name, type: 'view', url: '', children: [] }; }
function ensureButtons(buttons: any[]): any[] {
  return (buttons || []).map((item) => ({
    ...item,
    uid: item.uid || buildButtonUid(),
    type: item.type || 'view',
    children: ensureButtons(item.children || item.sub_button || []),
  }));
}
function countButtons(buttons: any[]): number { return (buttons || []).reduce((sum, item) => sum + 1 + countButtons(item.children || item.sub_button || []), 0); }
async function load() { if (loading.value) return; loading.value = true; try { const data = await requestClient.get<any>('wechat-client/menu/index', { params: { page: pagination.current, pageSize: pagination.pageSize, keyword: keyword.value, account_id: accountId.value, status: statusFilter.value } }); items.value = data?.items || []; pagination.total = data?.pageInfo?.total || 0; } finally { loading.value = false; } }
async function loadResources(accountId?: number) {
  const params = { page: 1, pageSize: 100, account_id: accountId };
  const [media, articles, replies] = await Promise.all([
    requestClient.get<any>('wechat-client/media/index', { params }),
    requestClient.get<any>('wechat-client/article/index', { params }),
    requestClient.get<any>('wechat-client/reply/index', { params: { ...params, rule_type: 'menu_click' } }),
  ]);
  mediaOptions.value = media?.items || [];
  articleOptions.value = articles?.items || [];
  replyOptions.value = replies?.items || [];
}
watch(() => form.account_id, (value, oldValue) => { if (modalOpen.value && value !== oldValue) void loadResources(Number(value || 0) || undefined); });
function handleSearch() { pagination.current = 1; load(); }
function handleReset() { keyword.value = ''; accountId.value = undefined; statusFilter.value = undefined; handleSearch(); }
async function openCreate() { editingId.value = null; Object.assign(form, { account_id: undefined, name: '', status: 1, buttons: [newButton()] }); selectedPath.value = '0'; resetPreviewState('edit'); modalOpen.value = true; await loadResources(); }
async function openEdit(record: any) { editingId.value = record.id; Object.assign(form, { account_id: record.account_id, name: record.name, status: record.status, buttons: ensureButtons(record.buttons || []) }); selectedPath.value = form.buttons.length ? '0' : ''; resetPreviewState('edit'); modalOpen.value = true; await loadResources(record.account_id); }

function setPreviewMode(mode: PreviewMode) {
  resetPreviewState(mode);
}

function resetPreviewState(mode: PreviewMode = previewMode.value) {
  // 预览器有“配置编辑”和“真实模拟”两套交互，切换模式时必须清理展开层、键盘和动作反馈，避免状态串扰。
  previewMode.value = mode;
  openedTopIndex.value = null;
  keyboardMode.value = false;
  simulatedAction.value = null;
}

function toggleKeyboardMode() {
  if (previewMode.value !== 'simulate') return;
  keyboardMode.value = !keyboardMode.value;
  openedTopIndex.value = null;
  simulatedAction.value = null;
}

function handleTopButtonClick(index: number) {
  // 编辑模式点击一级菜单用于选中属性；模拟模式按微信行为处理：有子菜单则展开/收起，无子菜单则直接触发动作。
  if (previewMode.value === 'edit') {
    selectButton(`${index}`);
    return;
  }

  keyboardMode.value = false;
  const button = form.buttons[index];
  if (!button) return;
  const children = button.children || [];
  if (children.length > 0) {
    openedTopIndex.value = openedTopIndex.value === index ? null : index;
    simulatedAction.value = null;
    return;
  }
  simulateMenuAction(`${index}`, button);
}

function handleSubButtonClick(index: number, childIndex: number) {
  // 二级菜单在微信端是叶子动作，模拟点击后关闭浮层并在会话区展示将触发的菜单效果。
  if (previewMode.value === 'edit') {
    selectButton(`${index}-${childIndex}`);
    return;
  }

  const child = form.buttons[index]?.children?.[childIndex];
  if (!child) return;
  simulateMenuAction(`${index}-${childIndex}`, child);
  openedTopIndex.value = null;
}

function handlePreviewBlankClick() {
  // 模拟模式点击聊天空白区域会收起二级菜单，贴近微信里点击会话区取消菜单浮层的真实行为；编辑模式保留当前选中状态。
  if (previewMode.value !== 'simulate') return;
  openedTopIndex.value = null;
}

function shouldShowSubList(index: number) {
  const children = form.buttons[index]?.children || [];
  if (previewMode.value === 'simulate') {
    return !keyboardMode.value && openedTopIndex.value === index && children.length > 0;
  }
  return isTopButtonActive(index);
}

function addTopButton() {
  if (form.buttons.length >= MENU_LIMITS.topMax) return message.warning('微信官方限制一级菜单最多 3 个');
  form.buttons.push(newButton());
  selectedPath.value = `${form.buttons.length - 1}`;
}

function addSubButton(index: number) {
  const top = form.buttons[index];
  if (!top) return;
  top.children ||= [];
  if (top.children.length >= MENU_LIMITS.subMax) return message.warning('微信官方限制每个一级菜单最多 5 个二级菜单');
  top.children.push(newButton());
  selectedPath.value = `${index}-${top.children.length - 1}`;
}

function addSubForSelected() {
  if (selectedTopIndex.value < 0) return;
  addSubButton(selectedTopIndex.value);
}
function selectButton(path: string) { selectedPath.value = path; openedTopIndex.value = null; keyboardMode.value = false; simulatedAction.value = null; }
function isTopButtonActive(index: number) { return previewMode.value === 'simulate' ? openedTopIndex.value === index : selectedPath.value === `${index}` || selectedPath.value.startsWith(`${index}-`); }
function isSubButtonActive(index: number, childIndex: number) { return previewMode.value === 'simulate' ? simulatedAction.value?.path === `${index}-${childIndex}` : selectedPath.value === `${index}-${childIndex}`; }
function getPathParts(path: string): number[] {
  // 空路径表示未选中任何菜单，不能让 Number('') 被误解析成 0 后选中第一个主菜单。
  const normalized = String(path || '').trim();
  if (!normalized) return [];
  return normalized
    .split('-')
    .filter((part) => /^\d+$/.test(part))
    .map((part) => Number(part));
}
function getButtonByPath(path: string): any | null {
  if (!path) return null;
  const parts = getPathParts(path);
  const topIndex = parts[0] ?? -1;
  const top = form.buttons[topIndex];
  if (!top) return null;
  const childIndex = parts[1] ?? -1;
  return parts.length > 1 ? (top.children || [])[childIndex] || null : top;
}
function getSiblingList(path: string): any[] {
  const parts = getPathParts(path);
  const topIndex = parts[0] ?? -1;
  return parts.length > 1 ? (form.buttons[topIndex]?.children || []) : form.buttons;
}
function getIndex(path: string): number {
  const parts = getPathParts(path);
  return parts.length > 0 ? parts[parts.length - 1] ?? -1 : -1;
}
function copySelected() {
  const current = selectedButton.value;
  if (!current) return;
  const list = getSiblingList(selectedPath.value);
  const index = getIndex(selectedPath.value);
  const limit = getSiblingLimit(selectedPath.value);
  if (list.length >= limit) return message.warning(getLimitMessage(selectedPath.value));
  const copy = cloneButton(current);
  copy.name = buildCopyButtonName(String(copy.name || '菜单'), getPathParts(selectedPath.value).length);
  list.splice(index + 1, 0, copy);
  selectedPath.value = replaceLastPathIndex(selectedPath.value, index + 1);
}

function moveSelected(offset: number) {
  const list = getSiblingList(selectedPath.value);
  const index = getIndex(selectedPath.value);
  const target = index + offset;
  if (index < 0 || target < 0 || target >= list.length) return;
  const [item] = list.splice(index, 1);
  list.splice(target, 0, item);
  selectedPath.value = replaceLastPathIndex(selectedPath.value, target);
}

function removeSelected() {
  const list = getSiblingList(selectedPath.value);
  const index = getIndex(selectedPath.value);
  const parts = getPathParts(selectedPath.value);
  if (index < 0) return;
  list.splice(index, 1);
  const nextIndex = Math.min(index, list.length - 1);
  selectedPath.value = parts.length > 1
    ? (list.length > 0 ? `${parts[0]}-${nextIndex}` : `${parts[0]}`)
    : (list.length > 0 ? `${nextIndex}` : '');
}
function startDrag(path: string) { if (previewMode.value !== 'edit') return; dragPath.value = path; }
function dropButton(path: string) {
  if (previewMode.value !== 'edit') return;
  if (!dragPath.value || dragPath.value === path) {
    dragPath.value = '';
    return;
  }
  const fromList = getSiblingList(dragPath.value);
  const toList = getSiblingList(path);
  if (fromList !== toList) {
    dragPath.value = '';
    return message.warning('仅支持同级菜单拖拽排序');
  }
  const from = getIndex(dragPath.value);
  const to = getIndex(path);
  const [item] = fromList.splice(from, 1);
  toList.splice(to, 0, item);
  selectedPath.value = replaceLastPathIndex(path, to);
  dragPath.value = '';
}
async function save() { if (!validateOfficialMenu()) return; saving.value = true; try { const payload = { ...form, buttons: form.buttons }; if (editingId.value) await requestClient.put(`wechat-client/menu/update/${editingId.value}`, payload); else await requestClient.post('wechat-client/menu/create', payload); message.success('保存成功'); modalOpen.value = false; await load(); } finally { saving.value = false; } }
async function publish(id: number) { await requestClient.post(`wechat-client/menu/publish/${id}`); message.success('发布成功'); await load(); }
async function deleteRow(id: number) { await requestClient.delete(`wechat-client/menu/delete/${id}`); message.success('删除成功'); await load(); }
function onTableChange(pag: any) { pagination.current = pag.current; pagination.pageSize = pag.pageSize; load(); }

function buildButtonUid() {
  return `${Date.now()}-${Math.random()}`;
}

function cloneButton(button: any): any {
  const copy = JSON.parse(JSON.stringify(button || {}));
  const refreshUid = (item: any) => {
    item.uid = buildButtonUid();
    item.children = ensureButtons(item.children || item.sub_button || []).map((child) => {
      refreshUid(child);
      return child;
    });
    delete item.sub_button;
    return item;
  };

  return refreshUid(copy);
}

function getSiblingLimit(path: string) {
  return getPathParts(path).length > 1 ? MENU_LIMITS.subMax : MENU_LIMITS.topMax;
}

function getLimitMessage(path: string) {
  return getPathParts(path).length > 1 ? '微信官方限制每个一级菜单最多 5 个二级菜单' : '微信官方限制一级菜单最多 3 个';
}

function replaceLastPathIndex(path: string, index: number) {
  const parts = path.split('-');
  parts[parts.length - 1] = String(index);
  return parts.join('-');
}

function buildCopyButtonName(name: string, level: number) {
  const suffix = '副本';
  const limit = level > 1 ? MENU_LIMITS.subNameWidth : MENU_LIMITS.topNameWidth;
  const chars = Array.from(name.trim() || (level > 1 ? '子菜单' : '菜单'));
  // 复制菜单时自动压缩名称，避免“菜单 副本”一类默认文案直接超过微信名称宽度限制。
  while (chars.length > 0 && menuNameWidth(`${chars.join('')}${suffix}`) > limit) {
    chars.pop();
  }
  return `${chars.join('') || (level > 1 ? '子菜单' : '菜单')}${suffix}`;
}

function simulateMenuAction(path: string, button: any) {
  // 仅做前端模拟，不真正跳转、发送素材或触发回复；根据当前配置生成接近微信用户点击后的可见反馈。
  keyboardMode.value = false;
  const name = String(button?.name || '菜单');
  const type = String(button?.type || 'view');
  const action: SimulatedAction = {
    description: '当前菜单还没有配置可在微信端触发的动作。',
    name,
    path,
    title: '未配置动作',
    tone: 'empty',
  };

  if (type === 'view') {
    const url = String(button?.url || '').trim();
    Object.assign(action, {
      description: url ? '微信客户端会打开该网页链接。' : '当前按钮类型为网页链接，但还没有填写 URL。',
      detail: url || '请先在右侧填写 https:// 开头的网页 URL',
      title: url ? '打开网页链接' : '网页链接未完成',
      tone: url ? 'view' : 'empty',
    });
  } else if (type === 'local_media') {
    const media = findOption(mediaOptions.value, button?.media_local_id);
    Object.assign(action, {
      description: media ? `${media.name} · ${media.media_type}` : '发布时会把本地素材转换为微信素材消息。',
      detail: media?.media_id ? `微信 media_id：${media.media_id}` : '未选择素材或素材尚未上传到微信',
      title: '发送素材消息',
      tone: 'media',
    });
  } else if (type === 'local_article') {
    const article = findOption(articleOptions.value, button?.article_id);
    Object.assign(action, {
      description: article ? article.title : '发布时会把本地图文转换为微信图文菜单。',
      detail: article?.draft_media_id ? `草稿 media_id：${article.draft_media_id}` : '未选择文章或文章尚未上传草稿',
      title: '打开图文文章',
      tone: 'article',
    });
  } else if (type === 'reply') {
    const reply = findOption(replyOptions.value, button?.reply_rule_id);
    Object.assign(action, {
      description: reply ? `命中规则 #${reply.id} ${reply.keyword || '菜单点击'}` : '发布后点击菜单会触发菜单点击回复规则。',
      detail: reply?.reply_type ? `回复类型：${reply.reply_type}` : '未选择回复规则',
      title: '触发点击回复',
      tone: 'reply',
    });
  } else if (type === 'json') {
    const raw = parseRawJson(button?.raw_json);
    Object.assign(action, {
      description: raw ? `按微信官方 JSON 结构触发 ${raw.type || '自定义'} 动作。` : '高级 JSON 当前无法解析，发布前需要修正格式。',
      detail: raw ? buildRawJsonPreview(raw) : 'JSON 格式错误或为空',
      title: raw ? '高级 JSON 动作' : '高级 JSON 无效',
      tone: raw ? 'json' : 'empty',
    });
  }

  simulatedAction.value = action;
}

function findOption(options: any[], id: any) {
  return (options || []).find((item) => String(item.id) === String(id));
}

function parseRawJson(rawJson: any) {
  if (!rawJson) return null;
  if (typeof rawJson === 'object' && !Array.isArray(rawJson)) return rawJson;
  try {
    const parsed = JSON.parse(String(rawJson));
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
  } catch {
    return null;
  }
}

function buildRawJsonPreview(raw: any) {
  const parts = ['key', 'url', 'media_id', 'appid', 'pagepath']
    .map((field) => raw?.[field] ? `${field}: ${raw[field]}` : '')
    .filter(Boolean);
  return parts.length > 0 ? parts.join('；') : JSON.stringify(raw).slice(0, 120);
}

function validateOfficialMenu() {
  if (form.buttons.length === 0) {
    return failMenuValidation('', '微信官方自定义菜单至少需要 1 个一级菜单');
  }
  if (form.buttons.length > MENU_LIMITS.topMax) {
    return failMenuValidation('0', '微信官方限制一级菜单最多 3 个，请删除多余一级菜单');
  }

  for (let index = 0; index < form.buttons.length; index += 1) {
    if (!validateButton(form.buttons[index], 1, `第 ${index + 1} 个一级菜单`, `${index}`)) {
      return false;
    }
  }

  return true;
}

function failMenuValidation(path: string, errorMessage: string) {
  // 保存前校验失败时同步选中问题菜单，避免用户只看到提示却不知道应该修改哪一项。
  if (path) selectedPath.value = path;
  message.error(errorMessage);
  return false;
}

function validateButton(button: any, level: 1 | 2, label: string, path: string) {
  const name = String(button?.name || '').trim();
  if (!name) {
    return failMenuValidation(path, `${label}名称不能为空`);
  }
  if (menuNameWidth(name) > (level === 1 ? MENU_LIMITS.topNameWidth : MENU_LIMITS.subNameWidth)) {
    return failMenuValidation(path, `${label}名称过长，${level === 1 ? '一级菜单最多 4 个汉字（或 8 个英文字符）' : '二级菜单最多 8 个汉字（或 16 个英文字符）'}`);
  }

  const children = button?.children || button?.sub_button || [];
  if (children.length > 0) {
    if (level !== 1) {
      return failMenuValidation(path, '微信官方自定义菜单最多支持二级菜单');
    }
    if (children.length > MENU_LIMITS.subMax) {
      return failMenuValidation(path, `${label}最多包含 5 个二级菜单，请删除多余子菜单`);
    }

    for (let index = 0; index < children.length; index += 1) {
      if (!validateButton(children[index], 2, `${label}下第 ${index + 1} 个二级菜单`, `${path}-${index}`)) {
        return false;
      }
    }

    return true;
  }

  if (String(button?.type || '') === 'json' && !validateRawJsonButton(button, level, label, path)) {
    return false;
  }
  if (String(button?.type || '') === 'view') {
    const url = String(button?.url || '').trim();
    if (!url) {
      return failMenuValidation(path, `${label}网页链接不能为空`);
    }
    if (!/^https?:\/\//i.test(url)) {
      return failMenuValidation(path, `${label}网页链接必须是完整 URL`);
    }
  }
  if (String(button?.type || '') === 'local_media' && !Number(button?.media_local_id || 0)) {
    return failMenuValidation(path, `${label}关联素材不能为空`);
  }
  if (String(button?.type || '') === 'local_article' && !Number(button?.article_id || 0)) {
    return failMenuValidation(path, `${label}关联文章不能为空`);
  }
  if (String(button?.type || '') === 'reply' && !Number(button?.reply_rule_id || 0)) {
    return failMenuValidation(path, `${label}菜单点击回复规则不能为空`);
  }

  return true;
}

function validateRawJsonButton(button: any, level: 1 | 2, label: string, path: string) {
  let raw: any = button?.raw_json || {};
  if (typeof raw === 'string') {
    try {
      raw = JSON.parse(raw || '{}');
    } catch {
      return failMenuValidation(path, `${label}高级 JSON 格式错误`);
    }
  }
  if (!raw || typeof raw !== 'object' || Array.isArray(raw) || Object.keys(raw).length === 0) {
    return failMenuValidation(path, `${label}高级 JSON 不能为空`);
  }

  return validateRawOfficialButton(raw, level, `${label}高级 JSON`, path);
}

function validateRawOfficialButton(button: any, level: 1 | 2, label: string, path: string) {
  const name = String(button?.name || '').trim();
  if (name && menuNameWidth(name) > (level === 1 ? MENU_LIMITS.topNameWidth : MENU_LIMITS.subNameWidth)) {
    return failMenuValidation(path, `${label}名称过长，${level === 1 ? '一级菜单最多 4 个汉字（或 8 个英文字符）' : '二级菜单最多 8 个汉字（或 16 个英文字符）'}`);
  }

  const rawChildren = button?.sub_button || button?.children || [];
  if (!Array.isArray(rawChildren)) {
    return failMenuValidation(path, `${label}的 sub_button 必须是数组`);
  }
  const children = rawChildren;
  for (let index = 0; index < children.length; index += 1) {
    if (!children[index] || typeof children[index] !== 'object' || Array.isArray(children[index])) {
      return failMenuValidation(path, `${label}下第 ${index + 1} 个子菜单必须是对象`);
    }
  }
  if (children.length === 0) {
    return validateRawLeafButton(button, label, path);
  }
  if (level !== 1) {
    return failMenuValidation(path, `${label}不能包含三级菜单`);
  }
  if (button.type) {
    return failMenuValidation(path, `${label}包含 sub_button 时不能同时配置 type`);
  }
  if (children.length > MENU_LIMITS.subMax) {
    return failMenuValidation(path, `${label}最多包含 5 个二级菜单`);
  }

  for (let index = 0; index < children.length; index += 1) {
    if (!validateRawOfficialButton(children[index], 2, `${label}下第 ${index + 1} 个二级菜单`, path)) {
      return false;
    }
  }

  return true;
}

function validateRawLeafButton(button: any, label: string, path: string) {
  const type = String(button?.type || '').trim().toLowerCase();
  if (!type) {
    return failMenuValidation(path, `${label}需配置 type 或 sub_button`);
  }

  const requiredMap: Record<string, Record<string, string>> = {
    article_id: { article_id: '图文 article_id 不能为空' },
    article_view_limited: { article_id: '图文 article_id 不能为空' },
    click: { key: '事件 KEY 不能为空' },
    location_select: { key: '事件 KEY 不能为空' },
    media_id: { media_id: 'MediaID 不能为空' },
    miniprogram: { appid: '小程序 AppID 不能为空', pagepath: '小程序页面不能为空', url: '备用链接不能为空' },
    pic_photo_or_album: { key: '事件 KEY 不能为空' },
    pic_sysphoto: { key: '事件 KEY 不能为空' },
    pic_weixin: { key: '事件 KEY 不能为空' },
    scancode_push: { key: '事件 KEY 不能为空' },
    scancode_waitmsg: { key: '事件 KEY 不能为空' },
    view: { url: '网页链接不能为空' },
    view_limited: { media_id: 'MediaID 不能为空' },
  };
  const required = requiredMap[type];
  if (!required) {
    return failMenuValidation(path, `${label} 类型不支持`);
  }
  for (const [field, messageText] of Object.entries(required)) {
    if (!String(button?.[field] || '').trim()) {
      return failMenuValidation(path, `${label} ${messageText}`);
    }
  }
  if (['miniprogram', 'view'].includes(type) && !/^https?:\/\//i.test(String(button?.url || ''))) {
    return failMenuValidation(path, `${label} ${type === 'view' ? '网页链接必须是完整 URL' : '备用链接必须是完整 URL'}`);
  }

  return true;
}

function menuNameWidth(value: string) {
  // 按微信客户端展示宽度估算：中文等宽字符按 2 列，英文和数字按 1 列，避免超过官方“4/8 个汉字”的展示限制。
  return Array.from(value).reduce((sum, char) => sum + (/[\u1100-\u115f\u2329\u232a\u2e80-\u9fff\uf900-\ufaff\uff01-\uff60\uffe0-\uffe6]/u.test(char) ? 2 : 1), 0);
}
onMounted(load);
</script>

<style scoped>
.menu-designer-card :deep(.ant-card-body),
.menu-editor-card :deep(.ant-card-body) {
  padding: 12px;
}

.menu-designer-card :deep(.ant-card-head),
.menu-editor-card :deep(.ant-card-head) {
  min-height: 44px;
  padding: 0 14px;
}

.menu-editor-card :deep(.ant-card-head-title) {
  white-space: normal;
}

.menu-designer-phone {
  width: 100%;
  max-width: 348px;
  margin: 0 auto;
  overflow: hidden;
  color: #191919;
  background: #fafafa;
  border: 1px solid #8a8a8a;
  border-radius: 6px;
  box-shadow: 0 12px 28px rgb(0 0 0 / 10%);
}

.menu-designer-screen {
  position: relative;
  height: 428px;
  color: #191919;
  background: #f7f7f7;
}

.menu-designer-status {
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 8px;
  color: #fff;
  background: linear-gradient(180deg, #202124, #2e3033);
}

.menu-designer-signal,
.menu-designer-status-icons {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.menu-designer-signal i {
  width: 6px;
  height: 6px;
  background: #fff;
  border-radius: 999px;
  opacity: 0.95;
}

.menu-designer-wifi {
  position: relative;
  width: 15px;
  height: 10px;
  margin-left: 2px;
  border: 2px solid #fff;
  border-bottom: 0;
  border-radius: 15px 15px 0 0;
  opacity: 0.9;
}

.menu-designer-wifi::after {
  position: absolute;
  bottom: -3px;
  left: 50%;
  width: 4px;
  height: 4px;
  content: "";
  background: #fff;
  border-radius: 999px;
  transform: translateX(-50%);
}

.menu-designer-status-icons {
  font-size: 12px;
  font-weight: 600;
}

.menu-designer-battery {
  position: relative;
  width: 22px;
  height: 10px;
  border: 1px solid #fff;
  border-radius: 2px;
}

.menu-designer-battery::before {
  position: absolute;
  inset: 1px;
  content: "";
  background: #fff;
  border-radius: 1px;
}

.menu-designer-battery::after {
  position: absolute;
  top: 2px;
  right: -4px;
  width: 2px;
  height: 6px;
  content: "";
  background: #fff;
  border-radius: 0 2px 2px 0;
}

.menu-designer-screen-header {
  position: relative;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: linear-gradient(180deg, #303235, #242528);
  border-bottom: 1px solid #1d1d1f;
}

.menu-designer-back {
  position: absolute;
  left: 12px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 17px;
  font-weight: 500;
  color: #f5f5f5;
}

.menu-designer-back-icon {
  width: 13px;
  height: 13px;
  border-bottom: 3px solid #f5f5f5;
  border-left: 3px solid #f5f5f5;
  transform: rotate(45deg);
}

.menu-designer-title {
  max-width: 150px;
  overflow: hidden;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.menu-designer-user-icon {
  position: absolute;
  right: 14px;
  width: 24px;
  height: 24px;
  color: #fff;
}

.menu-designer-screen-body {
  height: calc(100% - 74px);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  gap: 8px;
  padding: 14px 12px 12px;
  background: #f5f5f5;
}

.menu-designer-empty-tip {
  align-self: center;
  max-width: 260px;
  padding: 6px 10px;
  margin-bottom: 4px;
  font-size: 12px;
  line-height: 1.4;
  color: #9a9a9a;
  text-align: center;
  background: rgb(255 255 255 / 68%);
  border: 1px dashed #d6d6d6;
  border-radius: 999px;
}

.menu-designer-time {
  align-self: center;
  padding: 2px 8px;
  font-size: 11px;
  color: #fff;
  background: #d0d0d0;
  border-radius: 999px;
}

.menu-designer-user-bubble {
  position: relative;
  align-self: flex-end;
  max-width: 245px;
  padding: 8px 10px;
  margin-right: 6px;
  font-size: 13px;
  line-height: 1.45;
  color: #10240f;
  background: #95ec69;
  border-radius: 5px;
}

.menu-designer-user-bubble::after {
  position: absolute;
  right: -5px;
  top: 11px;
  width: 10px;
  height: 10px;
  content: "";
  background: #95ec69;
  transform: rotate(45deg);
}

.menu-designer-result-card {
  align-self: flex-start;
  width: min(270px, 100%);
  padding: 10px;
  background: #fff;
  border: 1px solid #e3e3e3;
  border-left: 4px solid var(--ant-colorPrimary);
  border-radius: 6px;
  box-shadow: 0 8px 16px rgb(0 0 0 / 6%);
  color: #191919;
}

.menu-designer-result-title {
  margin-bottom: 4px;
  font-size: 14px;
  font-weight: 600;
  color: #191919;
}

.menu-designer-result-desc {
  font-size: 12px;
  line-height: 1.55;
  color: #4b5563;
}

.menu-designer-result-detail {
  padding-top: 7px;
  margin-top: 7px;
  overflow-wrap: anywhere;
  font-size: 12px;
  color: #8c8c8c;
  border-top: 1px dashed #d8d8d8;
}

.menu-designer-result-article {
  border-left-color: var(--ant-colorSuccess);
}

.menu-designer-result-media {
  border-left-color: var(--ant-colorWarning);
}

.menu-designer-result-reply {
  border-left-color: var(--ant-colorInfo);
}

.menu-designer-result-json {
  border-left-color: #722ed1;
}

.menu-designer-result-empty {
  border-left-color: var(--ant-colorError);
}

.menu-designer-help {
  max-width: 348px;
  margin: 8px auto 0;
  font-size: 12px;
  line-height: 1.45;
  color: var(--ant-colorTextTertiary);
}

.menu-designer-bar {
  position: relative;
  height: 52px;
  display: flex;
  align-items: stretch;
  overflow: visible;
  background: #fafafa;
  border-top: 1px solid #cfcfcf;
}

.menu-designer-keyboard {
  flex: 0 0 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b6b6b;
  cursor: pointer;
  background: #fafafa;
  border-right: 1px solid #d2d2d2;
}

.menu-designer-keyboard span {
  width: 24px;
  height: 24px;
}

.menu-designer-keyboard.active {
  color: #07c160;
  background: #f2fbf5;
}

.menu-designer-inputbar {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding: 7px 8px;
  background: #f7f7f7;
}

.menu-designer-input-placeholder {
  flex: 1;
  min-width: 0;
  height: 36px;
  padding: 0 12px;
  overflow: hidden;
  font-size: 13px;
  line-height: 36px;
  color: #8c8c8c;
  text-overflow: ellipsis;
  white-space: nowrap;
  background: #fff;
  border: 1px solid #d8d8d8;
  border-radius: 4px;
}

.menu-designer-send-button {
  height: 32px;
  padding: 0 12px;
  font-size: 13px;
  color: #fff;
  cursor: not-allowed;
  background: #9ee5b5;
  border: 0;
  border-radius: 4px;
}

.menu-designer-slots {
  flex: 1;
  display: flex;
  min-width: 0;
}

.menu-designer-top,
.menu-designer-add,
.menu-designer-placeholder {
  position: relative;
  flex: 0 0 calc(100% / 3);
  width: calc(100% / 3);
  height: 52px;
  min-width: 0;
  border: 0;
  border-right: 1px solid #d2d2d2;
  border-radius: 0;
}

.menu-designer-slots.simulating .menu-designer-top {
  flex: 1 1 0;
  width: auto;
}

.menu-designer-slots > :last-child {
  border-right: 0;
}

.menu-designer-top {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 0 7px;
  color: #606266;
  cursor: pointer;
  background: #fafafa;
  transition: color 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
}

.menu-designer-slots.editing .menu-designer-top.active,
.menu-designer-slots.editing .menu-designer-add:hover {
  z-index: 3;
  color: #606266;
  background: #fff;
  box-shadow: inset 0 0 0 2px #1aad19;
}

.menu-designer-slots.simulating .menu-designer-top.active {
  color: #07c160;
  background: #fff;
}

.menu-designer-top-name {
  max-width: 100%;
  overflow: hidden;
  font-size: 14px;
  line-height: 52px;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.menu-designer-menu-dot {
  flex: 0 0 7px;
  width: 7px;
  height: 7px;
  background: #c9c9c9;
  border-radius: 999px;
}

.menu-designer-slots.simulating .menu-designer-top.active .menu-designer-menu-dot {
  background: #07c160;
}

.menu-designer-add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
  color: #b8b8b8;
  background: #fafafa;
  box-shadow: none;
}

.menu-designer-add:hover,
.menu-designer-add:focus {
  color: #1aad19;
  background: #fff;
  border-color: transparent;
}

.menu-designer-add-icon {
  font-size: 28px;
  line-height: 24px;
}

.menu-designer-add-text {
  font-size: 12px;
  line-height: 14px;
}

.menu-designer-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #c0c0c0;
  background: #f5f5f5;
}

.menu-designer-sub-list {
  position: absolute;
  right: 0;
  bottom: 52px;
  left: 0;
  z-index: 12;
  display: flex;
  flex-direction: column;
  padding: 0;
  color: #606266;
  background: #fafafa;
  border: 1px solid #d2d2d2;
  box-shadow: none;
}

.menu-designer-sub-list::after {
  position: absolute;
  bottom: -7px;
  left: 50%;
  width: 12px;
  height: 12px;
  content: "";
  background: #fafafa;
  border-right: 1px solid #d2d2d2;
  border-bottom: 1px solid #d2d2d2;
  transform: translateX(-50%) rotate(45deg);
}

.menu-designer-sub {
  position: relative;
  min-height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 8px;
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  line-height: 50px;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
  color: #606266;
  background: #fafafa;
  border: 0;
  border-bottom: 1px solid #d2d2d2;
  border-radius: 0;
}

.menu-designer-sub:last-child {
  border-bottom: 0;
}

.menu-designer-sub.active {
  z-index: 2;
  color: #1aad19;
  background: #fff;
  box-shadow: inset 0 0 0 2px #1aad19;
}

.menu-designer-sub-add {
  font-size: 30px;
  font-weight: 300;
  color: #b8b8b8;
}

.menu-designer-sub-add:hover,
.menu-designer-sub-add:focus {
  color: #1aad19;
  background: #fff;
  outline: 0;
}

.menu-editor-actions {
  margin-top: 4px;
}

@media (max-width: 768px) {
  .menu-designer-phone {
    max-width: 330px;
  }

  .menu-designer-screen {
    height: 386px;
  }
}
</style>
