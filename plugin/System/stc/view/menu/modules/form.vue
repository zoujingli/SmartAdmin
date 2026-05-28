<template>
  <AppDrawer
    :confirm-loading="saving"
    :open="visible"
    :title="title" width-size="lg"
    ok-text="确定"
    @close="handleCancel"
    @ok="handleOk"
  >
    <div class="menu-form-overview" :style="overviewStyle">
      <div>
        <div class="menu-form-overview__title" :style="sectionTitleStyle">{{ title }}</div>
        <div class="menu-form-overview__desc" :style="sectionDescStyle">
          按菜单类型配置层级、路由、权限和展示方式，当前会直接影响左侧导航与页面访问。
        </div>
      </div>
      <Tag :color="typeMeta.color">{{ typeMeta.label }}</Tag>
    </div>

    <CrudNoticeAlert
      custom-class="mb-4"
      :message="typeMeta.message"
      :description="typeMeta.description"
    />

    <Form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
      <div class="menu-form-panel" :style="panelStyle">
        <div class="menu-form-section">
          <div class="menu-form-section__title" :style="sectionTitleStyle">基础信息</div>
          <div class="menu-form-section__desc" :style="sectionDescStyle">定义菜单层级、类型、名称、排序和启用状态。</div>
        </div>

        <Row :gutter="[16, 0]">
          <Col :span="12">
            <FormItem label="菜单类型" name="type">
              <RadioGroup v-model:value="formData.type" :options="menuTypeOptions" option-type="button" />
            </FormItem>
          </Col>
          <Col :span="12">
            <FormItem label="状态" name="status">
              <RadioGroup v-model:value="formData.status" :options="statusOptions" option-type="button" />
            </FormItem>
          </Col>
          <Col :span="16">
            <FormItem label="父级菜单" name="parentId">
              <TreeSelect
                v-model:value="formData.parentId"
                :tree-data="menuTreeOptions"
                placeholder="请选择父级菜单"
                allow-clear
                tree-default-expand-all
                :field-names="{ children: 'children', label: 'name', value: 'id' }"
              />
            </FormItem>
          </Col>
          <Col :span="8">
            <FormItem label="排序" name="sort">
              <InputNumber v-model:value="formData.sort" :min="0" style="width: 100%" placeholder="请输入排序" />
            </FormItem>
          </Col>
          <Col :span="24">
            <FormItem label="菜单名称" name="name">
              <Input v-model:value="formData.name" placeholder="请输入菜单名称" />
            </FormItem>
          </Col>
        </Row>
      </div>

      <div class="menu-form-panel" :style="panelStyle">
        <div class="menu-form-section">
          <div class="menu-form-section__title" :style="sectionTitleStyle">路由与权限</div>
          <div class="menu-form-section__desc" :style="sectionDescStyle">目录与菜单节点可配置路径、组件和默认跳转，按钮节点主要依赖权限标识。</div>
        </div>

        <Row :gutter="[16, 0]">
          <Col v-if="!isButtonType" :span="12">
            <FormItem label="菜单路径" name="path">
              <Input v-model:value="formData.path" placeholder="例如 /system/menu" />
            </FormItem>
          </Col>
          <Col v-if="isMenuType" :span="12">
            <FormItem label="页面组件" name="component">
              <Input
                v-model:value="formData.component"
                placeholder="例如 /system/menu/index"
              />
            </FormItem>
          </Col>
          <Col v-if="isDirectoryType" :span="12">
            <FormItem label="默认跳转" name="redirect">
              <Input v-model:value="formData.redirect" placeholder="例如 /system/user" />
            </FormItem>
          </Col>
          <Col :span="isButtonType ? 24 : 12">
            <FormItem label="权限标识" name="permission">
              <AutoComplete
                v-model:value="formData.permission"
                :filter-option="false"
                :options="nodeOptions"
                :placeholder="isButtonType ? '按钮类型必须填写权限标识' : '可选，用于前后端权限控制'"
                allow-clear
                @focus="() => loadNodeOptions(formData.permission)"
                @search="handleNodeSearch"
              />
            </FormItem>
          </Col>
        </Row>
      </div>

      <div class="menu-form-panel" :style="panelStyle">
        <div class="menu-form-section">
          <div class="menu-form-section__title" :style="sectionTitleStyle">视觉与说明</div>
          <div class="menu-form-section__desc" :style="sectionDescStyle">设置图标和备注，提升菜单结构的可读性与长期维护性。</div>
        </div>

        <Row :gutter="[16, 0]">
          <Col :span="24">
            <FormItem label="菜单图标" name="icon">
              <div class="w-full">
                <IconPicker v-model="formData.icon" placeholder="请选择或搜索菜单图标" prefix="lucide" />
                <div class="menu-form-icon-preview" :style="iconPreviewStyle">
                  <span class="flex size-9 shrink-0 items-center justify-center rounded-xl" :style="iconBoxStyle">
                    <IconifyIcon v-if="formData.icon" :icon="formData.icon" class="size-5 text-primary" />
                    <i v-else class="i-lucide-image size-5" />
                  </span>
                  <div class="min-w-0">
                    <div class="truncate font-medium" :style="sectionTitleStyle">
                      {{ formData.icon || '未选择图标' }}
                    </div>
                    <div class="text-xs" :style="sectionDescStyle">
                      建议目录与菜单节点配置图标，按钮类型通常可留空。
                    </div>
                  </div>
                </div>
                <div class="mt-3">
                  <Button v-if="formData.icon" size="small" @click="formData.icon = ''">清空图标</Button>
                </div>
              </div>
            </FormItem>
          </Col>
          <Col :span="24">
            <FormItem label="备注" name="remark">
              <Input.TextArea v-model:value="formData.remark" :rows="4" :maxlength="200" show-count placeholder="请输入备注" />
            </FormItem>
          </Col>
        </Row>
      </div>
    </Form>
  </AppDrawer>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { CrudNoticeAlert, IconPicker } from '@vben/common-ui';
