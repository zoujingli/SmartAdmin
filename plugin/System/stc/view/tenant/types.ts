export interface TenantType {
  id: number
  code: string
  name: string
  contact_name: string
  contact_phone: string
  contact_email: string
  package_code: string
  expired_at?: null | string
  status: number
  remark?: string
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export interface TenantFormData {
  id?: number
  code: string
  name: string
  contact_name: string
  contact_phone: string
  contact_email: string
  package_code: string
  expired_at?: string
  status: number
  remark?: string
  admin_username?: string
  admin_password?: string
  admin_nickname?: string
  admin_phone?: string
  admin_email?: string
}
