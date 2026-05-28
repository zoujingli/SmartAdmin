<template>
  <AppDrawer
    :confirm-loading="saving"
    :open="visible"
    :title="title"
    :width="drawerWidth"
    ok-text="确定"
    @close="handleCancel"
    @ok="handleOk"
  >
    <Form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
      <Row :gutter="[16, 0]">
        <Col :span="12">
          <FormItem label="角色名称" name="name">
            <Input v-model:value="formData.name" placeholder="请输入角色名称" />
          </FormItem>
        </Col>

        <Col :span="6">
          <FormItem label="排序" name="sort">
            <InputNumber v-model:value="formData.sort" :min="0" style="width: 100%" placeholder="请输入排序" />
          </FormItem>
        </Col>
        <Col :span="6">
          <FormItem label="状态" name="status">
            <RadioGroup v-model:value="formData.status" :options="statusOptions" option-type="button" />
          </FormItem>
        </Col>

        <Col :span="24">
          <FormItem label="数据范围" name="scope">
            <RadioGroup v-model:value="formData.scope" :options="scopeOptions" option-type="button" />
          </FormItem>
        </Col>

        <Col v-if="canAssignPermissions" :span="24">
          <FormItem name="menuIds">
            <FormItemRest>
              <div class="role-permission-header">
                <span class="role-permission-label">
                  权限菜单
                  <span class="role-permission-label__count">
                    已选择 {{ selectedVisibleMenuIds.length }} / {{ allVisibleMenuIds.length }} 个权限节点
                  </span>
                </span>
                <div class="role-permission-toolbar__actions">
                  <Button size="small" @click="handleExpandAllGroups">展开全部</Button>
                  <Button size="small" @click="handleCollapseAllGroups">收起</Button>
                  <Button size="small" @click="handleSelectAll">全选</Button>
                  <Button size="small" @click="handleClearAll">清空</Button>
                </div>
              </div>
              <div class="role-permission-shell">
                <div class="role-permission-body">
                  <Collapse v-model:activeKey="activePermissionGroupKeys" ghost class="role-permission-collapse">
                    <CollapsePanel
                      v-for="group in permissionGroups"
                      :key="String(group.id)"
                    >
                      <template #header>
                        <span class="role-permission-panel-heading">
                          <span class="role-permission-panel-heading__name">{{ group.name }}</span>
                          <span class="role-permission-panel-heading__count">
                            已选 {{ getGroupSelectedCount(group) }} / {{ getGroupVisibleIds(group).length }}
                          </span>
                        </span>
                      </template>
                      <template #extra>
                        <Space size="small" class="role-permission-panel-actions" @click.stop>
                          <Button size="small" type="link" @click.stop="handleGroupSelectAll(group)">全选本组</Button>
                          <Button size="small" type="link" @click.stop="handleGroupClear(group)">清空本组</Button>
                        </Space>
                      </template>
                      <div class="role-permission-group">
                        <table class="role-permission-table">
                          <thead>
                            <tr>
                              <th class="role-permission-table__head-cell role-permission-table__head-cell--section">一级节点</th>
                              <th class="role-permission-table__head-cell role-permission-table__head-cell--branch">二级节点</th>
                              <th class="role-permission-table__head-cell">三级节点</th>
                            </tr>
                          </thead>
                          <tbody>
                            <template v-for="section in getNodeChildren(group)" :key="String(section.id)">
                              <tr
                                v-if="getNodeChildren(section).length === 0"
                                class="role-permission-table__row role-permission-table__row--single"
                              >
                                <td class="role-permission-table__cell role-permission-table__cell--section">
                                  <Checkbox
                                    :checked="isNodeChecked(section)"
                                    @change="(event) => handleNodeCheck(section, event.target.checked)"
                                  >
                                    <span class="role-permission-table__title">{{ section.name }}</span>
                                  </Checkbox>
                                </td>
                                <td class="role-permission-table__cell role-permission-table__cell--branch">
                                  <span class="role-permission-table__empty">—</span>
                                </td>
                                <td class="role-permission-table__cell role-permission-table__cell--leaves">
                                  <span class="role-permission-table__empty">—</span>
                                </td>
                              </tr>

                              <template v-else>
                                <tr
                                  v-if="getSectionLeafNodes(section).length > 0"
                                  :key="`${section.id}-direct-leaves`"
                                  class="role-permission-table__row"
                                >
                                  <td
                                    class="role-permission-table__cell role-permission-table__cell--section"
                                    :rowspan="getSectionTableRowCount(section)"
                                  >
                                    <Checkbox
                                      :checked="isNodeChecked(section)"
                                      :indeterminate="isNodeIndeterminate(section)"
                                      @change="(event) => handleNodeCheck(section, event.target.checked)"
                                    >
                                      <span class="role-permission-table__title">{{ section.name }}</span>
                                    </Checkbox>
                                  </td>

                                  <td class="role-permission-table__cell role-permission-table__cell--branch">
                                    <span class="role-permission-table__empty">—</span>
                                  </td>

                                  <td class="role-permission-table__cell role-permission-table__cell--leaves">
                                    <div class="role-permission-leaf-grid">
                                      <Checkbox
                                        v-for="leaf in getSectionLeafNodes(section)"
                                        :key="String(leaf.id)"
                                        :checked="isNodeChecked(leaf)"
                                        @change="(event) => handleNodeCheck(leaf, event.target.checked)"
                                      >
                                        {{ leaf.name }}
                                      </Checkbox>
                                    </div>
                                  </td>
                                </tr>

                                <tr
                                  v-for="(node, nodeIndex) in getSectionBranchNodes(section)"
                                  :key="`${section.id}-${node.id}`"
                                  class="role-permission-table__row"
                                >
                                  <td
                                    v-if="nodeIndex === 0 && getSectionLeafNodes(section).length === 0"
                                    class="role-permission-table__cell role-permission-table__cell--section"
                                    :rowspan="getSectionTableRowCount(section)"
                                  >
                                    <Checkbox
                                      :checked="isNodeChecked(section)"
                                      :indeterminate="isNodeIndeterminate(section)"
                                      @change="(event) => handleNodeCheck(section, event.target.checked)"
                                    >
                                      <span class="role-permission-table__title">{{ section.name }}</span>
                                    </Checkbox>
                                  </td>

                                  <td class="role-permission-table__cell role-permission-table__cell--branch">
                                    <Checkbox
                                      :checked="isNodeChecked(node)"
                                      :indeterminate="isNodeIndeterminate(node)"
                                      @change="(event) => handleNodeCheck(node, event.target.checked)"
                                    >
                                      {{ node.name }}
                                    </Checkbox>
                                  </td>

                                  <td class="role-permission-table__cell role-permission-table__cell--leaves">
                                    <div class="role-permission-leaf-grid">
                                      <Checkbox
                                        v-for="leaf in getNodeChildren(node)"
                                        :key="String(leaf.id)"
                                        :checked="isNodeChecked(leaf)"
                                        @change="(event) => handleNodeCheck(leaf, event.target.checked)"
                                      >
                                        {{ leaf.name }}
                                      </Checkbox>
                                    </div>
                                  </td>
                                </tr>
                              </template>
                            </template>
                          </tbody>
                        </table>
                      </div>
                    </CollapsePanel>
                  </Collapse>
                </div>
              </div>
              <div v-if="formData.allPermissions" class="mt-2 text-xs text-foreground/60">
                当前角色使用全量授权标记，保持全选后会继续保存为 <code>*</code> 通配权限。
              </div>
            </FormItemRest>
          </FormItem>
        </Col>

        <Col :span="24">
          <FormItem label="备注" name="remark">
            <Input.TextArea v-model:value="formData.remark" :rows="3" :maxlength="200" show-count placeholder="请输入备注" />
          </FormItem>
        </Col>
      </Row>
    </Form>
  </AppDrawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Button, Checkbox, Col, Collapse, CollapsePanel, Form, FormItem, FormItemRest, Input, InputNumber, message, RadioGroup, Row, Space, theme } from 'ant-design-vue';

