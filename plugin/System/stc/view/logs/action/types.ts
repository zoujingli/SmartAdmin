export interface LogsActionRow {
  id: number
  level?: string
  message?: string
  context?: string
  extra?: string
  created_at?: string
  updated_at?: string
  createdAt?: string
  updatedAt?: string
  username?: string
  method?: string
  router?: string
  name?: string
  service_name?: string
  remark?: string
  ip?: string
  ip_location?: string
  os?: string
  browser?: string
  request_data?: string
  response_code?: string
  response_data?: string
  created_by?: number
  updated_by?: number
  deleted_at?: null | string
}

export interface LogsActionChangeField {
  field: string
  label: string
  old?: unknown
  new?: unknown
  old_text?: string
  new_text?: string
  unit?: string
}

export interface LogsActionChangeRow {
  id: number
  tenant_id?: number
  action_id: number
  username?: string
  model?: string
  table_name?: string
  model_name?: string
  record_id?: number | string
  record_label?: string
  event?: string
  change_values?: LogsActionChangeField[] | string
  change_remark?: string
  created_by?: number
  updated_by?: number
  created_at?: string
  updated_at?: string
  deleted_at?: null | string
}

export interface LogsActionSearchForm {
  keyword?: string
  created_at?: [string, string]
  username?: string
  name?: string
  ip?: string
  router?: string
  response_code?: string
}

/** 近实时窗口指标（GET system/logs/action/metrics，日志主页展示） */
export interface LogsActionRealtimeMetrics {
  server_time: string
  last_activity_at: string | null
  count_last_1m: number
  count_last_5m: number
  count_last_15m: number
  errors_last_5m: number
  events_per_minute_5m: number
  by_response_code_last_5m: Record<string, number>
}

