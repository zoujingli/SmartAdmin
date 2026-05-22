<template>
  <Drawer
    :open="visible"
    :title="formData.id ? '编辑租户' : '新增租户'"
    :body-style="{ padding: '20px 24px 8px' }"
    width="min(760px, calc(100vw - 32px))"
    placement="right"
    @close="handleCancel"
  >
    <Form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
      <Row :gutter="[16, 0]">
        <Col :span="12">
          <FormItem label="租户编码" name="code">
            <Input v-model:value="formData.code" placeholder="请输入租户编码" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="租户名称" name="name">
            <Input v-model:value="formData.name" placeholder="请输入租户名称" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="联系人" name="contact_name">
            <Input v-model:value="formData.contact_name" placeholder="请输入联系人" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="联系电话" name="contact_phone">
            <Input v-model:value="formData.contact_phone" placeholder="请输入联系电话" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="联系邮箱" name="contact_email">
            <Input v-model:value="formData.contact_email" placeholder="请输入联系邮箱" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="套餐编码" name="package_code">
            <Input v-model:value="formData.package_code" placeholder="请输入套餐编码" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="到期时间" name="expired_at">
            <Input v-model:value="formData.expired_at" placeholder="请输入到期时间，例如 2026-12-31 23:59:59" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="状态" name="status">
            <RadioGroup v-model:value="formData.status" :options="statusOptions" option-type="button" />
          </FormItem>
        </Col>

        <Col v-if="!formData.id" :span="24">
          <div class="mb-3 mt-1 text-sm font-medium text-gray-400">租户管理员</div>
        </Col>
        <Col v-if="!formData.id" :span="12">
          <FormItem label="管理员用户名" name="admin_username">
            <Input v-model:value="formData.admin_username" placeholder="请输入管理员用户名" />
          </FormItem>
        </Col>
        <Col v-if="!formData.id" :span="12">
          <FormItem label="管理员初始密码" name="admin_password">
            <Input.Password v-model:value="formData.admin_password" placeholder="请输入管理员初始密码" />
          </FormItem>
        </Col>
        <Col v-if="!formData.id" :span="12">
          <FormItem label="管理员昵称" name="admin_nickname">
            <Input v-model:value="formData.admin_nickname" placeholder="请输入管理员昵称" />
          </FormItem>
        </Col>
        <Col v-if="!formData.id" :span="12">
          <FormItem label="管理员邮箱" name="admin_email">
            <Input v-model:value="formData.admin_email" placeholder="请输入管理员邮箱" />
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
import { reactive, ref, watch } from 'vue';
import { Button, Col, Drawer, Form, FormItem, Input, message, RadioGroup, Row } from 'ant-design-vue';

import { tenantApiService } from '#/api/system/tenant';

import type { TenantFormData, TenantType } from '../types';

interface Props {
  visible: boolean;
  data?: TenantType;
}

interface Emits {
  (e: 'update:visible', visible: boolean): void;
  (e: 'success'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const formRef = ref();
const formData = reactive<TenantFormData>({
  id: 0,
  code: '',
  name: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  package_code: 'basic',
  expired_at: '',
  status: 1,
  remark: '',
  admin_username: '',
  admin_password: '',
  admin_nickname: '',
  admin_phone: '',
  admin_email: '',
});

const statusOptions = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];

const formRules: any = {
  code: [
    { required: true, message: '请输入租户编码', trigger: 'blur' },
    { min: 2, max: 50, message: '租户编码长度为 2-50 个字符', trigger: 'blur' },
  ],
  name: [
    { required: true, message: '请输入租户名称', trigger: 'blur' },
    { min: 2, max: 100, message: '租户名称长度为 2-100 个字符', trigger: 'blur' },
  ],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
  admin_username: [
    { required: true, message: '请输入管理员用户名', trigger: 'blur' },
    { min: 3, max: 20, message: '管理员用户名长度为 3-20 个字符', trigger: 'blur' },
  ],
  admin_password: [
    { required: true, message: '请输入管理员初始密码', trigger: 'blur' },
    { min: 6, max: 20, message: '管理员初始密码长度为 6-20 个字符', trigger: 'blur' },
  ],
};

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;

    if (props.data) {
      Object.assign(formData, {
        ...props.data,
        expired_at: props.data.expired_at || '',
      });
      return;
    }

    Object.assign(formData, {
      id: 0,
      code: '',
      name: '',
      contact_name: '',
      contact_phone: '',
      contact_email: '',
      package_code: 'basic',
      expired_at: '',
      status: 1,
      remark: '',
      admin_username: '',
      admin_password: '',
      admin_nickname: '',
      admin_phone: '',
      admin_email: '',
    });
  },
);

const handleOk = async () => {
  try {
    await formRef.value?.validate();

    const submitData: any = {
      code: formData.code,
      name: formData.name,
      contact_name: formData.contact_name,
      contact_phone: formData.contact_phone,
      contact_email: formData.contact_email,
      package_code: formData.package_code,
      expired_at: formData.expired_at || null,
      status: formData.status,
      remark: formData.remark,
    };

    if (formData.id) {
      await tenantApiService.updateTenant(formData.id, submitData);
      message.success('更新成功');
    } else {
      submitData.admin_username = formData.admin_username;
      submitData.admin_password = formData.admin_password;
      submitData.admin_nickname = formData.admin_nickname || '租户管理员';
      submitData.admin_phone = formData.admin_phone || formData.contact_phone;
      submitData.admin_email = formData.admin_email || formData.contact_email;
      await tenantApiService.createTenant(submitData);
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