import AppDrawer from '#/components/app-drawer.vue';
import { roleApiService } from '#/api/system/role';

import { ROLE_SCOPE_DEFAULT, ROLE_SCOPE_OPTIONS } from '../constants';
import type { RoleFormData, RoleType } from '../types';
import { popupWidth } from '#/utils/popup';
interface Props {
  canAssignPermissions?: boolean;
  visible: boolean;
  data?: RoleType;
  menuTreeOptions: any[];
}

interface Emits {
  (e: 'update:visible', visible: boolean): void;
  (e: 'success'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { token } = theme.useToken();

const formRef = ref();
const saving = ref(false);
const formData = reactive<RoleFormData>({
  id: 0,
  name: '',
  scope: ROLE_SCOPE_DEFAULT,
  status: 1,
  sort: 0,
  remark: '',
  menuIds: [],
  allPermissions: false,
});

const statusOptions = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];

const scopeOptions = ROLE_SCOPE_OPTIONS;

const title = computed(() => (formData.id ? '编辑角色' : '新增角色'));
const drawerWidth = computed(() => (props.canAssignPermissions ? popupWidth.lg : popupWidth.md));
const permissionGroups = computed(() => props.menuTreeOptions ?? []);
const permissionGroupKeys = computed(() => permissionGroups.value.map((item: any) => String(item.id)));
const activePermissionGroupKeys = ref<string[]>([]);

const getNodeId = (node: any): null | number => {
  const id = Number(node?.id);
  return Number.isFinite(id) ? id : null;
};

const getNodeChildren = (node: any): any[] => {
  return Array.isArray(node?.children) ? node.children : [];
};

const getSectionLeafNodes = (section: any): any[] => {
  return getNodeChildren(section).filter((node) => getNodeChildren(node).length === 0);
};

const getSectionBranchNodes = (section: any): any[] => {
  return getNodeChildren(section).filter((node) => getNodeChildren(node).length > 0);
};

/**
 * 权限树存在一级、二级、三级不同深度：二级已是权限节点时归入“三级节点”列，
 * 只有确实还有子级的节点才展示在“二级节点”列，避免出现整列无意义的占位符。
 */
const getSectionTableRowCount = (section: any): number => {
  const directLeafRowCount = getSectionLeafNodes(section).length > 0 ? 1 : 0;
  return Math.max(1, directLeafRowCount + getSectionBranchNodes(section).length);
};

const formRules: any = {
  name: [
    { required: true, message: '请输入角色名称', trigger: 'blur' },
    { min: 2, max: 20, message: '角色名称长度为 2-20 个字符', trigger: 'blur' },
  ],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
  scope: [{ required: true, message: '请选择数据范围', trigger: 'change' }],
  sort: [{ required: true, message: '请输入排序', trigger: 'blur' }],
};

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;

