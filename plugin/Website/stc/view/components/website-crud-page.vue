<template>
  <Page :title="title">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="load"><span class="i-lucide-refresh-cw" />刷新</Button>
        <Button :loading="exporting" @click="exportRows"><span class="i-lucide-download" />导出</Button>
        <Button v-if="allowCreate" type="primary" @click="openCreate"><span class="i-lucide-plus" />新增{{ entityName }}</Button>
      </Space>
    </template>

    <Card class="website-crud-page crud-page-shell">
      <Alert v-if="description" class="website-crud-page__intro" show-icon :message="description" :description="introText" />

      <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
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
              <Button type="primary" :loading="loading" @click="handleSearch">搜索</Button>
              <Button :disabled="loading" @click="handleReset">重置</Button>
            </Space>
          </Col>
        </Row>
      </Card>

      <Card>
        <CrudTableHeader :title="listTitle" :description="listDescription" :count-text="`${pagination.total} 条记录`" />
        <Table :columns="tableColumns" :data-source="items" :loading="loading" :pagination="pagination" :scroll="tableScroll" row-key="id" @change="onTableChange">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'status'"><Tag :color="statusColor(record.status)">{{ record.status_text || statusText(record.status) }}</Tag></template>
            <template v-else-if="column.key === 'publish_status'"><Tag :color="optionColor(publishStatusOptions, record.publish_status)">{{ record.publish_status_text || optionText(publishStatusOptions, record.publish_status) }}</Tag></template>
            <template v-else-if="column.key === 'lead_status'"><Tag :color="optionColor(leadStatusOptions, record.status)">{{ record.status_text || optionText(leadStatusOptions, record.status) }}</Tag></template>
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

    <Drawer :open="drawerOpen" :title="form.id ? `编辑${entityName}` : `新增${entityName}`" :width="popupWidth.lg" @close="drawerOpen = false">
      <Alert v-if="formHelp" class="mb-4" show-icon type="info" :message="formHelp" />
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col v-for="field in formFields" :key="field.name" :md="field.span || 12" :span="24">
            <FormItem :label="field.label" :required="field.required">
              <Select
                v-if="field.type === 'select'"
                v-model:value="form[field.name]"
                allow-clear
                show-search
                option-filter-prop="label"
                class="w-full"
                :placeholder="field.placeholder || `请选择${field.label}`"
              >
                <SelectOption v-for="item in fieldOptions(field)" :key="item.value" :label="item.label" :value="item.value">{{ item.label }}</SelectOption>
              </Select>
              <InputNumber v-else-if="field.type === 'number'" v-model:value="form[field.name]" class="w-full" :min="field.min ?? 0" :placeholder="field.placeholder" />
              <DatePicker v-else-if="field.type === 'datetime'" v-model:value="form[field.name]" class="w-full" format="YYYY-MM-DD HH:mm:ss" value-format="YYYY-MM-DD HH:mm:ss" show-time input-read-only :placeholder="field.placeholder || '请选择时间'" />
              <Textarea v-else-if="field.type === 'textarea' || field.type === 'json' || field.type === 'tags'" v-model:value="form[field.name]" :auto-size="{ minRows: field.rows || (field.type === 'json' ? 5 : 3), maxRows: 12 }" :maxlength="field.maxlength || 5000" :placeholder="field.placeholder" show-count allow-clear />
              <Input v-else v-model:value="form[field.name]" :maxlength="field.maxlength || 255" :placeholder="field.placeholder" allow-clear />
              <div v-if="field.help" class="website-crud-page__field-help">{{ field.help }}</div>
            </FormItem>
          </Col>
        </Row>
      </Form>
      <template #footer><div class="flex justify-end gap-3"><Button @click="drawerOpen = false">取消</Button><Button type="primary" :loading="saving" @click="save">保存</Button></div></template>
    </Drawer>

    <Drawer :open="handleOpen" title="处理访客线索" :width="popupWidth.md" @close="handleOpen = false">
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
      <template #footer><div class="flex justify-end gap-3"><Button @click="handleOpen = false">取消</Button><Button type="primary" :loading="handling" @click="submitHandle">保存处理结果</Button></div></template>
    </Drawer>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { CrudTableHeader, Page } from '@vben/common-ui';
