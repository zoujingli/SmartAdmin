<script lang="ts" setup>
import type { UserType } from '../types';

import { computed, ref } from 'vue';

import { Button, message } from 'ant-design-vue';

import { useVbenForm } from '#/adapter/form';
import { userApiService } from '#/api/system/user';

import { useFormSchema } from '../data';
import AppDrawer from '#/components/app-drawer.vue';

const emit = defineEmits(['success']);
const formData = ref<UserType>();
const visible = ref(false);
const saving = ref(false);

const [Form, formApi] = useVbenForm({
  layout: 'vertical',
  schema: useFormSchema(),
  showDefaultActions: false,
});

function resetForm() {
  formApi.resetForm();
  formApi.setValues(formData.value || {});
}

async function handleSubmit() {
  const { valid } = await formApi.validate();
  if (!valid) return;

  const data = await formApi.getValues();
  const submitData: any = {
    username: data.username,
    nickname: data.nickname,
    email: data.email,
    phone: data.phone,
    dept_id: data.deptId,
    role_ids: data.roleIds || [],
    post_ids: data.postIds || [],
    status: data.status,
    remark: data.remark,
  };

  if (!formData.value?.id && data.password) {
    submitData.password = data.password;
  }

  if (formData.value?.id) {
    submitData.id = formData.value.id;
  }

  await (formData.value?.id
    ? userApiService.updateUser(formData.value.id, submitData)
    : userApiService.createUser(submitData));

  message.success(formData.value?.id ? '更新成功' : '创建成功');
  visible.value = false;
  emit('success');
}

const getTitle = computed(() => {
  return formData.value?.id ? '编辑用户' : '新增用户';
});

const open = (data?: UserType) => {
  if (data) {
    formData.value = data;
    formApi.setValues({
      ...data,
      roleIds: data.roleIds || [],
      postIds: data.postIds || [],
      deptId: data.deptId || null,
    });
  } else {
    formData.value = undefined;
    formApi.resetForm();
  }
  visible.value = true;
};

defineExpose({
  open,
});
</script>

<template>
  <AppDrawer
    :confirm-loading="saving"
    :open="visible"
    :title="getTitle" width-size="md"
    ok-text="确定"
    @close="visible = false"
    @ok="handleSubmit"
  >
    <Form />
    <template #footer-left>
      <Button :disabled="saving" @click="resetForm">重置</Button>
    </template>
  </AppDrawer>
</template>
