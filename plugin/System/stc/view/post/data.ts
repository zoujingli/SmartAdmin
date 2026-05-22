export function getPostColumns() {
  return [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: '岗位编码', dataIndex: 'code', key: 'code', width: 160 },
    { title: '岗位名称', dataIndex: 'name', key: 'name', width: 200 },
    { title: '排序', dataIndex: 'sort', key: 'sort', width: 90 },
    { title: '状态', dataIndex: 'status', key: 'status', width: 90 },
    { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 160 },
    { title: '操作', key: 'action', width: 100, fixed: 'right' as const },
  ];
}

export function getPostFormSchema() {
  return [
    { name: 'code', label: '岗位编码', component: 'Input', rules: [{ required: true, message: '请输入岗位编码' }] },
    { name: 'name', label: '岗位名称', component: 'Input', rules: [{ required: true, message: '请输入岗位名称' }] },
    { name: 'sort', label: '排序权重', component: 'InputNumber', defaultValue: 0, componentProps: { min: 0, style: { width: '100%' } } },
    {
      name: 'status',
      label: '状态',
      component: 'RadioGroup',
      defaultValue: 1,
      componentProps: {
        options: [
          { label: '启用', value: 1 },
          { label: '禁用', value: 0 },
        ],
        optionType: 'button',
      },
    },
    { name: 'remark', label: '备注', component: 'Textarea', componentProps: { rows: 3, maxLength: 200, showCount: true } },
  ];
}
