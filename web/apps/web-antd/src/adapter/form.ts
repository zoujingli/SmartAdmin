import type { VbenFormSchema as FormSchema, VbenFormProps } from '@vben/common-ui'
import type { ComponentType } from './component'

import { setupVbenForm, useVbenForm as useForm, z } from '@vben/common-ui'

async function initSetupVbenForm() {
  setupVbenForm<ComponentType>({
    config: {
      // ant design vue组件库默认都是 v-model:value
      baseModelPropName: 'value',
      // 一些组件是 v-model:checked 或者 v-model:fileList
      modelPropNameMap: {
        Checkbox: 'checked',
        Switch: 'checked',
        Upload: 'fileList',
        TreeSelect: 'value',
        Select: 'value',
        RadioGroup: 'value',
        CheckboxGroup: 'value',
      },
    },
    defineRules: {
      // 输入项目必填验证
      required: (value, _params, ctx) => {
        if (value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
          return `${ctx.label}是必填项`
        }
        return true
      },
      // 选择项目必填验证
      selectRequired: (value, _params, ctx) => {
        if (value === undefined || value === null) {
          return `请选择${ctx.label}`
        }
        return true
      },
    },
  })
}

const useVbenForm = useForm<ComponentType>

export { initSetupVbenForm, useVbenForm, z }
export type VbenFormSchema = FormSchema<ComponentType>
export type { VbenFormProps }
