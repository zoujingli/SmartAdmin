<template>
  <Modal
    v-model:open="uploadVisible"
    :title="`${moduleName}数据导入`"
    :width="popupWidth.xl"
    @cancel="handleUploadClose"
  >
    <div class="crud-import-dialog">
      <Alert
        class="mb-4"
        show-icon
        type="info"
        message="导入流程"
        :description="`请先下载模板，按模板字段填写后上传。系统会先预览数据，确认后逐条导入 ${moduleName}。`"
      />

      <div class="crud-import-dialog__section">
        <div class="crud-import-dialog__section-title">导入规则</div>
        <ul class="crud-import-dialog__rules">
          <li v-for="rule in normalizedRules" :key="rule">{{ rule }}</li>
        </ul>
      </div>

      <div class="crud-import-dialog__section">
        <div class="crud-import-dialog__section-title">字段说明</div>
        <Table
          :columns="fieldColumns"
          :data-source="fieldRows"
          :pagination="false"
          :scroll="{ x: 760, y: 220 }"
          row-key="key"
          size="small"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'required'">
              <Tag :color="record.required ? 'red' : 'default'">
                {{ record.required ? '必填' : '选填' }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'example'">
              <TypographyText type="secondary">{{ record.example || '-' }}</TypographyText>
            </template>
          </template>
        </Table>
      </div>

      <div class="crud-import-dialog__section">
        <div class="crud-import-dialog__section-head">
          <div>
            <div class="crud-import-dialog__section-title">模板与文件</div>
            <div class="crud-import-dialog__section-desc">支持 .xlsx、.xls、.csv，读取第一个工作表。</div>
          </div>
          <Button :disabled="importing" @click="downloadTemplate">
            <span class="i-lucide-download mr-1" />
            下载导入模板
          </Button>
        </div>

        <UploadDragger
          accept=".xlsx,.xls,.csv"
          :before-upload="handleBeforeUpload"
          :disabled="importing"
          :file-list="fileList"
          :max-count="1"
          :multiple="false"
          @remove="handleRemoveFile"
        >
          <p class="ant-upload-drag-icon">
            <span class="i-lucide-upload-cloud crud-import-dialog__upload-icon" />
          </p>
          <p class="ant-upload-text">点击或拖拽文件到此处上传</p>
          <p class="ant-upload-hint">上传后会先解析并预览，不会立即写入数据。</p>
        </UploadDragger>
      </div>

      <div v-if="errorMessage" class="crud-import-dialog__section">
        <Alert show-icon type="error" :message="errorMessage" />
      </div>
    </div>

    <template #footer>
      <Space>
        <Button @click="handleUploadClose">取消</Button>
      </Space>
    </template>
  </Modal>

  <Modal
    v-model:open="previewVisible"
    :closable="!importing"
    :keyboard="!importing"
    :mask-closable="!importing"
    :title="previewTitle"
    :width="popupWidth.xl"
    @cancel="handleClose"
  >
    <div class="crud-import-dialog">
      <Alert
        class="mb-4"
        show-icon
        type="info"
        message="数据预览"
        :description="`已完成文件解析，请核对预览数据。确认无误后点击“开始导入”，系统将逐条写入 ${moduleName}。`"
      />

      <div v-if="errorMessage" class="crud-import-dialog__section">
        <Alert show-icon type="error" :message="errorMessage" />
      </div>

      <div v-if="rows.length > 0" class="crud-import-dialog__section">
        <div class="crud-import-dialog__section-head">
          <div>
            <div class="crud-import-dialog__section-title">数据预览</div>
            <div class="crud-import-dialog__section-desc">
              共识别 {{ rows.length }} 条数据，仅展示前 {{ previewRows.length }} 条。
            </div>
          </div>
          <Tag color="processing">待导入 {{ rows.length }} 条</Tag>
        </div>
        <Table
          :columns="previewColumns"
          :data-source="previewRows"
          :pagination="false"
          :scroll="{ x: previewScrollX, y: 260 }"
          row-key="__index"
          size="small"
        />
      </div>

      <div v-if="status === 'importing' || status === 'done'" class="crud-import-dialog__section">
        <div class="crud-import-dialog__section-title">导入进度</div>
        <Progress :percent="progressPercent" :status="progressStatus" />
        <div class="crud-import-dialog__progress-text">
          已处理 {{ progress.current }} / {{ progress.total }}，成功 {{ progress.success }}，失败 {{ progress.failed }}
        </div>
      </div>

      <div v-if="status === 'done' && resultRows.length > 0" class="crud-import-dialog__section">
        <div class="crud-import-dialog__section-title">导入结果</div>
        <Table
          :columns="resultColumns"
          :data-source="resultRows"
          :pagination="false"
          :scroll="{ y: 220 }"
          row-key="index"
          size="small"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'status'">
              <Tag :color="record.status === 'success' ? 'green' : 'red'">
                {{ record.status === 'success' ? '成功' : '失败' }}
              </Tag>
            </template>
          </template>
        </Table>
      </div>
    </div>

    <template #footer>
      <Space>
        <Button :disabled="importing" @click="handleClose">
          {{ status === 'done' ? '关闭' : '取消' }}
        </Button>
        <Button v-if="status === 'preview'" :disabled="importing" @click="handleReupload">
          重新上传
        </Button>
        <Button
          v-if="status !== 'done'"
          type="primary"
          :disabled="rows.length === 0"
          :loading="importing"
          @click="startImport"
        >
          开始导入
        </Button>
      </Space>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import type { UploadProps } from 'ant-design-vue';

import { computed, ref } from 'vue';

import {
  Alert,
  Button,
  message,
  Modal,
  Progress,
  Space,
  Table,
  Tag,
  TypographyText,
  UploadDragger,
} from 'ant-design-vue';
import { popupWidth } from '#/utils/popup';

type CellValue = boolean | number | string | null | undefined;
type ImportStatus = 'done' | 'idle' | 'importing' | 'preview';

interface ImportColumn {
  example?: CellValue;
  importable?: boolean;
  key: string;
  required?: boolean;
  rule?: string;
  title: string;
}

interface ImportResultRow {
  index: number;
  message: string;
  status: 'failed' | 'success';
}

interface ImportRunResult {
  results: ImportResultRow[];
  success: number;
  total: number;
}

const props = defineProps<{
  columns: ImportColumn[];
  downloadTemplate: () => Promise<void> | void;
  moduleName: string;
  readRows: (file: File) => Promise<Array<Record<string, CellValue>>>;
  rules?: string[];
  runImport: (
    rows: Array<Record<string, CellValue>>,
    onProgress: (progress: { current: number; failed: number; success: number; total: number }) => void,
  ) => Promise<ImportRunResult>;
}>();

const emit = defineEmits<{
  close: [];
}>();

const uploadVisible = ref(true);
const previewVisible = ref(false);
const workflowClosed = ref(false);
const status = ref<ImportStatus>('idle');
const fileList = ref<UploadProps['fileList']>([]);
const rows = ref<Array<Record<string, CellValue>>>([]);
const errorMessage = ref('');
const progress = ref({ current: 0, failed: 0, success: 0, total: 0 });
const resultRows = ref<ImportResultRow[]>([]);

const importing = computed(() => status.value === 'importing');
const failedCount = computed(() => resultRows.value.filter((item) => item.status === 'failed').length);
const importableColumns = computed(() => props.columns.filter((column) => column.importable !== false));
const normalizedRules = computed(() => [
  '第一行必须是模板表头，字段名请保持不变。',
  '上传后仅做预览解析，点击“开始导入”后才会写入数据。',
  '导入按行串行提交；单行失败不会中断后续行，结果中会保留错误原因。',
  ...(props.rules || []),
]);

const fieldColumns = [
  { dataIndex: 'title', key: 'title', title: '字段', width: 140 },
  { dataIndex: 'required', key: 'required', title: '要求', width: 90 },
  { dataIndex: 'example', key: 'example', title: '示例', width: 160 },
  { dataIndex: 'rule', key: 'rule', title: '规则说明' },
];

const fieldRows = computed(() =>
  importableColumns.value.map((column) => ({
    example: column.example,
    key: column.key,
    required: Boolean(column.required),
    rule: column.rule || '-',
    title: column.title,
  })),
);

const previewColumns = computed(() => {
  const headers = Object.keys(rows.value[0] || {});
  return headers.map((header) => ({
    dataIndex: header,
    key: header,
    title: header,
    width: 140,
  }));
});

const previewRows = computed(() =>
  rows.value.slice(0, 8).map((row, index) => ({
    __index: index + 1,
    ...row,
  })),
);

const previewScrollX = computed(() => Math.max(760, previewColumns.value.length * 140));
const previewTitle = computed(() => (status.value === 'done' ? `${props.moduleName}导入结果` : `${props.moduleName}数据预览`));
const progressPercent = computed(() => {
  if (progress.value.total <= 0) {
    return 0;
  }
  return Math.round((progress.value.current / progress.value.total) * 100);
});
const progressStatus = computed(() => {
  if (status.value === 'done') {
    return failedCount.value > 0 ? 'exception' : 'success';
  }
  return 'active';
});

const resultColumns = [
  { dataIndex: 'index', key: 'index', title: '行号', width: 90 },
  { dataIndex: 'status', key: 'status', title: '状态', width: 100 },
  { dataIndex: 'message', key: 'message', title: '结果说明' },
];

async function downloadTemplate() {
  await props.downloadTemplate();
}

async function handleBeforeUpload(file: File) {
  errorMessage.value = '';
  resultRows.value = [];
  rows.value = [];
  status.value = 'idle';
  fileList.value = [{
    name: file.name,
    status: 'done',
    uid: String(Date.now()),
  }];

  try {
    const parsedRows = await props.readRows(file);
    if (workflowClosed.value) {
      return false;
    }
    if (parsedRows.length === 0) {
      errorMessage.value = '导入文件没有可识别的数据，请检查模板内容。';
      status.value = 'idle';
      return false;
    }
    rows.value = parsedRows;
    progress.value = { current: 0, failed: 0, success: 0, total: parsedRows.length };
    status.value = 'preview';
    openPreviewLayer();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : String(error);
    status.value = 'idle';
  }

  return false;
}

function handleRemoveFile() {
  if (importing.value) {
    return false;
  }
  resetUploadState();
  return true;
}

function resetUploadState() {
  fileList.value = [];
  rows.value = [];
  resultRows.value = [];
  errorMessage.value = '';
  progress.value = { current: 0, failed: 0, success: 0, total: 0 };
  status.value = 'idle';
}

function openPreviewLayer() {
  // 上传层只承载规则和文件选择；解析成功后切换到独立预览层，避免两个阶段内容挤在同一弹层内。
  if (workflowClosed.value) {
    return;
  }
  uploadVisible.value = false;
  window.setTimeout(() => {
    if (workflowClosed.value) {
      return;
    }
    previewVisible.value = true;
  }, 180);
}

function handleReupload() {
  if (importing.value) {
    return;
  }
  resetUploadState();
  previewVisible.value = false;
  window.setTimeout(() => {
    uploadVisible.value = true;
  }, 180);
}

async function startImport() {
  if (rows.value.length === 0 || importing.value) {
    return;
  }

  errorMessage.value = '';
  resultRows.value = [];
  status.value = 'importing';
  progress.value = { current: 0, failed: 0, success: 0, total: rows.value.length };

  try {
    const result = await props.runImport(rows.value, (nextProgress) => {
      progress.value = nextProgress;
    });
    resultRows.value = result.results;
    progress.value = {
      current: result.total,
      failed: result.total - result.success,
      success: result.success,
      total: result.total,
    };
    status.value = 'done';
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : String(error);
    status.value = 'preview';
  }
}

function handleUploadClose() {
  workflowClosed.value = true;
  uploadVisible.value = false;
  emit('close');
}

function handleClose() {
  if (importing.value) {
    message.warning('导入进行中，请等待完成后关闭');
    return;
  }
  workflowClosed.value = true;
  uploadVisible.value = false;
  previewVisible.value = false;
  emit('close');
}
</script>

<style scoped>
.crud-import-dialog {
  max-height: calc(100vh - 220px);
  overflow: auto;
  padding-right: 4px;
}

.crud-import-dialog__section + .crud-import-dialog__section {
  margin-top: 16px;
}

.crud-import-dialog__section-head {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.crud-import-dialog__section-title {
  margin-bottom: 8px;
  color: var(--ant-colorText);
  font-size: 15px;
  font-weight: 600;
}

.crud-import-dialog__section-desc {
  color: var(--ant-colorTextSecondary);
  font-size: 13px;
}

.crud-import-dialog__rules {
  margin: 0;
  padding-left: 20px;
  color: var(--ant-colorTextSecondary);
  line-height: 1.8;
}

.crud-import-dialog__upload-icon {
  color: var(--ant-colorPrimary);
  font-size: 42px;
}

.crud-import-dialog__progress-text {
  margin-top: 8px;
  color: var(--ant-colorTextSecondary);
  font-size: 13px;
}
</style>