    if (props.data) {
      Object.assign(formData, {
        ...props.data,
        scope: props.data.scope ?? ROLE_SCOPE_DEFAULT,
        menuIds: [...(props.data.menuIds ?? [])],
        allPermissions: Boolean(props.data.allPermissions),
      });
    } else {
      resetForm();
    }

    resetActivePermissionGroups();
  },
);

watch(
  permissionGroupKeys,
  (keys) => {
    if (!props.visible) return;
    if (activePermissionGroupKeys.value.length === 0) {
      activePermissionGroupKeys.value = keys.slice(0, 1);
    }
  },
);

const resetForm = () => {
  Object.assign(formData, {
    id: 0,
    name: '',
    scope: ROLE_SCOPE_DEFAULT,
    status: 1,
    sort: 0,
    remark: '',
    menuIds: [],
    allPermissions: false,
  });
};

const flattenMenus = (menus: any[], map: Map<number, string>) => {
  for (const item of menus ?? []) {
    if (typeof item?.id === 'number') {
      map.set(item.id, String(item?.code ?? ''));
    }
    if (Array.isArray(item?.children) && item.children.length > 0) {
      flattenMenus(item.children, map);
    }
  }
};

const collectNodeIds = (nodes: any[], includeRoot = true): number[] => {
  const ids: number[] = [];

  const visit = (items: any[]) => {
    for (const node of items ?? []) {
      const id = getNodeId(node);
      if (id !== null) {
        ids.push(id);
      }
      const children = getNodeChildren(node);
      if (children.length > 0) {
        visit(children);
      }
    }
  };

  visit(nodes);

  if (!includeRoot && nodes.length === 1) {
    const rootId = getNodeId(nodes[0]);
    return Array.from(new Set(ids.filter((id) => id !== rootId)));
  }

  return Array.from(new Set(ids));
};

const getVisibleNodeIds = (node: any): number[] => {
  const children = getNodeChildren(node);
  if (children.length === 0) {
    const id = getNodeId(node);
    return id === null ? [] : [id];
  }

  return collectNodeIds(children);
};

const getAllMenuIds = (): number[] => {
  return collectNodeIds(props.menuTreeOptions);
};

const getAllVisibleMenuIds = (): number[] => {
  return permissionGroups.value.flatMap((group) => getVisibleNodeIds(group));
};

const allMenuIds = computed(() => getAllMenuIds());
const allVisibleMenuIds = computed(() => Array.from(new Set(getAllVisibleMenuIds())));
const selectedMenuIdSet = computed(() => new Set(formData.menuIds ?? []));
const selectedVisibleMenuIds = computed(() => {
  const visible = new Set(allVisibleMenuIds.value);
  return (formData.menuIds ?? []).filter((id) => visible.has(id));
});

