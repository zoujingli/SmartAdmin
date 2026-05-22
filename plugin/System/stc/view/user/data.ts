import type { VbenFormSchema } from '#/adapter/form';

import { z } from '#/adapter/form';

export function useFormSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'username',
      label: '用户名',
      rules: z.string().min(3, '用户名至少 3 位').max(20, '用户名最多 20 位'),
    },
    {
      component: 'Input',
      fieldName: 'nickname',
      label: '昵称',
      rules: z.string().min(2, '昵称至少 2 位').max(20, '昵称最多 20 位'),
    },
    {
      component: 'Input',
      fieldName: 'email',
      label: '邮箱',
      rules: z.string().email('邮箱格式不正确'),
    },
    {
      component: 'Input',
      fieldName: 'phone',
      label: '手机号',
      rules: z.string().regex(/^1[3-9]\d{9}$/, '手机号格式不正确'),
    },
    {
      component: 'RadioGroup',
      fieldName: 'status',
      label: '状态',
      defaultValue: 1,
      componentProps: {
        buttonStyle: 'solid',
        optionType: 'button',
        options: getStatusOptions(),
      },
    },
    {
      component: 'InputPassword',
      fieldName: 'password',
      label: '密码',
      componentProps: {
        placeholder: '请输入密码',
        maxLength: 20,
      },
      rules: z.string().min(6, '密码至少 6 位').max(20, '密码最多 20 位').optional(),
    },
    {
      component: 'Textarea',
      fieldName: 'remark',
      label: '备注',
      componentProps: {
        rows: 3,
        maxLength: 200,
        showCount: true,
      },
      rules: z.string().max(200, '备注最多 200 位').optional(),
    },
  ];
}

export function useSearchSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'username',
      label: '用户名',
      componentProps: { placeholder: '请输入用户名' },
    },
    {
      component: 'Select',
      fieldName: 'status',
      label: '状态',
      componentProps: {
        allowClear: true,
        placeholder: '请选择状态',
        options: getStatusOptions(),
      },
    },
  ];
}

export function useColumns() {
  return [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: '用户名', dataIndex: 'username', key: 'username' },
    { title: '昵称', dataIndex: 'nickname', key: 'nickname' },
    { title: '邮箱', dataIndex: 'email', key: 'email' },
    { title: '手机号', dataIndex: 'phone', key: 'phone' },
    { title: '部门', dataIndex: 'deptName', key: 'deptName' },
    { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
    { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 160 },
    { title: '操作', key: 'action', width: 100, fixed: 'right' as const },
  ];
}

export function getStatusOptions() {
  return [
    { label: '启用', value: 1 },
    { label: '禁用', value: 0 },
  ];
}