import { Alert, Button, Card, Col, DatePicker, Drawer, Form, FormItem, Input, InputNumber, message, Row, Select, SelectOption, Space, Table, Tag, Textarea, Tooltip, TypographyText } from 'ant-design-vue';
import { requestClient } from '#/api/request';
import CrudTableActions from '#/components/crud-table-actions.vue';
import SearchField from '#/components/crud-search-field.vue';
import { exportCrudXlsx } from '#/utils/crud-excel';
import { popupWidth } from '#/utils/popup';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { leadStatusOptions, optionColor, optionText, pageParams, parseJsonField, publishStatusOptions, splitStringList, statusColor, statusText, stringifyJson } from '../website-api';

type Field = { label: string; name: string; type?: 'datetime' | 'json' | 'number' | 'select' | 'tags' | 'textarea' | 'text'; required?: boolean; maxlength?: number; min?: number; options?: any[]; optionApi?: string; span?: number; rows?: number; placeholder?: string; help?: string; defaultValue?: any; emptyValue?: any; jsonFallback?: any };
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

const loading = ref(false); const saving = ref(false); const exporting = ref(false); const drawerOpen = ref(false);
const handling = ref(false); const handleOpen = ref(false); const activeLead = ref<any>(null);
const keyword = ref(''); const items = ref<any[]>([]);
const filters = reactive<Record<string, any>>({});
const form = reactive<Record<string, any>>({});
const handleForm = reactive<Record<string, any>>({ status: 'handled', remark: '' });
const dynamicOptions = reactive<Record<string, any[]>>({});
const pagination = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const listTitle = computed(() => props.listTitle || `${props.entityName}列表`);
const listDescription = computed(() => props.listDescription || '当前列表沿用后台权限、数据范围和租户隔离；导出仅导出当前筛选结果。');
const hasActions = computed(() => props.allowEdit || props.allowDelete || props.allowStatus || props.publishable || props.leadMode);
const actionColumnWidth = computed(() => estimateVisibleActionColumnWidth(items.value.length > 0 ? items.value.map(rowActions) : [rowActions({ id: 1, status: 1 })], { maxWidth: 220, minWidth: 110 }));
const tableColumns = computed(() => hasActions.value ? [...props.columns, { title: '操作', key: 'action', width: actionColumnWidth.value, fixed: 'right' as const }] : props.columns);
const tableScroll = computed(() => buildTableScrollX(tableColumns.value, { minWidth: 1100 }));
const remarkMaxLength = 1000;

