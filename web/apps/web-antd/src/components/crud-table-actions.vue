<template>
  <Space v-if="visibleActions.length > 0" class="crud-table-actions" :class="{ 'is-busy': running }" size="small">
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
      <Tooltip v-if="item.action.tooltip" :title="item.action.tooltip">
        <span class="crud-table-actions__content">
          <i v-if="item.action.icon" :class="item.action.icon" />
          <span>{{ item.action.label }}</span>
        </span>
      </Tooltip>
      <span v-else class="crud-table-actions__content">
        <i v-if="item.action.icon" :class="item.action.icon" />
        <span>{{ item.action.label }}</span>
      </span>
    </Button>

    <Dropdown v-if="dropdownActions.length > 0" :trigger="['click']">
      <Button :disabled="rowBusy && !dropdownRunning" :loading="dropdownRunning" size="small" type="link">
        {{ moreText }}<i class="i-lucide-chevron-down ml-1" />
      </Button>
      <template #overlay>
        <Menu>
          <template v-for="entry in dropdownEntries" :key="entry.key">
            <MenuItem v-if="entry.type === 'group'" :key="entry.key" class="crud-table-actions__group-title" disabled>
              {{ entry.label }}
            </MenuItem>
            <MenuItem
              v-else
              :key="entry.item.key"
              :danger="Boolean(entry.item.action.danger)"
              :disabled="isActionDisabled(entry.item)"
              @click="() => handleAction(entry.item)"
            >
              <Tooltip v-if="entry.item.action.tooltip" :title="entry.item.action.tooltip">
                <span class="crud-table-actions__content">
                  <i v-if="entry.item.action.icon" :class="entry.item.action.icon" />
                  <span>{{ entry.item.action.label }}</span>
                </span>
              </Tooltip>
              <span v-else class="crud-table-actions__content">
                <i v-if="entry.item.action.icon" :class="entry.item.action.icon" />
                <span>{{ entry.item.action.label }}</span>
              </span>
              <span v-if="isActionLoading(entry.item)" class="crud-table-actions__pending">{{ loadingText }}</span>
            </MenuItem>
          </template>
        </Menu>
      </template>
    </Dropdown>
  </Space>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Button, Dropdown, Menu, MenuItem, Modal, Space, Tooltip } from 'ant-design-vue';
import { useAsyncAction } from '#/utils/async-action';

export interface CrudTableAction {
  confirmCancelText?: string;
  confirmContent?: string;
  confirmOkText?: string;
  confirmTitle?: string;
  danger?: boolean;
  disabled?: boolean;
  group?: string;
  groupOrder?: number;
  icon?: string;
  inline?: boolean;
  key?: number | string;
  label: string;
  loading?: boolean;
  onClick?: () => Promise<unknown> | unknown;
  tooltip?: string;
  visible?: boolean;
}

interface CrudTableActionItem {
  action: CrudTableAction;
  key: string;
}

type CrudTableDropdownEntry = {
  key: string;
  label: string;
  type: 'group';
} | {
  item: CrudTableActionItem;
  key: string;
  type: 'action';
};

const props = withDefaults(defineProps<{
  actions: CrudTableAction[];
  explicitInline?: boolean;
  inlineBeforeMore?: number;
  loadingText?: string;
  maxInline?: number;
  moreText?: string;
}>(), {
  explicitInline: false,
  inlineBeforeMore: 2,
  loadingText: '执行中',
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
// 任务等高密度列表可显式固定直显动作，其余已授权动作统一进入“更多”；默认模式保持原阈值行为。
const inlineActions = computed(() => {
  if (props.explicitInline) {
    return visibleActions.value.filter((item) => item.action.inline === true);
  }
  return shouldCollapse.value
    ? visibleActions.value.slice(0, props.inlineBeforeMore)
    : visibleActions.value;
});
const dropdownActions = computed(() => {
  if (props.explicitInline) {
    return visibleActions.value.filter((item) => item.action.inline !== true);
  }
  return shouldCollapse.value
    ? visibleActions.value.slice(props.inlineBeforeMore)
    : [];
});
const dropdownEntries = computed<CrudTableDropdownEntry[]>(() => {
  const actions = dropdownActions.value;
  if (!actions.some((item) => normalizedActionGroup(item.action) !== '')) {
    return actions.map((item) => ({ item, key: item.key, type: 'action' }));
  }

  const groups = new Map<string, { firstIndex: number; label: string; order: number; rows: CrudTableActionItem[] }>();
  actions.forEach((item, index) => {
    const label = normalizedActionGroup(item.action);
    const key = label || `__ungrouped_${index}`;
    const order = Number.isFinite(Number(item.action.groupOrder)) ? Number(item.action.groupOrder) : 9999;
    if (!groups.has(key)) {
      groups.set(key, { firstIndex: index, label, order, rows: [] });
    }
    groups.get(key)!.rows.push(item);
  });

  return Array.from(groups.values())
    .sort((a, b) => (a.order - b.order) || (a.firstIndex - b.firstIndex))
    .flatMap((group, index) => [
      ...(group.label ? [{ key: `group-${index}-${group.label}`, label: group.label, type: 'group' as const }] : []),
      ...group.rows.map((item) => ({ item, key: item.key, type: 'action' as const })),
    ]);
});
const rowBusy = computed(() => running.value || visibleActions.value.some((item) => Boolean(item.action.loading)));
const dropdownRunning = computed(() => dropdownActions.value.some((item) => isActionLoading(item)));

function normalizedActionGroup(action: CrudTableAction) {
  return String(action.group || '').trim();
}

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
      cancelText: item.action.confirmCancelText || '取消',
      content: item.action.confirmContent,
      okText: item.action.confirmOkText || '确认',
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

.crud-table-actions__content {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-width: 0;
  vertical-align: middle;
}

.crud-table-actions__pending {
  margin-left: 8px;
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  font-size: 12px;
}

.crud-table-actions__group-title {
  height: auto;
  margin-top: 4px;
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground))) !important;
  cursor: default;
  font-size: 12px;
  font-weight: 600;
  line-height: 18px;
  opacity: 1 !important;
}
</style>