const normalizeMenuIds = (rawIds: Iterable<number>): number[] => {
  const source = new Set(Array.from(rawIds).map((id) => Number(id)).filter((id) => Number.isFinite(id)));
  const normalized = new Set<number>();

  const visit = (node: any): boolean => {
    const id = getNodeId(node);
    const children = getNodeChildren(node);

    if (children.length === 0) {
      if (id !== null && source.has(id)) {
        normalized.add(id);
        return true;
      }
      return false;
    }

    const allChildrenChecked = children.map((child) => visit(child)).every(Boolean);
    if (allChildrenChecked && id !== null) {
      normalized.add(id);
      return true;
    }

    return false;
  };

  for (const group of permissionGroups.value) {
    visit(group);
  }

  return Array.from(normalized);
};

const applyMenuIds = (ids: Iterable<number>) => {
  formData.menuIds = normalizeMenuIds(ids);
};

const handleNodeCheck = (node: any, checked: boolean) => {
  const next = new Set(formData.menuIds ?? []);
  const nodeIds = collectNodeIds([node]);

  for (const id of nodeIds) {
    if (checked) {
      next.add(id);
    } else {
      next.delete(id);
    }
  }

  applyMenuIds(next);
};

const isNodeChecked = (node: any): boolean => {
  const nodeIds = collectNodeIds([node]);
  return nodeIds.length > 0 && nodeIds.every((id) => selectedMenuIdSet.value.has(id));
};

const isNodeIndeterminate = (node: any): boolean => {
  const nodeIds = collectNodeIds([node]);
  const checkedCount = nodeIds.filter((id) => selectedMenuIdSet.value.has(id)).length;
  return checkedCount > 0 && checkedCount < nodeIds.length;
};

const getGroupVisibleIds = (group: any): number[] => {
  return Array.from(new Set(getVisibleNodeIds(group)));
};

const getGroupSelectedCount = (group: any): number => {
  const visibleIds = new Set(getGroupVisibleIds(group));
  return (formData.menuIds ?? []).filter((id) => visibleIds.has(id)).length;
};

/**
 * 权限节点很多时默认只展开首个命中的模块，避免打开角色抽屉后所有模块铺满导致视觉过散。
 * 用户仍可通过“展开全部”查看完整权限树，已选统计保留在折叠头上便于定位。
 */
const resetActivePermissionGroups = () => {
  const selectedGroup = permissionGroups.value.find((group) => getGroupSelectedCount(group) > 0);
  const defaultKey = selectedGroup?.id ?? permissionGroups.value[0]?.id;
  activePermissionGroupKeys.value = defaultKey === undefined ? [] : [String(defaultKey)];
};

const handleExpandAllGroups = () => {
  activePermissionGroupKeys.value = [...permissionGroupKeys.value];
};

const handleCollapseAllGroups = () => {
  activePermissionGroupKeys.value = [];
};

const handleSelectAll = () => {
  applyMenuIds(allVisibleMenuIds.value);
};

const handleClearAll = () => {
  formData.menuIds = [];
};

const handleGroupSelectAll = (group: any) => {
  const next = new Set(formData.menuIds ?? []);
  for (const id of collectNodeIds([group])) {
    next.add(id);
  }
  applyMenuIds(next);
};

const handleGroupClear = (group: any) => {
  const next = new Set(formData.menuIds ?? []);
  for (const id of collectNodeIds([group])) {
    next.delete(id);
  }
  applyMenuIds(next);
};

const mapMenuIdsToNodes = (menuIds: number[]): string[] => {
  const idToCode = new Map<number, string>();
  flattenMenus(props.menuTreeOptions, idToCode);

  const nodes: string[] = [];
  for (const menuId of menuIds ?? []) {
    const code = idToCode.get(menuId);
    if (code) nodes.push(code);
  }
  return Array.from(new Set(nodes));
};

const handleOk = async () => {
  if (saving.value) return;

  try {
    saving.value = true;
    await formRef.value?.validate();

    const menuIds = Array.from(new Set(formData.menuIds ?? []));
    const keepWildcard =
      Boolean(formData.allPermissions)
      && allMenuIds.value.length > 0
      && allMenuIds.value.every((id) => menuIds.includes(id));

    const permissionNodes = keepWildcard ? ['*'] : mapMenuIdsToNodes(menuIds);
    const submitData: any = {
      name: formData.name,
      scope: formData.scope,
      status: formData.status,
      sort: formData.sort,
      remark: formData.remark,
    };

    let roleId = Number(formData.id || 0);
    if (formData.id) {
      await roleApiService.updateRole(formData.id, submitData);
    } else {
      const created = await roleApiService.createRole(submitData);
      roleId = Number((created as any)?.id || 0);
    }

    if (props.canAssignPermissions) {
      if (!roleId) {
        throw new Error('角色保存成功，但缺少角色 ID，无法分配权限');
      }
      await roleApiService.assignRoleNodes(roleId, permissionNodes);
    }

    message.success(formData.id ? '更新成功' : '创建成功');
    emit('success');
    emit('update:visible', false);
  } catch (error: any) {
    message.error(`保存失败: ${error?.message || '未知错误'}`);
  } finally {
    saving.value = false;
  }
};

