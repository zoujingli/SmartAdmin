<template>
  <Page :title="title">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="activeLoading" @click="loadActive"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button v-if="activeTab === 'data'" :loading="exporting" @click="exportRows"><span class="i-lucide-download" />导出</Button>
        <Button v-if="canCreate && activeTab === 'data'" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增{{ entityName }}</Button>
      </Space>
    </template>

    <Card class="website-crud-page crud-page-shell">
      <Alert v-if="description" class="website-crud-page__intro" show-icon :message="description" :description="introText" />

      <Card class="mb-5">
        <Row class="crud-search-grid" :gutter="[16, 16]">
          <Col :xs="24" :sm="12" :xl="6">
            <SearchField label="搜索内容">
              <Input v-model:value="keyword" allow-clear :placeholder="searchPlaceholder" @press-enter="handleSearch" />
            </SearchField>
          </Col>
          <Col v-for="field in filterFields" :key="field.name" :xs="24" :sm="12" :xl="field.xl || 4">
            <SearchField :label="field.label">
              <Select
                v-if="field.type === 'select'"
                v-model:value="filters[field.name]"
                allow-clear
                show-search
                option-filter-prop="label"
                class="w-full"
                :placeholder="field.placeholder || `请选择${field.label}`"
              >
                <SelectOption v-for="item in fieldOptions(field)" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption>
              </Select>
              <Input v-else v-model:value="filters[field.name]" allow-clear :placeholder="field.placeholder || `请输入${field.label}`" @press-enter="handleSearch" />
            </SearchField>
          </Col>
          <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="8">
            <Space wrap>
              <Button type="primary" :loading="activeLoading" @click="handleSearch">搜索</Button>
              <Button :disabled="activeLoading" @click="handleReset">重置</Button>
            </Space>
          </Col>
        </Row>
      </Card>

      <Card>
        <Tabs v-if="showRecycle" v-model:activeKey="activeTab" class="website-crud-page__tabs" @change="onTabChange">
          <TabPane key="data" tab="数据列表" />
          <TabPane key="recycle" tab="回收站" />
        </Tabs>
        <CrudTableHeader :title="activeListTitle" :description="activeListDescription" :count-text="`${activePagination.total} 条记录`" />
        <Table :columns="activeTableColumns" :data-source="activeItems" :loading="activeLoading" :pagination="activePagination" :scroll="tableScroll" row-key="id" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'status'"><Tag :color="statusColor(record.status)">{{ record.status_text || statusText(record.status) }}</Tag></template>
            <template v-else-if="column.key === 'publish_status'"><Tag :color="optionColor(publishStatusOptions, record.publish_status)">{{ record.publish_status_text || optionText(publishStatusOptions, record.publish_status) }}</Tag></template>
            <template v-else-if="column.key === 'lead_status'"><Tag :color="optionColor(leadStatusOptions, record.status)">{{ record.status_text || optionText(leadStatusOptions, record.status) }}</Tag></template>
            <template v-else-if="columnValueType(column) === 'option'">{{ optionLabelByColumn(record, column) }}</template>
            <template v-else-if="columnValueType(column) === 'copy'">
              <TypographyText v-if="readable(record, column.dataIndex) !== '-'" copyable>{{ readable(record, column.dataIndex) }}</TypographyText>
              <span v-else>-</span>
            </template>
            <template v-else-if="columnValueType(column) === 'route'">
              <TypographyText v-if="readable(record, column.dataIndex) !== '-'" code copyable>{{ readable(record, column.dataIndex) }}</TypographyText>
              <span v-else>-</span>
            </template>
            <template v-else-if="columnValueType(column) === 'longText'">
              <Tooltip :title="readable(record, column.dataIndex)">
                <span class="website-crud-page__ellipsis">{{ readable(record, column.dataIndex) }}</span>
              </Tooltip>
            </template>
            <template v-else-if="columnValueType(column) === 'json'">
              <Tooltip :title="jsonPreviewByColumn(record, column)">
                <TypographyText code class="website-crud-page__ellipsis">{{ jsonPreviewByColumn(record, column) }}</TypographyText>
              </Tooltip>
            </template>
            <template v-else-if="columnValueType(column) === 'tags'">
              <Space v-if="tagValuesByColumn(record, column).length > 0" wrap size="small">
                <Tag v-for="tag in tagValuesByColumn(record, column)" :key="tag">{{ tag }}</Tag>
              </Space>
              <span v-else>-</span>
            </template>
            <template v-else-if="column.key === 'action'"><CrudTableActions :actions="rowActions(record)" /></template>
            <template v-else>{{ readable(record, column.dataIndex) }}</template>
          </template>
        </Table>
      </Card>
    </Card>

    <AppDrawer :confirm-loading="saving" :open="drawerOpen" :title="form.id ? `编辑${entityName}` : `新增${entityName}`" width-size="lg" @close="drawerOpen = false" @ok="save">
      <Alert v-if="formHelp" class="mb-4" show-icon type="info" :message="formHelp" />
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col v-for="field in formFields" :key="field.name" :md="field.span || 12" :span="24">
            <div v-if="field.type === 'object'" class="website-crud-page__group-field">
              <div class="website-crud-page__group-title">
                <span>{{ field.label }}</span>
                <span v-if="field.required" class="website-crud-page__required">*</span>
              </div>
              <Row :gutter="[12, 0]">
                <Col v-for="child in field.children || []" :key="child.name" :md="child.span || 12" :span="24">
                  <FormItem :label="child.label" :required="child.required">
                    <Select v-if="child.type === 'select'" v-model:value="form[field.name][child.name]" allow-clear class="w-full" :placeholder="child.placeholder || `请选择${child.label}`">
                      <SelectOption v-if="hasEmptyOption(child)" :key="`empty-${child.name}`" :label="child.emptyLabel" :value="child.emptyValue">{{ child.emptyLabel }}</SelectOption>
                      <SelectOption v-for="item in fieldOptions(child)" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption>
                    </Select>
                    <InputNumber v-else-if="child.type === 'number'" v-model:value="form[field.name][child.name]" class="w-full" :min="child.min ?? 0" :placeholder="child.placeholder" />
                    <AdminImageUpload v-else-if="child.type === 'image'" :model-value="uploadFieldValue(form[field.name][child.name])" :allow-select-existing="true" :clearable="true" :button-text="child.buttonText || '上传/选择图片'" scene="image" @update:model-value="(value) => form[field.name][child.name] = uploadFieldUrl(value)" />
                    <AdminVideoUpload v-else-if="child.type === 'video'" :model-value="uploadFieldValue(form[field.name][child.name])" :allow-select-existing="true" :clearable="true" :button-text="child.buttonText || '上传/选择视频'" scene="video" @update:model-value="(value) => form[field.name][child.name] = uploadFieldUrl(value)" />
                    <Textarea v-else-if="child.type === 'textarea'" v-model:value="form[field.name][child.name]" :auto-size="{ minRows: child.rows || 3, maxRows: 8 }" :maxlength="child.maxlength || 1000" :placeholder="child.placeholder" show-count allow-clear />
                    <Input v-else v-model:value="form[field.name][child.name]" :maxlength="child.maxlength || 255" :placeholder="child.placeholder" allow-clear />
                    <div v-if="child.help" class="website-crud-page__field-help">{{ child.help }}</div>
                  </FormItem>
                </Col>
              </Row>
              <div v-if="field.help" class="website-crud-page__field-help">{{ field.help }}</div>
            </div>
            <div v-else-if="field.type === 'kv'" class="website-crud-page__group-field">
              <div class="website-crud-page__group-title">
                <span>{{ field.label }}</span>
                <span v-if="field.required" class="website-crud-page__required">*</span>
              </div>
              <div class="website-crud-page__kv-list">
                <div v-for="(row, index) in form[field.name]" :key="index" class="website-crud-page__kv-row">
                  <Input v-model:value="row.key" class="website-crud-page__kv-key" :maxlength="80" :placeholder="field.keyPlaceholder || '字段名，如 slogan'" allow-clear />
                  <Input v-model:value="row.value" class="website-crud-page__kv-value" :maxlength="field.maxlength || 1000" :placeholder="field.valuePlaceholder || '字段内容'" allow-clear />
                  <Button danger type="link" @click="removeKvRow(field, Number(index))">删除</Button>
                </div>
                <Button type="dashed" @click="addKvRow(field)"><span class="i-lucide-plus" />增加一项</Button>
              </div>
              <div v-if="field.help" class="website-crud-page__field-help">{{ field.help }}</div>
            </div>
            <FormItem v-else :label="field.label" :required="field.required">
              <Select
                v-if="field.type === 'select'"
                v-model:value="form[field.name]"
                allow-clear
                show-search
                option-filter-prop="label"
                class="w-full"
                :placeholder="field.placeholder || `请选择${field.label}`"
              >
                <SelectOption v-if="hasEmptyOption(field)" :key="`empty-${field.name}`" :label="field.emptyLabel" :value="field.emptyValue">{{ field.emptyLabel }}</SelectOption>
                <SelectOption v-for="item in fieldOptions(field)" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption>
              </Select>
              <InputNumber v-else-if="field.type === 'number'" v-model:value="form[field.name]" class="w-full" :min="field.min ?? 0" :placeholder="field.placeholder" />
              <DatePicker v-else-if="field.type === 'datetime'" v-model:value="form[field.name]" class="w-full" format="YYYY-MM-DD HH:mm:ss" value-format="YYYY-MM-DD HH:mm:ss" show-time input-read-only :placeholder="field.placeholder || '请选择时间'" />
              <AdminRichTextEditor v-else-if="field.type === 'richtext'" allow-file v-model="form[field.name]" :allow-video="true" :uploadable="canUploadSystemFile" :visible="drawerOpen" :title="field.label" :placeholder="field.placeholder || `请输入${field.label}`" />
              <AdminImageUpload v-else-if="field.type === 'image'" :model-value="uploadFieldValue(form[field.name])" :allow-select-existing="true" :clearable="true" :button-text="field.buttonText || '上传/选择图片'" scene="image" @update:model-value="(value) => form[field.name] = uploadFieldUrl(value)" />
              <AdminVideoUpload v-else-if="field.type === 'video'" :model-value="uploadFieldValue(form[field.name])" :allow-select-existing="true" :clearable="true" :button-text="field.buttonText || '上传/选择视频'" scene="video" @update:model-value="(value) => form[field.name] = uploadFieldUrl(value)" />
              <Textarea v-else-if="field.type === 'textarea' || field.type === 'json' || field.type === 'tags'" v-model:value="form[field.name]" :auto-size="{ minRows: field.rows || (field.type === 'json' ? 5 : 3), maxRows: 12 }" :maxlength="field.maxlength || 5000" :placeholder="field.placeholder" show-count allow-clear />
              <Input v-else v-model:value="form[field.name]" :maxlength="field.maxlength || 255" :placeholder="field.placeholder" allow-clear />
              <div v-if="field.help" class="website-crud-page__field-help">{{ field.help }}</div>
            </FormItem>
          </Col>
        </Row>
      </Form>
    </AppDrawer>

    <AppDrawer :confirm-loading="handling" :open="handleOpen" ok-text="保存处理结果" title="处理访客线索" width-size="md" @close="handleOpen = false" @ok="submitHandle">
      <Descriptions v-if="activeLead" bordered :column="1" class="mb-4" size="small">
        <DescriptionsItem label="联系人">{{ activeLead.name || '-' }}</DescriptionsItem>
        <DescriptionsItem label="手机号"><TypographyText v-if="activeLead.mobile" copyable>{{ activeLead.mobile }}</TypographyText><span v-else>-</span></DescriptionsItem>
        <DescriptionsItem label="邮箱"><TypographyText v-if="activeLead.email" copyable>{{ activeLead.email }}</TypographyText><span v-else>-</span></DescriptionsItem>
        <DescriptionsItem label="公司">{{ activeLead.company || '-' }}</DescriptionsItem>
        <DescriptionsItem label="来源页面"><TypographyText v-if="activeLead.source_url" copyable>{{ activeLead.source_url }}</TypographyText><span v-else>-</span></DescriptionsItem>
        <DescriptionsItem label="咨询内容"><TypographyParagraph class="website-crud-page__lead-content" copyable>{{ activeLead.content || '-' }}</TypographyParagraph></DescriptionsItem>
      </Descriptions>
      <Form :model="handleForm" layout="vertical">
        <FormItem label="线索主题"><Input :value="activeLead?.subject || '-'" disabled /></FormItem>
        <FormItem label="处理状态" required>
          <Select v-model:value="handleForm.status" class="w-full">
            <SelectOption v-for="item in leadStatusOptions" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption>
          </Select>
        </FormItem>
        <FormItem label="处理备注">
          <Textarea v-model:value="handleForm.remark" :auto-size="{ minRows: 4, maxRows: 8 }" :maxlength="remarkMaxLength" show-count allow-clear placeholder="填写跟进结果、无效原因或下一步安排" />
        </FormItem>
      </Form>
    </AppDrawer>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useAccess } from '@vben/access';
