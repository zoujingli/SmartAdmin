<template>
  <Modal
    v-model:open="visible"
    :closable="!exporting"
    :keyboard="!exporting"
    :mask-closable="!exporting"
    :title="`${moduleName}数据导出`"
    :width="popupWidth.sm"
    @cancel="handleClose"
  >
    <div class="space-y-4">
      <Alert
        show-icon
        type="info"
        message="导出说明"
        :description="`将按当前列表筛选条件分页读取 ${moduleName} 数据，后台仅返回权限范围内的数据，Excel 文件由浏览器生成并下载。`"
      />

      <Descriptions bordered :column="1" size="small">
        <DescriptionsItem label="导出模块">{{ moduleName }}</DescriptionsItem>
        <DescriptionsItem label="文件名称">{{ filename }}</DescriptionsItem>
        <DescriptionsItem v-if="sheetCount > 1" label="Sheet 数量">{{ sheetCount }} 个</DescriptionsItem>
        <DescriptionsItem label="字段数量">{{ columnCount }} 个字段</DescriptionsItem>
        <DescriptionsItem label="分页大小">{{ pageSize }} 条 / 页</DescriptionsItem>
      </Descriptions>

      <ul class="crud-export-dialog__rules">
        <li>导出只读取当前权限和当前筛选条件下的数据。</li>
        <li>数据会按分页串行拉取，不调用后台文件导出接口，完成后自动下载 Excel 文件。</li>
        <li v-for="rule in rules" :key="rule">{{ rule }}</li>
      </ul>

      <div v-if="status === 'exporting' || status === 'done'" class="crud-export-dialog__progress">
        <Progress :percent="progressPercent" :status="status === 'done' ? 'success' : 'active'" />
        <div class="crud-export-dialog__progress-text">
          已读取 {{ progress.loaded }} / {{ progress.total || progress.loaded }} 条
        </div>
      </div>

      <Alert v-if="status === 'done'" show-icon type="success" :message="`导出完成，共 ${progress.loaded} 条。`" />
      <Alert v-if="errorMessage" show-icon type="error" :message="errorMessage" />
    </div>

    <template #footer>
      <Space>
        <Button :disabled="exporting" @click="handleClose">
          {{ status === 'done' ? '关闭' : '取消' }}
        </Button>
        <Button v-if="status !== 'done'" type="primary" :loading="exporting" @click="startExport">
          确认导出
        </Button>
      </Space>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

import {
  Alert,
  Button,
  Descriptions,
  DescriptionsItem,
  message,
  Modal,
  Progress,
  Space,
} from 'ant-design-vue';
import { popupWidth } from '#/utils/popup';

type ExportStatus = 'confirm' | 'done' | 'exporting' | 'failed';

const props = defineProps<{
  columnCount: number;
  filename: string;
  moduleName: string;
  pageSize: number;
  rules?: string[];
  sheetCount?: number;
  runExport: (
    onProgress: (progress: { loaded: number; total: number }) => void,
  ) => Promise<{ total: number }>;
}>();

const emit = defineEmits<{
  close: [];
}>();

const visible = ref(true);
const status = ref<ExportStatus>('confirm');
const errorMessage = ref('');
const progress = ref({ loaded: 0, total: 0 });

const exporting = computed(() => status.value === 'exporting');
const sheetCount = computed(() => props.sheetCount || 1);
const progressPercent = computed(() => {
  if (progress.value.total <= 0) {
    return progress.value.loaded > 0 ? 99 : 0;
  }
  return Math.min(100, Math.round((progress.value.loaded / progress.value.total) * 100));
});
const rules = computed(() => props.rules || []);

async function startExport() {
  if (exporting.value) {
    return;
  }

  status.value = 'exporting';
  errorMessage.value = '';
  progress.value = { loaded: 0, total: 0 };

  try {
    const result = await props.runExport((nextProgress) => {
      progress.value = nextProgress;
    });
    progress.value = { loaded: result.total, total: result.total };
    status.value = 'done';
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : String(error);
    status.value = 'failed';
  }
}

function handleClose() {
  if (exporting.value) {
    message.warning('导出进行中，请等待完成后关闭');
    return;
  }
  visible.value = false;
  emit('close');
}
</script>

<style scoped>
.crud-export-dialog__rules {
  margin: 0;
  padding-left: 20px;
  color: var(--ant-colorTextSecondary);
  line-height: 1.8;
}

.crud-export-dialog__progress-text {
  margin-top: 8px;
  color: var(--ant-colorTextSecondary);
  font-size: 13px;
}
</style>
