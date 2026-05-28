<script lang="ts" setup>
import type { UserType } from '../types';

import { computed, nextTick, reactive, ref, watch } from 'vue';

import { useAccess } from '@vben/access';
import { Button, Col, Form, FormItem, Input, InputPassword, message, RadioGroup, Row, Select, Textarea, TreeSelect } from 'ant-design-vue';

import { deptApiService, postApiService, roleApiService, tenantApiService, userApiService } from '#/api';
import AppDrawer from '#/components/app-drawer.vue';

const emit = defineEmits(['success']);

const formRef = ref();
const saving = ref(false);
const visible = ref(false);
const createInitialFormData = (): UserType => ({
  id: 0,
  tenant_id: 0,
  username: '',
  nickname: '',
  email: '',
  phone: '',
  deptId: null,
  roleIds: [],
  postIds: [],
  status: 1,
  remark: '',
  password: '',
});

const normalizeFormData = (data?: Partial<UserType>): UserType => ({
  ...createInitialFormData(),
  ...data,
  roleIds: (data?.roleIds || []).map((id: any) => Number(id)),
  postIds: (data?.postIds || []).map((id: any) => Number(id)),
  deptId: data?.deptId || null,
  password: data?.password || '',
});

const formData = reactive<UserType>(createInitialFormData());
const formSnapshot = ref<UserType>(createInitialFormData());

const deptTreeOptions = ref<any[]>([]);
const roleOptions = ref<any[]>([]);
const postOptions = ref<any[]>([]);
const tenantOptions = ref<any[]>([]);
const optionsLoaded = ref(false);
const relationOptionsTenantId = ref<number | null>(null);
const { hasAccessByCodes } = useAccess();
const canAccessTenantOptions = computed(() => hasAccessByCodes(['system.tenant.index']));
const canAccessDeptOptions = computed(() => hasAccessByCodes(['system.dept.index']));
const canAccessRoleOptions = computed(() => hasAccessByCodes(['system.role.index']));
const canAccessPostOptions = computed(() => hasAccessByCodes(['system.post.index']));
const getTitle = computed(() => (formData.id ? '编辑用户' : '新增用户'));

const resetFormData = (data?: Partial<UserType>) => {
  const nextData = normalizeFormData(data);

  // reactive 对象不能整体替换，重置前先移除上一次编辑记录带入的展示字段，避免跨记录残留。
  Object.keys(formData).forEach((key) => {
    if (!(key in nextData)) {
      delete (formData as any)[key];
    }
  });

  Object.assign(formData, nextData);
};

const syncFormSnapshot = () => {
  formSnapshot.value = normalizeFormData(formData);
};

const normalizeTenantId = (value: any) => Number(value || 0);

const getRelationOptionParams = (tenantId = normalizeTenantId(formData.tenant_id)) => (
  canAccessTenantOptions.value ? { tenant_id: tenantId } : {}
);

const loadTenantOptions = async () => {
  const tenants = canAccessTenantOptions.value ? await tenantApiService.getTenantOptions() : [];
  tenantOptions.value = [{ label: '平台空间', value: 0 }, ...(tenants || []).map((tenant: any) => ({ label: tenant.label || tenant.name, value: Number(tenant.id) }))];
};

const loadRelationOptions = async (tenantId = normalizeTenantId(formData.tenant_id)) => {
  const params = getRelationOptionParams(tenantId);
  const [deptTree, roles, posts] = await Promise.all([
    canAccessDeptOptions.value ? deptApiService.getDeptTree(params) : Promise.resolve([]),
    canAccessRoleOptions.value ? roleApiService.getRoleOptions(params) : Promise.resolve([]),
    canAccessPostOptions.value ? postApiService.getPostOptions(params) : Promise.resolve([]),
  ]);

  deptTreeOptions.value = Array.isArray(deptTree) ? deptTree : [];
  roleOptions.value = (roles || []).map((role: any) => ({ label: role.name, value: Number(role.id) }));
  postOptions.value = (posts || []).map((post: any) => ({ label: post.name, value: Number(post.id) }));
  relationOptionsTenantId.value = tenantId;
};

const loadOptions = async () => {
  await Promise.all([
    loadTenantOptions(),
    loadRelationOptions(),
  ]);
  optionsLoaded.value = true;
};

const buildSubmitData = (values: any) => {
  const payload: any = {
    username: values.username,
    nickname: values.nickname,
    email: values.email,
    phone: values.phone,
    status: values.status,
    remark: values.remark || '',
  };

  if (canAccessTenantOptions.value) {
    payload.tenant_id = Number(values.tenant_id || 0);
  }

  if (canAccessDeptOptions.value) {
    payload.dept_id = values.deptId || 0;
  }

  if (canAccessRoleOptions.value) {
    payload.role_ids = (values.roleIds || []).map((id: number | string) => Number(id));
  }

  if (canAccessPostOptions.value) {
    payload.post_ids = (values.postIds || []).map((id: number | string) => Number(id));
  }

  if (!formData.id && values.password) {
    payload.password = values.password;
  }

  if (formData.id) {
    payload.id = formData.id;
  }

  return payload;
};

