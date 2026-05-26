<template>
  <Drawer
    :open="visible"
    :title="formData.id ? '编辑岗位' : '新增岗位'"
    :body-style="{ padding: '20px 24px 8px' }"
    :width="popupWidth.md"
    placement="right"
    @close="handleCancel"
  >
    <Form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
      <Row :gutter="[16, 0]">
        <Col :span="12">
          <FormItem label="岗位编码" name="code">
            <Input v-model:value="formData.code" placeholder="请输入岗位编码" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="岗位名称" name="name">
            <Input v-model:value="formData.name" placeholder="请输入岗位名称" />
          </FormItem>
        </Col>

        <Col :span="12">
          <FormItem label="排序权重" name="sort">
            <InputNumber v-model:value="formData.sort" :min="0" style="width: 100%" placeholder="请输入排序权重" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="状态" name="status">
            <RadioGroup v-model:value="formData.status" :options="statusOptions" option-type="button" />
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
import { Button, Col, Drawer, Form, FormItem, Input, InputNumber, message, RadioGroup, Row } from 'ant-design-vue';

import { postApiService } from '#/api/system/post';

import type { PostFormData, PostType } from '../types';
import { popupWidth } from '#/utils/popup';

interface Props {
  visible: boolean;
  data?: PostType;
}

interface Emits {
  (e: 'update:visible', visible: boolean): void;
  (e: 'success'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const formRef = ref();
const formData = reactive<PostFormData>({
  id: 0,
  code: '',
  name: '',
  sort: 0,
  status: 1,
  remark: '',
});

const statusOptions = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];

const formRules: any = {
  code: [
    { required: true, message: '请输入岗位编码', trigger: 'blur' },
    { min: 2, max: 50, message: '岗位编码长度为 2-50 个字符', trigger: 'blur' },
  ],
  name: [
    { required: true, message: '请输入岗位名称', trigger: 'blur' },
    { min: 2, max: 50, message: '岗位名称长度为 2-50 个字符', trigger: 'blur' },
  ],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
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
      code: '',
      name: '',
      sort: 0,
      status: 1,
      remark: '',
    });
  },
);

const handleOk = async () => {
  try {
    await formRef.value?.validate();

    const submitData: any = {
      code: formData.code,
      name: formData.name,
      sort: formData.sort,
      status: formData.status,
      remark: formData.remark,
    };

    if (formData.id) {
      submitData.id = formData.id;
      await postApiService.updatePost(formData.id, submitData);
      message.success('更新成功');
    } else {
      await postApiService.createPost(submitData);
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