import { AdminImageUpload, AdminRichTextEditor, AdminVideoUpload, CrudTableHeader, Page } from '@vben/common-ui';
import { Alert, Button, Card, Col, DatePicker, Descriptions, DescriptionsItem, Form, FormItem, Input, InputNumber, message, Row, Select, SelectOption, Space, Table, Tabs, TabPane, Tag, Textarea, Tooltip, TypographyParagraph, TypographyText } from 'ant-design-vue';
import { requestClient } from '#/api/request';
import CrudTableActions from '#/components/crud-table-actions.vue';
import SearchField from '#/components/crud-search-field.vue';
import { exportCrudXlsx } from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { leadStatusOptions, optionColor, optionText, pageParams, parseJsonField, publishStatusOptions, splitStringList, statusColor, statusText, stringifyJson } from '../website-api';
import AppDrawer from '#/components/app-drawer.vue';

type Field = { label: string; name: string; type?: 'datetime' | 'image' | 'json' | 'kv' | 'number' | 'object' | 'richtext' | 'select' | 'tags' | 'textarea' | 'text' | 'video'; required?: boolean; maxlength?: number; min?: number; options?: any[]; optionApi?: string; span?: number; rows?: number; placeholder?: string; help?: string; defaultValue?: any; emptyValue?: any; emptyLabel?: string; jsonFallback?: any; children?: Field[]; keyPlaceholder?: string; valuePlaceholder?: string; buttonText?: string; filterBySite?: boolean; excludeSelf?: boolean };
type FilterField = Field & { xl?: number };