const handleSubmit = async () => {
  try {
    await formRef.value?.validate();
    const values = formRef.value?.getFieldsValue();
    const submitData = buildSubmitData(values);

    if (formData.id) {
      await userApiService.updateUser(formData.id, submitData);
      message.success('更新成功');
    } else {
      await userApiService.createUser(submitData);
      message.success('创建成功');
    }

    visible.value = false;
    emit('success');
  } catch (error) {
    if ((error as any)?.errorFields) {
      return;
    }

    message.error(`保存失败: ${(error as any)?.message || '未知错误'}`);
  }
};

const handleCancel = () => {
  visible.value = false;
};

const handleReset = async () => {
  resetFormData(formSnapshot.value);
  await loadRelationOptions();
  await nextTick();
  formRef.value?.clearValidate?.();
};

const open = async (data?: UserType) => {
  // 抽屉复用同一个表单实例，先写入稳定的响应式模型，再清理上一次校验结果。
  if (data) {
    resetFormData({
      ...data,
      password: '',
    });
  } else {
    resetFormData();
  }

  syncFormSnapshot();

  if (!optionsLoaded.value) {
    await loadOptions();
  } else if (relationOptionsTenantId.value !== normalizeTenantId(formData.tenant_id)) {
    await loadRelationOptions();
  }

  visible.value = true;
  await nextTick();
  formRef.value?.clearValidate?.();
};

defineExpose({ open });

watch(
  () => formData.tenant_id,
  async (tenantId, oldTenantId) => {
    if (!visible.value || tenantId === oldTenantId || !canAccessTenantOptions.value) {
      return;
    }

    // 切换所属租户后，已选部门/角色/岗位不再可靠，必须重新加载目标租户可分配选项。
    formData.deptId = null;
    formData.roleIds = [];
    formData.postIds = [];
    await loadRelationOptions(normalizeTenantId(tenantId));
  },
);
</script>

<template>
  <AppDrawer
    :confirm-loading="saving"
    :open="visible"
    :title="getTitle" width-size="md"
    ok-text="确定"
    @close="handleCancel"
    @ok="handleSubmit"
  >
    <Form ref="formRef" :model="formData" layout="vertical">
      <Row :gutter="[16, 0]">
        <Col :span="12">
          <FormItem label="用户名" name="username" :rules="[{ required: true, message: '请输入用户名' }]">
            <Input v-model:value="formData.username" placeholder="请输入用户名" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="昵称" name="nickname" :rules="[{ required: true, message: '请输入昵称' }]">
            <Input v-model:value="formData.nickname" placeholder="请输入昵称" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem
            label="邮箱"
            name="email"
            :rules="[
              { required: true, message: '请输入邮箱' },
              { type: 'email', message: '请输入正确的邮箱格式' },
            ]"
          >
            <Input v-model:value="formData.email" placeholder="请输入邮箱" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="手机号" name="phone" :rules="[{ required: true, message: '请输入手机号' }]">
            <Input v-model:value="formData.phone" placeholder="请输入手机号" />
          </FormItem>
        </Col>

        <Col v-if="canAccessTenantOptions" :span="12">
          <FormItem label="所属租户" name="tenant_id">
            <Select v-model:value="formData.tenant_id" class="w-full" placeholder="请选择所属租户" :options="tenantOptions" />
          </FormItem>
        </Col>

        <Col v-if="canAccessDeptOptions" :span="12">
          <FormItem label="部门" name="deptId">
            <TreeSelect
              v-model:value="formData.deptId"
              :tree-data="deptTreeOptions"
              class="w-full"
              placeholder="请选择部门"
              allow-clear
              tree-default-expand-all
              :field-names="{ children: 'children', label: 'name', value: 'id' }"
              show-search
              tree-node-filter-prop="name"
            />
          </FormItem>
        </Col>

        <Col v-if="canAccessRoleOptions" :span="12">
          <FormItem label="角色" name="roleIds">
            <Select v-model:value="formData.roleIds" class="w-full" mode="multiple" placeholder="请选择角色" :options="roleOptions" />
          </FormItem>
        </Col>

        <Col v-if="canAccessPostOptions" :span="12">
          <FormItem label="岗位" name="postIds">
            <Select v-model:value="formData.postIds" class="w-full" mode="multiple" placeholder="请选择岗位" :options="postOptions" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="状态" name="status">
            <RadioGroup
              v-model:value="formData.status"
              :options="[
                { label: '启用', value: 1 },
                { label: '禁用', value: 0 },
              ]"
            />
          </FormItem>
        </Col>

        <Col v-if="!formData.id" :span="24">
          <FormItem
            label="密码"
            name="password"
            :rules="[{ required: true, message: '请输入密码' }]"
          >
            <InputPassword v-model:value="formData.password" placeholder="请输入密码" />
          </FormItem>
        </Col>

        <Col :span="24">
          <FormItem label="备注" name="remark">
            <Textarea v-model:value="formData.remark" placeholder="请输入备注" :rows="3" />
          </FormItem>
        </Col>
      </Row>
    </Form>
    <template #footer-left>
      <Button :disabled="saving" @click="handleReset">重置</Button>
    </template>
  </AppDrawer>
</template>
