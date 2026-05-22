export function getMenuTypeOptions() {
  return [
    { label: '目录', value: 1, color: 'processing' },
    { label: '菜单', value: 2, color: 'default' },
    { label: '按钮', value: 3, color: 'error' },
  ];
}

export function getMenuColumns() {
  return [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80, treeNode: true },
    { title: '菜单名称', dataIndex: 'name', key: 'name' },
    { title: '菜单路径', dataIndex: 'path', key: 'path' },
    { title: '图标', dataIndex: 'icon', key: 'icon', width: 120 },
    { title: '类型', dataIndex: 'type', key: 'type', width: 100 },
    { title: '权限标识', dataIndex: 'permission', key: 'permission', width: 180 },
    { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
    { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
    { title: '创建时间', dataIndex: 'createdAt', key: 'createdAt', width: 160 },
    { title: '操作', key: 'action', width: 100, fixed: 'right' as const },
  ];
}

export function getMenuFormSchema() {
  return [
    {
      name: 'type',
      label: '菜单类型',
      component: 'RadioGroup',
      defaultValue: 1,
      componentProps: { options: getMenuTypeOptions(), optionType: 'button' },
      rules: [{ required: true, message: '请选择菜单类型' }],
    },
    { name: 'name', label: '菜单名称', component: 'Input', rules: [{ required: true, message: '请输入菜单名称' }] },
    {
      name: 'parentId',
      label: '父级菜单',
      component: 'TreeSelect',
      componentProps: { allowClear: true, treeDefaultExpandAll: true, fieldNames: { children: 'children', label: 'name', value: 'id' } },
    },
    { name: 'path', label: '菜单路径', component: 'Input', rules: [{ required: true, message: '请输入菜单路径' }] },
    { name: 'icon', label: '菜单图标', component: 'Input' },
    { name: 'permission', label: '权限标识', component: 'Input' },
    { name: 'sort', label: '排序', component: 'InputNumber', defaultValue: 0, componentProps: { min: 0, style: { width: '100%' } } },
    {
      name: 'status',
      label: '状态',
      component: 'RadioGroup',
      defaultValue: 1,
      componentProps: { options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }], optionType: 'button' },
    },
  ];
}

export function getTypeColor(type: number) {
  const colors = { 1: 'blue', 2: 'green', 3: 'orange' } as Record<number, string>;
  return colors[type] || 'default';
}

export function getTypeText(type: number) {
  const texts = { 1: '目录', 2: '菜单', 3: '按钮' } as Record<number, string>;
  return texts[type] || '-';
}
