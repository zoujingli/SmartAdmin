<template>
  <AppDrawer
    :confirm-loading="saving"
    :open="visible"
    :title="formData.id ? '编辑字典' : '新增字典'" width-size="md"
    ok-text="确定"
    @close="handleCancel"
    @ok="handleOk"
  >
    <Form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
      <Row :gutter="[16, 0]">
        <Col :span="24">
          <FormItem label="父级字典" name="pid">
            <TreeSelect
              v-model:value="formData.pid"
              :tree-data="parentOptions"
              :field-names="{ children: 'children', label: 'name', value: 'id' }"
              placeholder="请选择父级字典，留空则创建分类"
              allow-clear
              tree-default-expand-all
            />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="字典编码" name="code">
            <Input v-model:value="formData.code" placeholder="请输入字典编码" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="字典名称" name="name">
            <Input v-model:value="formData.name" placeholder="请输入字典名称" />
          </FormItem>
        </Col>
        <Col :span="12">
          <FormItem label="字典值" name="value">
            <Input v-model:value="formData.value" :disabled="formData.pid === 0" placeholder="分类可留空，字典项必填" />
          </FormItem>
        </Col>
        <Col :span="6">
          <FormItem label="排序权重" name="sort">
            <InputNumber v-model:value="formData.sort" :min="0" style="width: 100%" placeholder="排序" />
          </FormItem>
        </Col>
        <Col :span="6">
          <FormItem label="状态" name="status">
            <RadioGroup v-model:value="formData.status" :options="statusOptions" option-type="button" />
          </FormItem>
        </Col>
        <Col :span="24">
          <FormItem label="扩展配置" name="extra">
            <Input.TextArea v-model:value="formData.extra" :rows="4" placeholder='例如 {"color":"blue"}' />
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
import { reactive, ref, watch } from 'vue';
import { Col, Form, FormItem, Input, InputNumber, message, RadioGroup, Row, TreeSelect } from 'ant-design-vue';

import { dictApiService } from '#/api/system/dict';

import type { DictFormData, DictInfo } from '../types';
import AppDrawer from '#/components/app-drawer.vue';

interface Props {
  visible: boolean;
  data?: DictInfo;
  parentOptions: DictInfo[];
}

interface Emits {
  (e: 'update:visible', visible: boolean): void;
  (e: 'success'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const formRef = ref();
const saving = ref(false);
const formData = reactive<DictFormData>({
  id: 0,
  pid: 0,
  code: '',
  name: '',
  value: '',
  extra: '',
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
    { required: true, message: '请输入字典编码', trigger: 'blur' },
    { max: 100, message: '字典编码最多 100 个字符', trigger: 'blur' },
  ],
  name: [
    { required: true, message: '请输入字典名称', trigger: 'blur' },
    { max: 100, message: '字典名称最多 100 个字符', trigger: 'blur' },
  ],
  value: [
    {
      validator: (_rule: any, value: string) => {
        if (formData.pid === 0 || String(value || '').trim() !== '') {
          return Promise.resolve();
        }

        return Promise.reject(new Error('字典项必须填写字典值'));
      },
      trigger: 'blur',
    },
  ],
  extra: [
    {
      validator: (_rule: any, value: string) => {
        const raw = String(value || '').trim();
        if (raw === '') return Promise.resolve();
        try {
          JSON.parse(raw);
          return Promise.resolve();
        } catch {
          return Promise.reject(new Error('扩展配置必须是有效 JSON'));
        }
      },
      trigger: 'blur',
    },
  ],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
};

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;

    if (props.data) {
      Object.assign(formData, {
        ...props.data,
        extra: JSON.stringify(props.data.extra || {}, null, 2),
        pid: Number(props.data.pid || 0),
        value: props.data.value || '',
      });
      return;
    }

    Object.assign(formData, {
      id: 0,
      pid: 0,
      code: '',
      name: '',
      value: '',
      extra: '',
      sort: 0,
      status: 1,
      remark: '',
    });
  },
);

const handleOk = async () => {
  try {
    await formRef.value?.validate();

    const extraText = String(formData.extra || '').trim();
    const submitData = {
      code: formData.code.trim(),
      name: formData.name.trim(),
      pid: Number(formData.pid || 0),
      value: formData.pid === 0 ? '' : formData.value.trim(),
      extra: extraText === '' ? {} : JSON.parse(extraText),
      sort: Number(formData.sort || 0),
      status: Number(formData.status ?? 1),
      remark: formData.remark.trim(),
    };

    if (formData.id) {
      await dictApiService.updateDict(formData.id, submitData);
      message.success('更新成功');
    } else {
      await dictApiService.createDict(submitData);
      message.success('创建成功');
    }

    emit('success');
    emit('update:visible', false);
  } catch (error: any) {
    if (error && typeof error === 'object' && 'errorFields' in error) {
      return;
    }
    message.error(`保存失败: ${error?.message || '未知错误'}`);
  }
};

const handleCancel = () => {
  emit('update:visible', false);
};
</script>
