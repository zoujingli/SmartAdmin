<template>
  <Drawer
    :open="visible"
    :title="title"
    :body-style="{ padding: '20px 24px 8px' }"
    :width="popupWidth.md"
    placement="right"
    @close="handleCancel"
  >
    <Form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
      <Row :gutter="[16, 0]">
        <Col :span="12">
          <FormItem label="角色名称" name="name">
            <Input v-model:value="formData.name" placeholder="请输入角色名称" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="角色编码" name="code">
            <Input v-model:value="formData.code" placeholder="请输入角色编码" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="排序" name="sort">
            <InputNumber v-model:value="formData.sort" :min="0" style="width: 100%" placeholder="请输入排序" />
          </FormItem>
        </Col>
        <Col :span="12">
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
            <template #label>
              <span class="role-permission-label">
                权限菜单
                <span class="role-permission-label__count">
                  （已选择 {{ selectedVisibleMenuIds.length }} / {{ allVisibleMenuIds.length }} 个权限节点）
                </span>
              </span>
            </template>
            <FormItemRest>
              <div class="role-permission-shell">
                <div class="role-permission-toolbar">
                  <div class="role-permission-toolbar__actions">
                    <Button size="small" @click="handleSelectAll">全选</Button>
                    <Button size="small" @click="handleClearAll">清空</Button>
                  </div>
                </div>

                <Collapse v-model:activeKey="activePermissionGroupKeys" ghost>
                  <CollapsePanel
                    v-for="group in permissionGroups"
                    :key="String(group.id)"
                    :header="`${group.name}（已选 ${getGroupSelectedCount(group)} / ${getGroupVisibleIds(group).length}）`"
                  >
                    <template #extra>
                      <Space size="small" @click.stop>
                        <Button size="small" type="link" @click.stop="handleGroupSelectAll(group)">全选本组</Button>
                        <Button size="small" type="link" @click.stop="handleGroupClear(group)">清空本组</Button>
                      </Space>
                    </template>
                    <div class="role-permission-group">
                      <template v-for="section in getNodeChildren(group)" :key="String(section.id)">
                        <div class="role-permission-section">
                          <div class="role-permission-section__header">
                            <Checkbox
                              :checked="isNodeChecked(section)"
                              :indeterminate="isNodeIndeterminate(section)"
                              @change="(event) => handleNodeCheck(section, event.target.checked)"
                            >
                              <span class="role-permission-section__title">{{ section.name }}</span>
                            </Checkbox>
                            <span class="role-permission-section__meta">
                              已选 {{ getNodeSelectedCount(section) }} / {{ getNodeVisibleIds(section).length }}
                            </span>
                          </div>

                          <div v-if="getNodeChildren(section).length > 0" class="role-permission-level3">
                            <template v-for="node in getNodeChildren(section)" :key="String(node.id)">
                              <div
                                v-if="getNodeChildren(node).length > 0"
                                class="role-permission-branch"
                              >
                                <div class="role-permission-branch__title">
                                  <Checkbox
                                    :checked="isNodeChecked(node)"
                                    :indeterminate="isNodeIndeterminate(node)"
                                    @change="(event) => handleNodeCheck(node, event.target.checked)"
                                  >
                                    {{ node.name }}
                                  </Checkbox>
                                </div>
                                <div class="role-permission-level4">
                                  <Checkbox
                                    v-for="leaf in getNodeChildren(node)"
                                    :key="String(leaf.id)"
                                    :checked="isNodeChecked(leaf)"
                                    @change="(event) => handleNodeCheck(leaf, event.target.checked)"
                                  >
                                    {{ leaf.name }}
                                  </Checkbox>
                                </div>
                              </div>

                              <label
                                v-else
                                class="role-permission-option"
                              >
                                <Checkbox
                                  :checked="isNodeChecked(node)"
                                  @change="(event) => handleNodeCheck(node, event.target.checked)"
                                >
                                  {{ node.name }}
                                </Checkbox>
                              </label>
                            </template>
                          </div>
                          <div v-else class="role-permission-empty">
                            当前分组下暂无可细分的权限节点。
                          </div>
                        </div>
                      </template>
                    </div>
                  </CollapsePanel>
                </Collapse>
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
    <template #footer>
      <div class="flex justify-end gap-3">
        <Button @click="handleCancel">取消</Button>
        <Button type="primary" @click="handleOk">确定</Button>
      </div>
    </template>
  </Drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Button, Checkbox, Col, Collapse, CollapsePanel, Drawer, Form, FormItem, FormItemRest, Input, InputNumber, message, RadioGroup, Row, Space, theme } from 'ant-design-vue';

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
const formData = reactive<RoleFormData>({
  id: 0,
  name: '',
  code: '',
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

const formRules: any = {
  name: [
    { required: true, message: '请输入角色名称', trigger: 'blur' },
    { min: 2, max: 20, message: '角色名称长度为 2-20 个字符', trigger: 'blur' },
  ],
  code: [
    { required: true, message: '请输入角色编码', trigger: 'blur' },
    { min: 2, max: 50, message: '角色编码长度为 2-50 个字符', trigger: 'blur' },
  ],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
  scope: [{ required: true, message: '请选择数据范围', trigger: 'change' }],
  sort: [{ required: true, message: '请输入排序', trigger: 'blur' }],
};

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;

    activePermissionGroupKeys.value = [...permissionGroupKeys.value];

    if (props.data) {
      Object.assign(formData, {
        ...props.data,
        code: props.data.code ?? '',
        scope: props.data.scope ?? ROLE_SCOPE_DEFAULT,
        menuIds: [...(props.data.menuIds ?? [])],
        allPermissions: Boolean(props.data.allPermissions),
      });
      return;
    }

    resetForm();
  },
);