function fieldOptions(field: Field) { return dynamicOptions[field.name] || field.options || []; }
function columnValueType(column: any) { return String(column?.valueType || ''); }
function recordValue(record: any, dataIndex: any) {
  if (Array.isArray(dataIndex)) {
    return dataIndex.reduce((value, key) => value?.[key], record);
  }

  return dataIndex ? record?.[dataIndex] : undefined;
}
function readable(record: any, dataIndex: any) { const value = recordValue(record, dataIndex); if (value === null || value === undefined || value === '') return '-'; return Array.isArray(value) || typeof value === 'object' ? JSON.stringify(value) : String(value); }
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
  const fields = [...props.formFields, ...props.filterFields].filter((field) => field.type === 'select' && field.optionApi);
  await Promise.all(fields.map(async (field) => {
    try {
      dynamicOptions[field.name] = await requestClient.get<any[]>(field.optionApi as string);
    } catch {
      dynamicOptions[field.name] = field.options || [];
    }
  }));
}
function defaultFieldValue(field: Field) { if (Object.prototype.hasOwnProperty.call(field, 'defaultValue')) return field.defaultValue; if (field.type === 'number') return 0; if (field.type === 'json') return stringifyJson(field.jsonFallback ?? {}); return ''; }
function formValue(field: Field, record: Record<string, any>) { const value = record[field.name]; if (field.type === 'json') return stringifyJson(value, stringifyJson(field.jsonFallback ?? {})); if (field.type === 'tags') return splitStringList(value).join('\n'); return value ?? defaultFieldValue(field); }
function resetForm(record: Record<string, any> = {}) { Object.keys(form).forEach((key) => delete form[key]); for (const field of props.formFields) form[field.name] = formValue(field, record); if (record.id) form.id = record.id; }
function openCreate() { resetForm(); drawerOpen.value = true; }
function openEdit(record: any) { resetForm(record); drawerOpen.value = true; }
function normalizePayload(payload: Record<string, any>) {
  const result: Record<string, any> = {};
  for (const field of props.formFields) {
    let value = payload[field.name];
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
function handleSearch() { pagination.current = 1; void load(); }
function handleReset() { keyword.value = ''; for (const field of props.filterFields) filters[field.name] = field.defaultValue; handleSearch(); }
function onTableChange(page: any) { pagination.current = page.current; pagination.pageSize = page.pageSize; void load(); }
async function save() { if (saving.value) return; saving.value = true; try { const payload = normalizePayload({ ...form }); if (form.id) await requestClient.put(`${props.api}/update/${form.id}`, payload); else await requestClient.post(`${props.api}/create`, payload); message.success('保存成功'); drawerOpen.value = false; await load(); } finally { saving.value = false; } }
async function deleteRow(id: number) { await requestClient.delete(`${props.api}/delete/${id}`); message.success('删除成功'); await load(); }
async function toggleStatus(record: any) { const next = Number(record.status) === 1 ? 0 : 1; await requestClient.put(`${props.api}/status/${record.id}`, { status: next }); message.success(next === 1 ? '已启用' : '已禁用'); await load(); }
async function publishRow(id: number) { await requestClient.put(`${props.api}/publish/${id}`, {}); message.success('发布成功'); await load(); }
async function offlineRow(id: number) { await requestClient.put(`${props.api}/offline/${id}`, {}); message.success('下线成功'); await load(); }
function openHandle(record: any) { activeLead.value = record; handleForm.status = record.status === 'pending' ? 'handled' : record.status || 'handled'; handleForm.remark = record.remark || ''; handleOpen.value = true; }
async function submitHandle() { if (!activeLead.value?.id || handling.value) return; handling.value = true; try { await requestClient.put(`${props.api}/handle/${activeLead.value.id}`, { ...handleForm }); message.success('处理成功'); handleOpen.value = false; await load(); } finally { handling.value = false; } }
function rowActions(record: any) {
  const id = Number(record?.id || 0);
  return [
    { label: '发布', visible: props.publishable && id > 0 && record?.publish_status !== 'published', onClick: () => publishRow(id) },
    { label: '下线', visible: props.publishable && id > 0 && record?.publish_status !== 'offline', confirmTitle: '确认下线该内容？', confirmContent: '下线后公开接口不会再返回该数据。', onClick: () => offlineRow(id) },
    { label: '处理', visible: props.leadMode && id > 0, onClick: () => openHandle(record) },
    { label: '编辑', visible: props.allowEdit && id > 0 && props.formFields.length > 0, onClick: () => openEdit(record) },
    { label: Number(record?.status) === 1 ? '禁用' : '启用', visible: props.allowStatus && id > 0 && !props.leadMode, danger: Number(record?.status) === 1, confirmTitle: `确认${Number(record?.status) === 1 ? '禁用' : '启用'}该${props.entityName}？`, confirmContent: '状态变更会影响后台可见性；禁用后公开接口不会返回对应数据。', onClick: () => toggleStatus(record) },
    { label: '删除', visible: props.allowDelete && id > 0, danger: true, confirmTitle: `确认删除该${props.entityName}？`, confirmContent: '删除后需要通过恢复接口或数据库备份找回，请确认不影响官网展示。', onClick: () => deleteRow(id) },
  ];
}
async function exportRows() { if (exporting.value) return; exporting.value = true; try { await exportCrudXlsx({ filename: `${props.entityName}_${new Date().toISOString().slice(0, 10)}.xlsx`, sheetName: props.entityName, columns: props.columns.map((column) => ({ key: Array.isArray(column.dataIndex) ? column.dataIndex.join('.') : String(column.dataIndex || column.key), title: column.title, formatter: (record: any) => readable(record, column.dataIndex) })), fetchPage: (page, pageSize) => requestClient.get<any>(`${props.api}/index`, { params: queryParams(page, pageSize) }), rules: ['导出只读取当前筛选条件、权限范围和租户范围内的数据。'] }); } finally { exporting.value = false; } }

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

.website-crud-page__field-help {
  margin-top: 6px;
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  font-size: 12px;
  line-height: 1.5;
}
</style>
