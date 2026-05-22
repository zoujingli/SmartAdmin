export interface MenuType {
  id: number
  parentId: number
  name: string
  path: string
  redirect?: string
  component?: string
  icon?: string
  type: number
  typeCode?: string
  status: number
  sort: number
  permission?: string
  remark?: string
  createdAt?: string
  updatedAt?: string
  deleted_at?: string
  children?: MenuType[]
  level?: number
  hasChildren?: boolean
}

export interface MenuFormData {
  id?: number
  parentId?: number
  name: string
  path: string
  redirect?: string
  component?: string
  icon?: string
  type: number
  status: number
  sort: number
  permission?: string
  remark?: string
}

export interface MenuSearchForm {
  name?: string
  type?: number
  status?: number
}
