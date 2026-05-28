export interface RoleType {
  id: number
  name: string
  scope?: number
  status: number
  sort: number
  remark?: string
  created_at?: string
  updated_at?: string
  menuIds?: number[]
  menuNames?: string[]
  allPermissions?: boolean
}

export interface RoleFormData {
  id?: number
  name: string
  scope: number
  status: number
  sort: number
  remark?: string
  menuIds: number[]
  allPermissions?: boolean
}

export interface RoleSearchForm {
  name?: string
  scope?: number
  status?: number
}
