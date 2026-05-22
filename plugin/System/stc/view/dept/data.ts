export function getDeptColumns() {
  return [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80, treeNode: true },
    { title: '部门名称', dataIndex: 'name', key: 'name' },
    { title: '部门编码', dataIndex: 'code', key: 'code' },
    { title: '负责人', dataIndex: 'leader', key: 'leader', width: 120 },
    { title: '联系电话', dataIndex: 'phone', key: 'phone', width: 140 },
    { title: '邮箱', dataIndex: 'email', key: 'email', width: 180 },
    { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
    { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
    { title: '创建时间', dataIndex: 'createdAt', key: 'createdAt', width: 160 },
    { title: '操作', key: 'action', width: 100, fixed: 'right' as const },
  ];
}

export function getDeptFormSchema() {
  return [
    { name: 'name', label: '部门名称', component: 'Input', rules: [{ required: true, message: '请输入部门名称' }] },
    { name: 'code', label: '部门编码', component: 'Input', rules: [{ required: true, message: '请输入部门编码' }] },
    {
      name: 'parentId',
      label: '父级部门',
      component: 'TreeSelect',
      componentProps: { allowClear: true, treeDefaultExpandAll: true, fieldNames: { children: 'children', label: 'name', value: 'id' } },
    },
    { name: 'leader', label: '负责人', component: 'Input' },
    { name: 'phone', label: '联系电话', component: 'Input' },
    { name: 'email', label: '邮箱', component: 'Input' },
    { name: 'sort', label: '排序', component: 'InputNumber', defaultValue: 0, componentProps: { min: 0, style: { width: '100%' } } },
    {
      name: 'status',
      label: '状态',
      component: 'RadioGroup',
      defaultValue: 1,
      componentProps: { options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }], optionType: 'button' },
    },
    { name: 'remark', label: '备注', component: 'Textarea', componentProps: { rows: 3, maxLength: 200, showCount: true } },
  ];
}
