<template>
  <AppDrawer
    :confirm-loading="saving"
    :open="visible"
    :title="formData.id ? '编辑部门' : '新增部门'" width-size="md"
    ok-text="确定"
    @close="handleCancel"
    @ok="handleOk"
  >
    <Form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
      <Row :gutter="[16, 0]">
        <Col :span="12">
          <FormItem label="部门名称" name="name">
            <Input v-model:value="formData.name" placeholder="请输入部门名称" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="部门编码" name="code">
            <Input v-model:value="formData.code" placeholder="请输入部门编码" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="上级部门" name="parentId">
            <TreeSelect
              v-model:value="formData.parentId"
              :tree-data="deptTreeOptions"
              :field-names="{ label: 'name', value: 'id', children: 'children' }"
              placeholder="请选择上级部门"
              allow-clear
              tree-default-expand-all
            />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="负责人" name="leader">
            <Input v-model:value="formData.leader" placeholder="请输入负责人" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="联系电话" name="phone">
            <Input v-model:value="formData.phone" placeholder="请输入联系电话" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="邮箱" name="email">
            <Input v-model:value="formData.email" placeholder="请输入邮箱" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="排序" name="sort">
            <InputNumber v-model:value="formData.sort" :min="0" style="width: 100%" placeholder="请输入排序" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="状态" name="status">
            <RadioGroup v-model:value="formData.status">
              <Radio :value="1">启用</Radio>
              <Radio :value="0">禁用</Radio>
            </RadioGroup>
          </FormItem>
        </Col>

        <Col :span="24">
          <FormItem label="备注" name="remark">
            <Textarea v-model:value="formData.remark" :rows="3" placeholder="请输入备注" />
          </FormItem>
        </Col>
      </Row>
    </Form>
  </AppDrawer>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { Form, FormItem, Input, InputNumber, Col, message, Radio, RadioGroup, Row, Textarea, TreeSelect } from 'ant-design-vue';

import { deptApiService } from '#/api/system/dept';

import type { DeptType } from '../types';
import AppDrawer from '#/components/app-drawer.vue';

interface Props {
  visible: boolean;
  data?: DeptType;
  deptTreeOptions?: any[];
}

interface Emits {
  (e: 'update:visible', visible: boolean): void;
  (e: 'success'): void;
}

const props = withDefaults(defineProps<Props>(), {
  visible: false,
  data: undefined,
  deptTreeOptions: () => [],
});

const emit = defineEmits<Emits>();

const formRef = ref();
const saving = ref(false);
const formData = reactive<DeptType>({
  id: 0,
  name: '',
  code: '',
  parentId: 0,
  leader: '',
  phone: '',
  email: '',
  sort: 0,
  status: 1,
  remark: '',
  createdAt: '',
  updatedAt: '',
});

const formRules: any = {
  name: [{ required: true, message: '请输入部门名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入部门编码', trigger: 'blur' }],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
  sort: [{ required: true, message: '请输入排序', trigger: 'blur' }],
};

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;

    if (props.data) {
      Object.assign(formData, props.data);
      return;
    }

    Object.assign(formData, {
      id: 0,
      name: '',
      code: '',
      parentId: 0,
      leader: '',
      phone: '',
      email: '',
      sort: 0,
      status: 1,
      remark: '',
      createdAt: '',
      updatedAt: '',
    });
  },
);

const handleOk = async () => {
  try {
    await formRef.value?.validate();

    const submitData: any = {
      pid: formData.parentId || 0,
      name: formData.name,
      code: formData.code,
      leader: formData.leader,
      phone: formData.phone,
      email: formData.email,
      sort: formData.sort,
      status: formData.status,
      remark: formData.remark,
    };

    if (formData.id) {
      submitData.id = formData.id;
      await deptApiService.updateDept(formData.id, submitData);
      message.success('更新成功');
    } else {
      await deptApiService.createDept(submitData);
      message.success('创建成功');
    }

    emit('success');
    emit('update:visible', false);
  } catch (error: any) {
    console.error('保存部门失败:', error);
    message.error(`保存失败: ${error?.message || '未知错误'}`);
  }
};

const handleCancel = () => {
  emit('update:visible', false);
};
</script>
