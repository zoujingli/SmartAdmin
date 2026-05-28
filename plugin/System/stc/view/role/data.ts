export function getRoleColumns() {
  return [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: '角色名称', dataIndex: 'name', key: 'name' },
    { title: '数据范围', dataIndex: 'scope', key: 'scope', width: 120 },
    { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
    { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
    { title: '权限菜单', dataIndex: 'menuNames', key: 'menuNames' },
    { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 160 },
    { title: '操作', key: 'action', width: 100, fixed: 'right' as const },
  ];
}

export function getRoleFormSchema() {
  return [
    {
      name: 'name',
      label: '角色名称',
      component: 'Input',
      rules: [
        { required: true, message: '请输入角色名称' },
        { min: 2, max: 20, message: '角色名称长度为 2-20 个字符' },
      ],
    },
    {
      name: 'sort',
      label: '排序',
      component: 'InputNumber',
      defaultValue: 0,
      componentProps: { min: 0, style: { width: '100%' } },
      rules: [{ required: true, message: '请输入排序' }],
    },
    {
      name: 'status',
      label: '状态',
      component: 'RadioGroup',
      defaultValue: 1,
      componentProps: {
        options: getStatusOptions(),
        optionType: 'button',
      },
      rules: [{ required: true, message: '请选择状态' }],
    },
    {
      name: 'remark',
      label: '备注',
      component: 'Textarea',
      componentProps: { rows: 3, maxLength: 200, showCount: true },
    },
  ];
}

export function getStatusOptions() {
  return [
    { label: '启用', value: 1 },
    { label: '禁用', value: 0 },
  ];
}