import { IconifyIcon } from '@vben/icons';
import { AutoComplete, Button, Col, Form, FormItem, Input, InputNumber, message, RadioGroup, Row, Tag, TreeSelect, theme } from 'ant-design-vue';

import { menuApiService } from '#/api/system/menu';

import type { MenuApi } from '#/api/system/menu';

import type { MenuFormData, MenuType } from '../types';
import AppDrawer from '#/components/app-drawer.vue';

interface Props {
  visible: boolean;
  data?: MenuType;
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
const nodeOptions = ref<MenuApi.NodeOption[]>([]);
let nodeSearchTimer: ReturnType<typeof setTimeout> | undefined;
const formData = reactive<MenuFormData>({
  id: 0,
  parentId: 0,
  name: '',
  path: '',
  redirect: '',
  component: '',
  icon: '',
  type: 1,
  status: 1,
  sort: 0,
  permission: '',
  remark: '',
});

const menuTypeOptions = [
  { label: '目录', value: 1 },
  { label: '菜单', value: 2 },
  { label: '按钮', value: 3 },
];
const statusOptions = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];
const title = computed(() => (formData.id ? '编辑菜单' : '新增菜单'));
const isDirectoryType = computed(() => formData.type === 1);
const isMenuType = computed(() => formData.type === 2);
const isButtonType = computed(() => formData.type === 3);
const sectionTitleStyle = computed(() => ({ color: token.value.colorTextHeading }));
const sectionDescStyle = computed(() => ({ color: token.value.colorTextSecondary }));
const overviewStyle = computed(() => ({
  backgroundColor: token.value.colorFillAlter,
  border: `1px solid ${token.value.colorBorderSecondary}`,
}));
const panelStyle = computed(() => ({
  backgroundColor: token.value.colorBgContainer,
  border: `1px solid ${token.value.colorBorderSecondary}`,
  boxShadow: token.value.boxShadowTertiary,
}));
const iconPreviewStyle = computed(() => ({
  backgroundColor: token.value.colorFillAlter,
  border: `1px solid ${token.value.colorBorderSecondary}`,
}));
const iconBoxStyle = computed(() => ({
  backgroundColor: token.value.colorBgElevated,
  border: `1px solid ${token.value.colorBorderSecondary}`,
  color: token.value.colorTextSecondary,
}));
const typeMeta = computed(() => {
  if (isDirectoryType.value) {
    return {
      color: 'blue',
      label: '目录',
      message: '目录用于组织导航分组',
      description: '目录节点通常不直接承载业务页面，会自动使用 BasicLayout，并可配置默认跳转目标。',
    };
  }

  if (isMenuType.value) {
    return {
      color: 'green',
      label: '菜单',
      message: '菜单需要绑定实际页面组件',
      description: '请填写菜单路径和页面组件，组件路径建议与 views 目录保持一致，便于后续维护。',
    };
  }

  return {
    color: 'orange',
    label: '按钮',
    message: '按钮主要用于权限控制',
    description: '按钮节点不会出现在侧边导航里，通常只需要填写名称、父级菜单和权限标识。',
  };
});