const props = withDefaults(defineProps<{
  allowCreate?: boolean;
  allowDelete?: boolean;
  allowEdit?: boolean;
  allowStatus?: boolean;
  api: string;
  columns: any[];
  description?: string;
  entityName: string;
  filterFields?: FilterField[];
  formFields?: Field[];
  formHelp?: string;
  introText?: string;
  leadMode?: boolean;
  listDescription?: string;
  listTitle?: string;
  publishable?: boolean;
  searchPlaceholder?: string;
  title: string;
}>(), {
  allowCreate: true,
  allowDelete: true,
  allowEdit: true,
  allowStatus: true,
  description: '',
  filterFields: () => [],
  formFields: () => [],
  formHelp: '',
  introText: '',
  leadMode: false,
  listDescription: '',
  listTitle: '',
  publishable: false,
  searchPlaceholder: '输入名称、编码或关键字',
});

const { hasAccessByCodes } = useAccess();
const canUploadSystemFile = computed(() => hasAccessByCodes(['system.file.upload']));
const loading = ref(false); const loadingRecycle = ref(false); const saving = ref(false); const exporting = ref(false); const drawerOpen = ref(false);
const handling = ref(false); const handleOpen = ref(false); const activeLead = ref<any>(null);
const activeTab = ref<'data' | 'recycle'>('data');
const keyword = ref(''); const items = ref<any[]>([]); const recycleItems = ref<any[]>([]);
const suppressSiteWatch = ref(false);
const filters = reactive<Record<string, any>>({});
const form = reactive<Record<string, any>>({});
const handleForm = reactive<Record<string, any>>({ status: 'handled', remark: '' });
const dynamicOptions = reactive<Record<string, any[]>>({});
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const recyclePagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const listTitle = computed(() => props.listTitle || `${props.entityName}列表`);
const listDescription = computed(() => props.listDescription || '当前列表沿用后台权限、数据范围和租户隔离；导出仅导出当前筛选结果。');
const permissionPrefix = computed(() => props.api.replace(/^system\//, '').replace(/\//g, '.'));
const canCreate = computed(() => props.allowCreate && hasAccessByCodes([`${permissionPrefix.value}.create`]));
const canEdit = computed(() => props.allowEdit && hasAccessByCodes([`${permissionPrefix.value}.update`]));
const canDelete = computed(() => props.allowDelete && hasAccessByCodes([`${permissionPrefix.value}.delete`]));
const canStatus = computed(() => props.allowStatus && hasAccessByCodes([`${permissionPrefix.value}.status`]));
const canRecovery = computed(() => hasAccessByCodes([`${permissionPrefix.value}.recovery`]));
const canRealDelete = computed(() => hasAccessByCodes([`${permissionPrefix.value}.real-delete`]));
const canPublish = computed(() => props.publishable && hasAccessByCodes([`${permissionPrefix.value}.publish`]));
const canOffline = computed(() => props.publishable && hasAccessByCodes([`${permissionPrefix.value}.offline`]));
const canHandleLead = computed(() => props.leadMode && hasAccessByCodes([`${permissionPrefix.value}.handle`]));
const showRecycle = computed(() => !props.leadMode && (canRecovery.value || canRealDelete.value));
const hasActions = computed(() => activeTab.value === 'recycle' ? showRecycle.value : (canEdit.value || canDelete.value || canStatus.value || canPublish.value || canOffline.value || canHandleLead.value));
const activeItems = computed(() => activeTab.value === 'recycle' ? recycleItems.value : items.value);
const activePagination = computed(() => activeTab.value === 'recycle' ? recyclePagination : pagination);
const activeLoading = computed(() => activeTab.value === 'recycle' ? loadingRecycle.value : loading.value);
const activeListTitle = computed(() => activeTab.value === 'recycle' ? `已删除${props.entityName}` : listTitle.value);
const activeListDescription = computed(() => activeTab.value === 'recycle' ? `回收站中的${props.entityName}可恢复；彻底删除后无法在后台找回。` : listDescription.value);
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth(activeItems.value.length > 0 ? activeItems.value.map(rowActions) : [rowActions({ id: 1, status: 1 })], { maxWidth: 240, minWidth: 110 }));
const activeTableColumns = computed(() => hasActions.value ? [...props.columns, { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const }] : props.columns);
const tableScroll = computed(() => buildTableScrollX(activeTableColumns.value, { minWidth: 1100 }));
const remarkMaxLength = 1000;

function fieldOptions(field: Field) {
  let items = dynamicOptions[field.name] || field.options || [];
  if (field.filterBySite && form.site_id) {
    items = items.filter((item) => !item.site_id || String(item.site_id) === String(form.site_id));
  }
  if (field.excludeSelf && form.id) {
    items = items.filter((item) => String(item.value ?? item.id) !== String(form.id));
  }
  return items;
}
function hasEmptyOption(field: Field) { return Boolean(field.emptyLabel) && Object.prototype.hasOwnProperty.call(field, 'emptyValue'); }
function columnValueType(column: any) { return String(column?.valueType || ''); }
function columnOptionKey(column: any) { return String(Array.isArray(column?.dataIndex) ? column.dataIndex.join('.') : column?.dataIndex || column?.key || ''); }
function columnOptions(column: any) {
  const items = dynamicOptions[columnOptionKey(column)] || column?.options || [];
  return hasEmptyOption(column) ? [{ label: column.emptyLabel, value: column.emptyValue }, ...items] : items;
}
function recordValue(record: any, dataIndex: any) {
  if (Array.isArray(dataIndex)) {
    return dataIndex.reduce((value, key) => value?.[key], record);
  }

  return dataIndex ? record?.[dataIndex] : undefined;
}
function readable(record: any, dataIndex: any) { const value = recordValue(record, dataIndex); if (value === null || value === undefined || value === '') return '-'; return Array.isArray(value) || typeof value === 'object' ? JSON.stringify(value) : String(value); }
function optionLabelByColumn(record: any, column: any) { const value = recordValue(record, column?.dataIndex); return optionText(columnOptions(column), value) || readable(record, column?.dataIndex); }
function tagValues(record: any, dataIndex: any) { const value = recordValue(record, dataIndex); return Array.isArray(value) ? value.map((item) => String(item)) : []; }
function jsonPreview(value: any) { const text = readable({ value }, 'value'); return text.length > 120 ? `${text.slice(0, 117)}...` : text; }
function jsonPreviewByColumn(record: any, column: any) { return jsonPreview(recordValue(record, column?.dataIndex)); }
function tagValuesByColumn(record: any, column: any) { return tagValues(record, column?.dataIndex); }
function emptyToUndefined(value: any) { return value === '' || value === null ? undefined : value; }
function queryParams(page = pagination.current, pageSize = pagination.pageSize) {
  const payload: Record<string, any> = pageParams({ current: page, pageSize }, { keyword: keyword.value });
  for (const field of props.filterFields) payload[field.name] = filters[field.name];
  return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== ''));
}
async function loadFieldOptions() {
  const columnFields = props.columns
    .filter((column) => columnValueType(column) === 'option' && column.optionApi)
    .map((column) => ({ ...column, name: columnOptionKey(column), type: 'select' }));
  const fields = [...props.formFields, ...props.formFields.flatMap((field) => field.children || []), ...props.filterFields, ...columnFields].filter((field) => field.type === 'select' && field.optionApi);
  await Promise.all(fields.map(async (field) => {
    try {
      dynamicOptions[field.name] = await requestClient.get<any[]>(field.optionApi as string);
    } catch {
      dynamicOptions[field.name] = field.options || [];
    }
  }));
}
function objectDefaults(field: Field) {
  const value: Record<string, any> = { ...(typeof field.defaultValue === 'object' && !Array.isArray(field.defaultValue) ? field.defaultValue : {}) };
  for (const child of field.children || []) {
    if (!Object.prototype.hasOwnProperty.call(value, child.name)) value[child.name] = defaultFieldValue(child);
  }
  return value;
}
function objectValue(field: Field, value: any) {
  let parsed = value;
  if (typeof value === 'string') {
    try { parsed = JSON.parse(value); } catch { parsed = {}; }
  }
  return { ...objectDefaults(field), ...(parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}) };
}
function kvRows(value: any) {
  let parsed = value;
  if (typeof value === 'string') {
    try { parsed = JSON.parse(value); } catch { parsed = {}; }
  }
  if (Array.isArray(parsed)) return parsed.map((item) => ({ key: String(item?.key || ''), value: String(item?.value || '') })).filter((item) => item.key || item.value);
  if (parsed && typeof parsed === 'object') return Object.entries(parsed).map(([key, item]) => ({ key, value: typeof item === 'string' || typeof item === 'number' ? String(item) : JSON.stringify(item) }));
  return [];
}
function defaultFieldValue(field: Field) { if (Object.prototype.hasOwnProperty.call(field, 'defaultValue')) return field.defaultValue; if (field.type === 'number') return 0; if (field.type === 'object') return objectDefaults(field); if (field.type === 'kv') return []; if (field.type === 'json') return stringifyJson(field.jsonFallback ?? {}); return ''; }
function formValue(field: Field, record: Record<string, any>) { const value = record[field.name]; if (field.type === 'object') return objectValue(field, value); if (field.type === 'kv') return kvRows(value); if (field.type === 'json') return stringifyJson(value, stringifyJson(field.jsonFallback ?? {})); if (field.type === 'tags') return splitStringList(value).join('\n'); return value ?? defaultFieldValue(field); }
function resetForm(record: Record<string, any> = {}) {
  suppressSiteWatch.value = true;
  Object.keys(form).forEach((key) => delete form[key]);
  for (const field of props.formFields) form[field.name] = formValue(field, record);
  if (record.id) form.id = record.id;
  queueMicrotask(() => { suppressSiteWatch.value = false; });
}
function openCreate() { resetForm(); drawerOpen.value = true; }
async function openEdit(record: any) {
  // 编辑时重新读取详情，避免列表为了性能裁剪字段后出现富文本、结构化字段不完整。
  const detail = record?.id ? await requestClient.get<any>(`${props.api}/info/${record.id}`) : record;
  resetForm(detail || record);
  drawerOpen.value = true;
}
function addKvRow(field: Field) { if (!Array.isArray(form[field.name])) form[field.name] = []; form[field.name].push({ key: '', value: '' }); }
function removeKvRow(field: Field, index: number) { if (Array.isArray(form[field.name])) form[field.name].splice(index, 1); }
function uploadFieldValue(value: any) {
  const url = String(value || '').trim();
  if (!url) return null;

  const name = url.split('/').filter(Boolean).pop() || '官网资源';
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
function uploadFieldUrl(value: any) {
  const asset = Array.isArray(value) ? (value[0] || null) : value;
  return asset?.url || asset?.preview_url || asset?.download_url || '';
}
function normalizePayload(payload: Record<string, any>) {
  const result: Record<string, any> = {};
  for (const field of props.formFields) {
    let value = payload[field.name];
    if (field.type === 'object') {
      const objectValue: Record<string, any> = {};
      for (const child of field.children || []) objectValue[child.name] = value?.[child.name] ?? defaultFieldValue(child);
      value = objectValue;
    }
    if (field.type === 'kv') {
      value = Array.isArray(value)
        ? Object.fromEntries(value.map((item) => [String(item?.key || '').trim(), item?.value]).filter(([key]) => key !== ''))
        : {};
    }
    if (field.type === 'json') {
      try {
        value = parseJsonField(value, field.jsonFallback ?? {});
      } catch {
        message.error(`${field.label}必须是合法 JSON`);
        throw new Error(`${field.label}必须是合法 JSON`);
      }
    }
    if (field.type === 'tags') value = splitStringList(value);
    if (Object.prototype.hasOwnProperty.call(field, 'emptyValue')) value = emptyToUndefined(value) === undefined ? field.emptyValue : value;
    result[field.name] = value;
  }
  return result;
}
async function load() { if (loading.value) return; loading.value = true; try { const data = await requestClient.get<any>(`${props.api}/index`, { params: queryParams() }); items.value = data?.items || []; pagination.total = data?.pageInfo?.total || 0; } finally { loading.value = false; } }
async function loadRecycle() {
  if (loadingRecycle.value || !showRecycle.value) return;
  loadingRecycle.value = true;
  try {
    const data = await requestClient.get<any>(`${props.api}/recycle`, { params: queryParams(recyclePagination.current, recyclePagination.pageSize) });
    recycleItems.value = data?.items || [];
    recyclePagination.total = data?.pageInfo?.total || 0;
  } finally {
    loadingRecycle.value = false;
  }
}
async function loadActive() { if (activeTab.value === 'recycle') await loadRecycle(); else await load(); }
function handleSearch() { if (activeTab.value === 'recycle') recyclePagination.current = 1; else pagination.current = 1; void loadActive(); }
function handleReset() { keyword.value = ''; for (const field of props.filterFields) filters[field.name] = field.defaultValue; handleSearch(); }
function onTabChange() { void loadActive(); }
function onTableChange(page: any) { if (activeTab.value === 'recycle') { recyclePagination.current = page.current; recyclePagination.pageSize = page.pageSize; } else { pagination.current = page.current; pagination.pageSize = page.pageSize; } void loadActive(); }
async function save() { if (saving.value) return; saving.value = true; try { const payload = normalizePayload({ ...form }); if (form.id) await requestClient.put(`${props.api}/update/${form.id}`, payload); else await requestClient.post(`${props.api}/create`, payload); message.success('保存成功'); drawerOpen.value = false; await load(); } finally { saving.value = false; } }
async function deleteRow(id: number) { await requestClient.delete(`${props.api}/delete/${id}`); message.success('删除成功'); await load(); }
async function recoveryRow(id: number) { await requestClient.put(`${props.api}/recovery/${id}`, {}); message.success('恢复成功'); await loadRecycle(); await load(); }
async function realDeleteRow(id: number) { await requestClient.delete(`${props.api}/real-delete/${id}`); message.success('彻底删除成功'); await loadRecycle(); }
async function toggleStatus(record: any) { const next = Number(record.status) === 1 ? 0 : 1; await requestClient.put(`${props.api}/status/${record.id}`, { status: next }); message.success(next === 1 ? '已启用' : '已禁用'); await load(); }
async function publishRow(id: number) { await requestClient.put(`${props.api}/publish/${id}`, {}); message.success('发布成功'); await load(); }
async function offlineRow(id: number) { await requestClient.put(`${props.api}/offline/${id}`, {}); message.success('下线成功'); await load(); }
function openHandle(record: any) { activeLead.value = record; handleForm.status = record.status === 'pending' ? 'handled' : record.status || 'handled'; handleForm.remark = record.remark || ''; handleOpen.value = true; }
async function submitHandle() { if (!activeLead.value?.id || handling.value) return; handling.value = true; try { await requestClient.put(`${props.api}/handle/${activeLead.value.id}`, { ...handleForm }); message.success('处理成功'); handleOpen.value = false; await load(); } finally { handling.value = false; } }
function rowActions(record: any) {
  const id = Number(record?.id || 0);
  if (activeTab.value === 'recycle') {
    return [
      { label: '恢复', visible: canRecovery.value && id > 0, onClick: () => recoveryRow(id) },
      { label: '彻底删除', visible: canRealDelete.value && id > 0, danger: true, confirmTitle: `确认彻底删除该${props.entityName}？`, confirmContent: '彻底删除后无法在后台恢复，请确认已经不再需要。', onClick: () => realDeleteRow(id) },
    ];
  }

  return [
    { label: '立即发布', visible: canPublish.value && id > 0 && record?.publish_status !== 'published', confirmTitle: '确认立即发布该内容？', confirmContent: '立即发布会把发布时间改为当前时间；如需定时发布，请在编辑表单里设置发布时间。', onClick: () => publishRow(id) },
    { label: '下线', visible: canOffline.value && id > 0 && record?.publish_status !== 'offline', confirmTitle: '确认下线该内容？', confirmContent: '下线后公开接口不会再返回该数据。', onClick: () => offlineRow(id) },
    { label: '处理', visible: canHandleLead.value && id > 0, onClick: () => openHandle(record) },
    { label: '编辑', visible: canEdit.value && id > 0 && props.formFields.length > 0, onClick: () => openEdit(record) },
    { label: Number(record?.status) === 1 ? '禁用' : '启用', visible: canStatus.value && id > 0 && !props.leadMode, danger: Number(record?.status) === 1, confirmTitle: `确认${Number(record?.status) === 1 ? '禁用' : '启用'}该${props.entityName}？`, confirmContent: '状态变更会影响后台可见性；禁用后公开接口不会返回对应数据。', onClick: () => toggleStatus(record) },
    { label: '删除', visible: canDelete.value && id > 0, danger: true, confirmTitle: `确认删除该${props.entityName}？`, confirmContent: props.leadMode ? '删除后将从线索列表移除，当前后台不提供回收站恢复入口。' : '删除后可在回收站恢复，请确认不影响官网展示。', onClick: () => deleteRow(id) },
  ];
}
async function exportRows() { if (exporting.value) return; exporting.value = true; try { await exportCrudXlsx({ filename: `${props.entityName}_${new Date().toISOString().slice(0, 10)}.xlsx`, sheetName: props.entityName, columns: props.columns.map((column) => ({ key: Array.isArray(column.dataIndex) ? column.dataIndex.join('.') : String(column.dataIndex || column.key), title: column.title, formatter: (record: any) => columnValueType(column) === 'option' ? optionLabelByColumn(record, column) : readable(record, column.dataIndex) })), fetchPage: (page, pageSize) => requestClient.get<any>(`${props.api}/index`, { params: queryParams(page, pageSize) }), rules: ['导出只读取当前筛选条件、权限范围和租户范围内的数据。'] }); } finally { exporting.value = false; } }

resetForm();
watch(() => form.site_id, (siteId, oldSiteId) => {
  if (suppressSiteWatch.value || siteId === oldSiteId || oldSiteId === undefined) return;
  // 站点切换后清空依赖站点的选择项，避免运营误把旧站点栏目/导航/内容保存到新站点。
  for (const field of props.formFields) {
    if (field.filterBySite && field.name !== 'site_id') {
      form[field.name] = field.emptyValue ?? defaultFieldValue(field);
    }
  }
});
onMounted(async () => { for (const field of props.filterFields) filters[field.name] = field.defaultValue; await loadFieldOptions(); await load(); });
</script>

<style scoped>
.website-crud-page__intro {
  margin-bottom: 16px;
}

.website-crud-page__ellipsis {
  display: inline-block;
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: bottom;
  white-space: nowrap;
}

.website-crud-page__lead-content {
  margin-bottom: 0;
  white-space: pre-wrap;
}

.website-crud-page__field-help {
  margin-top: 6px;
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  font-size: 12px;
  line-height: 1.5;
}

.website-crud-page__group-field {
  margin-bottom: 16px;
  padding: 16px;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 8px;
  background: var(--ant-colorFillAlter, hsl(var(--muted) / 30%));
}

.website-crud-page__group-title {
  display: flex;
  gap: 4px;
  align-items: center;
  margin-bottom: 12px;
  color: var(--ant-colorText, hsl(var(--foreground)));
  font-weight: 600;
}

.website-crud-page__required {
  color: var(--ant-colorError, #ff4d4f);
}

.website-crud-page__kv-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.website-crud-page__kv-row {
  display: grid;
  grid-template-columns: minmax(160px, 0.35fr) minmax(220px, 1fr) auto;
  gap: 10px;
  align-items: center;
}

@media (max-width: 768px) {
  .website-crud-page__kv-row {
    grid-template-columns: 1fr;
  }
}
</style>
