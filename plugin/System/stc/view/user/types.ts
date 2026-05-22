export interface UserType {
  id: number
  tenant_id?: number
  username: string
  nickname: string
  email?: string
  phone?: string
  deptId?: number | null
  roleIds?: (number | string)[]
  postIds?: number[]
  status: number
  remark?: string
  password?: string
  created_at?: string
  updated_at?: string
  createdAt?: string
  updatedAt?: string
  deptName?: string
  roleNames?: string[]
  postNames?: string[]
}

export interface UserFormData {
  id?: number
  tenant_id?: number
  username: string
  nickname: string
  email: string
  phone: string
  deptId?: number | null
  roleIds: number[]
  postIds: number[]
  status: number
  remark?: string
  password?: string
}

export interface UserSearchForm {
  username?: string
  nickname?: string
  email?: string
  phone?: string
  deptId?: number
  status?: number
}