const formRules: any = {
  name: [{ required: true, message: '请输入菜单名称', trigger: 'blur' }],
  path: [
    {
      validator: (_rule: any, value: string) => {
        if (isButtonType.value || String(value || '').trim() !== '') {
          return Promise.resolve();
        }

        return Promise.reject(new Error('请输入菜单路径'));
      },
      trigger: 'blur',
    },
  ],
  component: [
    {
      validator: (_rule: any, value: string) => {
        if (!isMenuType.value || String(value || '').trim() !== '') {
          return Promise.resolve();
        }

        return Promise.reject(new Error('菜单类型必须填写页面组件'));
      },
      trigger: 'blur',
    },
  ],
  permission: [
    {
      validator: (_rule: any, value: string) => {
        if (!isButtonType.value || String(value || '').trim() !== '') {
          return Promise.resolve();
        }

        return Promise.reject(new Error('按钮类型必须填写权限标识'));
      },
      trigger: 'blur',
    },
  ],
  type: [{ required: true, message: '请选择菜单类型', trigger: 'change' }],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
  sort: [{ required: true, message: '请输入排序', trigger: 'blur' }],
};

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;

    if (props.data) {
      resetForm();
      Object.assign(formData, {
        ...props.data,
        redirect: props.data.redirect || '',
        component: props.data.component || '',
        remark: props.data.remark || '',
        type: normalizeFormType(props.data.type),
      });
      loadNodeOptions(formData.permission);
      return;
    }

    resetForm();
    loadNodeOptions();
  },
);

onBeforeUnmount(() => {
  if (nodeSearchTimer) {
    clearTimeout(nodeSearchTimer);
  }
});

const resetForm = () => {
  Object.assign(formData, {
    id: 0,
    parentId: 0,
    name: '',
    path: '',
    redirect: '',
    component: '',
    icon: '',
    type: 1,
    status: 1,
    sort: 0,
    permission: '',
    remark: '',
  });
};

const normalizeFormType = (type: unknown) => {
  const normalized = String(type ?? '').trim().toUpperCase();
  const typeMap: Record<string, number> = {
    '1': 1,
    '2': 2,
    '3': 3,
    B: 3,
    BUTTON: 3,
    D: 1,
    DIRECTORY: 1,
    M: 2,
    MENU: 2,
    PATH: 1,
  };

  return typeMap[normalized] || 1;
};

const normalizeType = (type: number) => {
  const typeMap: Record<number, string> = {
    1: 'D',
    2: 'M',
    3: 'B',
  };

  return typeMap[type] || 'M';
};

const normalizeRoute = (route: string) => {
  const normalized = String(route || '').trim();
  if (normalized === '') {
    return '';
  }

  return normalized.startsWith('/') ? normalized : `/${normalized}`;
};

const normalizeComponent = (component: string) => {
  const normalized = String(component || '').trim();
  if (normalized === '' || normalized === 'BasicLayout') {
    return normalized;
  }

  return normalized.startsWith('/') ? normalized : `/${normalized}`;
};

const loadNodeOptions = async (keyword = '') => {
  try {
    nodeOptions.value = await menuApiService.getNodeOptions({
      keyword: String(keyword || '').trim(),
      limit: 50,
    });
  } catch {
    nodeOptions.value = [];
  }
};

const handleNodeSearch = (value: string) => {
  if (nodeSearchTimer) {
    clearTimeout(nodeSearchTimer);
  }

  nodeSearchTimer = setTimeout(() => loadNodeOptions(value), 250);
};

const handleOk = async () => {
  try {
    await formRef.value?.validate();

    const normalizedPath = isButtonType.value ? '' : normalizeRoute(formData.path);
    const normalizedRedirect = isDirectoryType.value ? normalizeRoute(formData.redirect || '') : '';
    const normalizedComponent = isDirectoryType.value
      ? 'BasicLayout'
      : isButtonType.value
        ? ''
        : normalizeComponent(formData.component || '');
    const submitData: any = {
      pid: formData.parentId || 0,
      name: formData.name.trim(),
      route: normalizedPath,
      redirect: normalizedRedirect,
      component: normalizedComponent,
      icon: String(formData.icon || '').trim(),
      type: normalizeType(formData.type),
      status: formData.status,
      sort: formData.sort,
      code: String(formData.permission || '').trim(),
      remark: String(formData.remark || '').trim(),
    };

    if (formData.id) {
      submitData.id = formData.id;
      await menuApiService.updateMenu(formData.id, submitData);
      message.success('更新成功');
    } else {
      await menuApiService.createMenu(submitData);
      message.success('创建成功');
    }

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
.menu-form-overview {
  margin-bottom: 16px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  border-radius: 16px;
  padding: 16px 18px;
}

.menu-form-overview__title {
  font-size: 15px;
  font-weight: 600;
  line-height: 24px;
}

.menu-form-overview__desc {
  margin-top: 4px;
  font-size: 12px;
  line-height: 20px;
}

.menu-form-panel {
  margin-bottom: 16px;
  border-radius: 16px;
  padding: 18px 18px 4px;
}

.menu-form-section {
  margin-bottom: 16px;
}

.menu-form-section__title {
  font-size: 14px;
  font-weight: 600;
  line-height: 22px;
}

.menu-form-section__desc {
  margin-top: 4px;
  font-size: 12px;
  line-height: 20px;
}

.menu-form-icon-preview {
  margin-top: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-radius: 14px;
  padding: 12px 14px;
}
</style>
