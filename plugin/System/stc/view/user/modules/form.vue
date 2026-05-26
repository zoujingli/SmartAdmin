<script lang="ts" setup>
import type { UserType } from '../types';

import { computed, ref } from 'vue';

import { Button, Drawer, message, Space } from 'ant-design-vue';

import { useVbenForm } from '#/adapter/form';
import { userApiService } from '#/api/system/user';

import { useFormSchema } from '../data';
import { popupWidth } from '#/utils/popup';

const emit = defineEmits(['success']);
const formData = ref<UserType>();
const visible = ref(false);

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
  <Drawer
    :open="visible"
    :title="getTitle"
    :body-style="{ padding: '20px 24px 8px' }"
    :width="popupWidth.md"
    placement="right"
    @close="visible = false"
  >
    <Form />
    <template #footer>
      <div class="flex w-full items-center justify-between gap-3">
        <Button @click="resetForm">
          重置
        </Button>
        <Space>
          <Button @click="visible = false">取消</Button>
          <Button type="primary" @click="handleSubmit">确定</Button>
        </Space>
      </div>
    </template>
  </Drawer>
</template>
