<template>
  <Modal
    :open="open"
    :title="title"
    :width="popupWidth.sm"
    :mask-closable="finished"
    :keyboard="finished"
    @cancel="close"
  >
    <div class="async-task-progress-dialog">
      <Alert
        v-if="statusType !== 'info'"
        show-icon
        :type="statusType"
        :message="statusMessage"
      />

      <Progress
        :percent="progressPercent"
        :status="progressStatus"
      />

      <div class="async-task-progress-dialog__message">
        {{ progressMessage }}
      </div>

      <div class="async-task-progress-dialog__logs">
        <div class="async-task-progress-dialog__logs-title">执行消息</div>
        <div v-if="logs.length" class="async-task-progress-dialog__logs-box">
          <div v-for="line in logs" :key="line" class="async-task-progress-dialog__log-line">
            {{ line }}
          </div>
        </div>
        <Empty v-else :image="Empty.PRESENTED_IMAGE_SIMPLE" description="暂无消息" />
      </div>
    </div>

    <template #footer>
      <Space>
        <Button @click="close">{{ finished ? '关闭' : '后台运行' }}</Button>
        <Button :loading="polling" @click="loadStatus">刷新</Button>
      </Space>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Alert, Button, Empty, message, Modal, Progress, Space } from 'ant-design-vue';
import { requestClient } from '#/api/request';
import { popupWidth } from '#/utils/popup';

type TaskProgress = {
  current?: number;
  message?: string;
  percent?: number;
  total?: number;
  updated_at?: string;
};

const props = withDefaults(defineProps<{
  open: boolean;
  taskId: string;
  title?: string;
}>(), {
  title: '任务进度',
});

const emit = defineEmits<{
  finish: [];
  'update:open': [value: boolean];
}>();

const polling = ref(false);
const stat = ref('unknown');
const progress = ref<TaskProgress | null>(null);
const logs = ref<string[]>([]);
let timer: ReturnType<typeof setInterval> | null = null;
let finishedEmitted = false;

const finished = computed(() => ['done', 'fail', 'unknown'].includes(stat.value) && !polling.value);
const progressPercent = computed(() => {
  const percent = Number(progress.value?.percent ?? 0);
  if (Number.isFinite(percent) && percent > 0) {
    return Math.min(100, Math.max(0, Math.round(percent)));
  }
  const current = Number(progress.value?.current ?? 0);
  const total = Number(progress.value?.total ?? 0);
  return total > 0 ? Math.min(100, Math.round((current / total) * 100)) : 0;
});
const progressStatus = computed(() => stat.value === 'fail' ? 'exception' : (stat.value === 'done' ? 'success' : 'active'));
const progressMessage = computed(() => progress.value?.message || statusMessage.value);
const statusType = computed(() => {
  if (stat.value === 'done') return 'success';
  if (stat.value === 'fail' || stat.value === 'unknown') return 'error';
  return 'info';
});
const statusMessage = computed(() => {
  if (stat.value === 'done') return '任务已完成';
  if (stat.value === 'fail') return '任务执行失败';
  if (stat.value === 'unknown') return '任务不存在或已过期';
  return '任务执行中';
});

watch(() => [props.open, props.taskId], ([open]) => {
  stopTimer();
  finishedEmitted = false;
  if (open && props.taskId) {
    void loadStatus();
    timer = setInterval(() => void loadStatus(), 1000);
  }
}, { immediate: true });

onBeforeUnmount(stopTimer);

async function loadStatus() {
  if (!props.taskId || polling.value) {
    return;
  }
  polling.value = true;
  try {
    const data = await requestClient.get<any>('system/task/status', {
      params: { task_id: props.taskId, limit: 50 },
    });
    stat.value = String(data?.stat || 'unknown');
    progress.value = data?.progress || null;
    logs.value = Array.isArray(data?.logs) ? data.logs : [];
    if (['done', 'fail', 'unknown'].includes(stat.value)) {
      stopTimer();
      if (!finishedEmitted) {
        finishedEmitted = true;
        emit('finish');
      }
    }
  } catch (error: any) {
    stopTimer();
    message.warning(error?.message || '读取任务状态失败');
  } finally {
    polling.value = false;
  }
}

function close() {
  stopTimer();
  emit('update:open', false);
}

function stopTimer() {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}
</script>

<style scoped>
.async-task-progress-dialog {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.async-task-progress-dialog__message {
  color: var(--ant-colorTextSecondary);
  font-size: 13px;
  line-height: 1.6;
}

.async-task-progress-dialog__logs-title {
  margin-bottom: 8px;
  color: var(--ant-colorText);
  font-weight: 500;
}

.async-task-progress-dialog__logs-box {
  max-height: 240px;
  overflow: auto;
  border: 1px solid var(--ant-colorBorderSecondary);
  border-radius: 6px;
  background: var(--ant-colorBgContainer);
  padding: 10px 12px;
}

.async-task-progress-dialog__log-line {
  color: var(--ant-colorTextSecondary);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
  line-height: 1.7;
  white-space: pre-wrap;
}
</style>
