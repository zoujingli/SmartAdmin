export type ComponentType =
  | 'Input'
  | 'InputPassword'
  | 'InputNumber'
  | 'Textarea'
  | 'Select'
  | 'TreeSelect'
  | 'RadioGroup'
  | 'CheckboxGroup'
  | 'Checkbox'
  | 'Switch'
  | 'DatePicker'
  | 'RangePicker'
  | 'TimePicker'
  | 'Rate'
  | 'Upload'
  | 'ApiSelect'
  | 'ApiTreeSelect'
  | 'IconPicker'

export async function initComponentAdapter() {
  // 初始化组件适配器
  // 这里可以添加组件注册逻辑
  console.log('Component adapter initialized')
}
