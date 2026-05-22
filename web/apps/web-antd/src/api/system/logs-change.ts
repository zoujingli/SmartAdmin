/**
 * 变更日志 API
 */
import type { LogsActionApi } from './logs-action';

import { SystemApiService } from '../base';
import { createPageParams, formatDateParams } from '../utils';
import { asRecord, type LogChangeRow, type LogChangeValue, toFiniteNumber, toNumberRecord } from './logs-shared';

export namespace LogsChangeApi {
  /** 变更字段明细 */
  export interface LogsChangeValue extends LogChangeValue {}

  /** 变更日志行 */
  export interface LogsChangeRow extends LogChangeRow {
    action?: LogsActionApi.LogsActionRow | null;
  }

  /** 变更日志列表参数 */
  export interface LogsChangeListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    action_id?: number | string;
    username?: string;
    model?: string;
    table_name?: string;
    model_name?: string;
    record_id?: number | string;
    record_label?: string;
    event?: string;
    startDate?: string;
    endDate?: string;
  }

  /** 变更日志统计信息 */
  export interface LogsChangeStatistics {
    total: number;
    today: number;
    by_event: Record<string, number>;
    by_model: Record<string, number>;
    by_table: Record<string, number>;
  }
}

function normalizeStatistics(value: unknown): LogsChangeApi.LogsChangeStatistics {
  const record = asRecord(value);
  return {
    total: toFiniteNumber(record.total),
    today: toFiniteNumber(record.today),
    by_event: toNumberRecord(record.by_event),
    by_model: toNumberRecord(record.by_model),
    by_table: toNumberRecord(record.by_table),
  };
}

class LogsChangeApiService extends SystemApiService {
  constructor() {
    super();
  }

  /** 列表和统计共用筛选 query（不含分页） */
  private buildChangeLogQueryParams(params: LogsChangeApi.LogsChangeListParams = {}): Record<string, any> {
    const dateParams = formatDateParams(params.startDate, params.endDate);
    const queryParams: Record<string, any> = {};
    if (params.keyword) queryParams.keyword = params.keyword;
    if (params.action_id) queryParams.action_id = params.action_id;
    if (params.username) queryParams.username = params.username;
    if (params.model) queryParams.model = params.model;
    if (params.table_name) queryParams.table_name = params.table_name;
    if (params.model_name) queryParams.model_name = params.model_name;
    if (params.record_id) queryParams.record_id = params.record_id;
    if (params.record_label) queryParams.record_label = params.record_label;
    if (params.event) queryParams.event = params.event;
    if (dateParams.startDate || dateParams.endDate) {
      queryParams.created_at = [dateParams.startDate || '', dateParams.endDate || ''];
    }
    return queryParams;
  }

  /** 获取变更日志列表 */
  async getChangeLogList(params: LogsChangeApi.LogsChangeListParams = {}) {
    const pageParams = createPageParams(params.page, params.pageSize);
    const queryParams: any = {
      page: pageParams.page,
      pageSize: pageParams.pageSize,
      ...this.buildChangeLogQueryParams(params),
    };

    return this.getList<LogsChangeApi.LogsChangeRow>(
      'system/logs/change/index',
      pageParams.page,
      pageParams.pageSize,
      queryParams,
    );
  }

  /** 获取变更日志详情 */
  async getChangeLogDetail(id: number) {
    return this.getDetail<LogsChangeApi.LogsChangeRow>('system/logs/change/info', id);
  }

  /** 获取变更日志统计 */
  async getChangeLogStatistics(params: LogsChangeApi.LogsChangeListParams = {}) {
    const data = await this.get<LogsChangeApi.LogsChangeStatistics>(
      'system/logs/change/statistics',
      this.buildChangeLogQueryParams(params),
    );
    return normalizeStatistics(data);
  }
}

export const logsChangeApiService = new LogsChangeApiService();
