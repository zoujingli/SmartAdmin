<template>
  <Space class="crud-table-actions" :class="{ 'is-busy': running }" size="small">
    <Button
      v-for="item in inlineActions"
      :key="item.key"
      :danger="Boolean(item.action.danger)"
      :disabled="isActionDisabled(item)"
      :loading="isActionLoading(item)"
      size="small"
      type="link"
      @click="() => handleAction(item)"
    >
      {{ item.action.label }}
    </Button>

    <Dropdown v-if="dropdownActions.length > 0" :trigger="['click']">
      <Button :disabled="rowBusy && !dropdownRunning" :loading="dropdownRunning" size="small" type="link">
        {{ moreText }}<i class="i-lucide-chevron-down ml-1" />
      </Button>
      <template #overlay>
        <Menu>
          <MenuItem
            v-for="item in dropdownActions"
            :key="item.key"
            :danger="Boolean(item.action.danger)"
            :disabled="isActionDisabled(item)"
            @click="() => handleAction(item)"
          >
            <span>{{ item.action.label }}</span>
            <span v-if="isActionLoading(item)" class="crud-table-actions__pending">执行中</span>
          </MenuItem>
        </Menu>
      </template>
    </Dropdown>
  </Space>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Button, Dropdown, Menu, MenuItem, Modal, Space } from 'ant-design-vue';
import { useAsyncAction } from '#/utils/async-action';

interface CrudTableAction {
  confirmContent?: string;
  confirmTitle?: string;
  danger?: boolean;
  disabled?: boolean;
  key?: number | string;
  label: string;
  loading?: boolean;
  onClick?: () => Promise<unknown> | unknown;
  visible?: boolean;
}

interface CrudTableActionItem {
  action: CrudTableAction;
  key: string;
}

const props = withDefaults(defineProps<{
  actions: CrudTableAction[];
  inlineBeforeMore?: number;
  maxInline?: number;
  moreText?: string;
}>(), {
  inlineBeforeMore: 2,
  maxInline: 3,
  moreText: '更多',
});

const { isPending, run, running } = useAsyncAction();
const visibleActions = computed<CrudTableActionItem[]>(() => props.actions
  .map((action, index) => ({
    action,
    key: String(action.key ?? `${action.label}-${index}`),
  }))
  .filter((item) => item.action.visible !== false));
const shouldCollapse = computed(() => visibleActions.value.length > props.maxInline);
const inlineActions = computed(() => shouldCollapse.value
  ? visibleActions.value.slice(0, props.inlineBeforeMore)
  : visibleActions.value);
const dropdownActions = computed(() => shouldCollapse.value
  ? visibleActions.value.slice(props.inlineBeforeMore)
  : []);
const rowBusy = computed(() => running.value || visibleActions.value.some((item) => Boolean(item.action.loading)));
const dropdownRunning = computed(() => dropdownActions.value.some((item) => isActionLoading(item)));

function isActionLoading(item: CrudTableActionItem) {
  return Boolean(item.action.loading) || isPending(item.key);
}

function isActionDisabled(item: CrudTableActionItem) {
  return Boolean(item.action.disabled) || (rowBusy.value && !isActionLoading(item));
}

async function executeAction(item: CrudTableActionItem) {
  return run(item.key, () => item.action.onClick?.());
}

function handleAction(item: CrudTableActionItem) {
  if (isActionDisabled(item) || isActionLoading(item)) {
    return;
  }

  // 表格行操作统一在组件内处理确认弹层，确保收纳到“更多”后的危险操作仍保留二次确认。
  if (item.action.confirmTitle) {
    Modal.confirm({
      cancelText: '取消',
      content: item.action.confirmContent,
      okText: '确认',
      okType: item.action.danger ? 'danger' : 'primary',
      title: item.action.confirmTitle,
      onOk: () => executeAction(item),
    });
    return;
  }

  void executeAction(item);
}
</script>

<style scoped>
.crud-table-actions {
  white-space: nowrap;
}

.crud-table-actions.is-busy {
  cursor: progress;
}

.crud-table-actions__pending {
  margin-left: 8px;
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  font-size: 12px;
}
</style>