const handleCancel = () => {
  emit('update:visible', false);
};
</script>

<style scoped>
.role-permission-label {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  color: v-bind('token.colorText');
  font-weight: 600;
  line-height: 1.5;
}

.role-permission-label__count {
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 999px;
  background: v-bind('token.colorFillQuaternary');
  font-size: 12px;
  font-weight: 400;
  line-height: 20px;
  padding: 0 8px;
  color: v-bind('token.colorTextSecondary');
}

.role-permission-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.role-permission-shell {
  overflow: hidden;
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 12px;
  background: v-bind('token.colorBgLayout');
}

.role-permission-toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.role-permission-body {
  padding: 6px 8px 8px;
}

.role-permission-collapse {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.role-permission-collapse :deep(.ant-collapse-item) {
  overflow: hidden;
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 10px;
  background: v-bind('token.colorBgContainer');
}

.role-permission-collapse :deep(.ant-collapse-header) {
  align-items: center;
  padding: 7px 8px !important;
}

.role-permission-collapse :deep(.ant-collapse-content-box) {
  padding: 0 8px 8px !important;
}

.role-permission-panel-heading {
  display: inline-flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.role-permission-panel-heading__name {
  color: v-bind('token.colorText');
  font-weight: 600;
}

.role-permission-panel-heading__count {
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 999px;
  background: v-bind('token.colorFillQuaternary');
  color: v-bind('token.colorTextSecondary');
  font-size: 12px;
  line-height: 18px;
  padding: 0 6px;
}

.role-permission-panel-actions :deep(.ant-btn-link) {
  padding-inline: 2px;
}

.role-permission-group {
  overflow: hidden;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 10px;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
}

.role-permission-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
}

.role-permission-table__row + .role-permission-table__row .role-permission-table__cell {
  border-top: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
}

.role-permission-table__head-cell {
  border-bottom: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  background: var(--ant-colorFillQuaternary, rgb(255 255 255 / 4%));
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  font-size: 11px;
  font-weight: 400;
  line-height: 16px;
  padding: 4px 8px;
  text-align: left;
}

.role-permission-table__head-cell + .role-permission-table__head-cell {
  border-left: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
}

.role-permission-table__head-cell--section {
  width: 132px;
}

.role-permission-table__head-cell--branch {
  width: 150px;
}

.role-permission-table__cell {
  vertical-align: middle;
  background: var(--ant-colorBgContainer, hsl(var(--background)));
  padding: 7px 8px;
}

.role-permission-table__cell + .role-permission-table__cell {
  border-left: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
}

.role-permission-table__cell--section {
  width: 132px;
  background: color-mix(in srgb, var(--ant-colorPrimary, #1677ff) 5%, transparent);
  font-weight: 600;
}

.role-permission-table__cell--branch {
  width: 150px;
  background: var(--ant-colorFillQuaternary, rgb(255 255 255 / 4%));
  font-weight: 500;
}

.role-permission-table__cell--leaves {
  background: color-mix(in srgb, var(--ant-colorInfo, #1677ff) 2%, transparent);
  min-width: 0;
}

.role-permission-table__title {
  font-weight: 600;
  white-space: nowrap;
}

.role-permission-leaf-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
  align-items: center;
  min-width: 0;
  gap: 5px 8px;
}

.role-permission-leaf-grid :deep(.ant-checkbox-wrapper),
.role-permission-table__cell--branch :deep(.ant-checkbox-wrapper),
.role-permission-table__cell--section :deep(.ant-checkbox-wrapper) {
  min-width: 0;
  margin-inline-start: 0;
  white-space: nowrap;
}

.role-permission-leaf-grid :deep(.ant-checkbox-wrapper) {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid var(--ant-colorBorderSecondary, hsl(var(--border)));
  border-radius: 8px;
  background: var(--ant-colorFillQuaternary, rgb(255 255 255 / 4%));
  line-height: 18px;
  padding: 4px 6px;
}

.role-permission-table__row--single .role-permission-table__cell--section {
  background: transparent;
}

.role-permission-table__empty {
  color: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  font-size: 12px;
}

</style>