watch(
  permissionGroupKeys,
  (keys) => {
    if (!props.visible) return;
    if (activePermissionGroupKeys.value.length === 0) {
      activePermissionGroupKeys.value = [...keys];
    }
  },
);

const resetForm = () => {
  Object.assign(formData, {
    id: 0,
    name: '',
    code: '',
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

const getNodeVisibleIds = (node: any): number[] => {
  return Array.from(new Set(getVisibleNodeIds(node)));
};

const getNodeSelectedCount = (node: any): number => {
  const visibleIds = new Set(getNodeVisibleIds(node));
  return (formData.menuIds ?? []).filter((id) => visibleIds.has(id)).length;
};

const getGroupVisibleIds = (group: any): number[] => {
  return Array.from(new Set(getVisibleNodeIds(group)));
};

const getGroupSelectedCount = (group: any): number => {
  const visibleIds = new Set(getGroupVisibleIds(group));
  return (formData.menuIds ?? []).filter((id) => visibleIds.has(id)).length;
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
  try {
    await formRef.value?.validate();

    const menuIds = Array.from(new Set(formData.menuIds ?? []));
    const keepWildcard =
      Boolean(formData.allPermissions)
      && allMenuIds.value.length > 0
      && allMenuIds.value.every((id) => menuIds.includes(id));

    const permissionNodes = keepWildcard ? ['*'] : mapMenuIdsToNodes(menuIds);
    const submitData: any = {
      name: formData.name,
      code: formData.code,
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
  gap: 4px;
}

.role-permission-label__count {
  font-size: 12px;
  font-weight: 400;
  color: v-bind('token.colorTextSecondary');
}

.role-permission-shell {
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 16px;
  background: v-bind('token.colorFillQuaternary');
  padding: 16px;
}

.role-permission-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-bottom: 12px;
}

.role-permission-toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.role-permission-group {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-top: 4px;
}

.role-permission-section {
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 14px;
  background: v-bind('token.colorFillTertiary');
  padding: 14px 16px;
}

.role-permission-section__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.role-permission-section__title {
  font-weight: 600;
}

.role-permission-section__meta {
  font-size: 12px;
  color: v-bind('token.colorTextSecondary');
}

.role-permission-level3 {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.role-permission-branch {
  min-width: 220px;
  flex: 1 1 260px;
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 12px;
  background: v-bind('token.colorBgContainer');
  padding: 12px;
}

.role-permission-branch__title {
  margin-bottom: 10px;
}

.role-permission-level4 {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
}

.role-permission-option {
  display: inline-flex;
  min-width: 160px;
  max-width: 100%;
  flex: 0 1 auto;
  align-items: center;
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 12px;
  background: v-bind('token.colorBgContainer');
  padding: 10px 12px;
}

.role-permission-empty {
  font-size: 12px;
  color: v-bind('token.colorTextTertiary');
}
</style>
