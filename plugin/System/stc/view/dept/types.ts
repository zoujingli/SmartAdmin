export interface DeptType {
  id: number
  parentId: number
  name: string
  code: string
  leader?: string
  phone?: string
  email?: string
  status: number
  sort: number
  remark?: string
  createdAt?: string
  updatedAt?: string
  deleted_at?: string
  children?: DeptType[]
  level?: number
  hasChildren?: boolean
}

export interface DeptFormData {
  id?: number
  parentId?: number
  name: string
  code: string
  leader?: string
  phone?: string
  email?: string
  status: number
  sort: number
  remark?: string
}

export interface DeptSearchForm {
  name?: string
  code?: string
  status?: number
}
