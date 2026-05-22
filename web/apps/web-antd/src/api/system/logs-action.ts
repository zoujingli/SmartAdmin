/**
 * 操作日志 API - 重构版本
 */
import { SystemApiService } from '../base';
import { createPageParams, formatDateParams } from '../utils';
import {
  asRecord,
  fillNumberRange,
  type LogChangeRow,
  type LogChangeValue,
  toFiniteNumber,
  toNumberRecord,
} from './logs-shared';

// 日志相关类型定义
export namespace LogsActionApi {
  /** 日志信息 */
  export interface LogsActionRow {
    id: number;
    level?: string;
    message?: string;
    context?: string;
    extra?: string;
    created_at: string;
    updated_at: string;
    username: string;
    method: string;
    router: string;
    name?: string;
    service_name: string;
    remark: string;
    ip: string;
    ip_location: string;
    os: string;
    browser: string;
    request_data: string;
    response_code: string;
    response_data: string;
    created_by: number;
    updated_by: number;
    deleted_at: string | null;
  }

  /** 日志列表参数 */
  export interface LogsActionListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    startDate?: string;
    endDate?: string;
    username?: string;
    name?: string;
    exclude_name?: string;
    ip?: string;
    router?: string;
    response_code?: string;
  }

  /** 日志搜索表单 */
  export interface LogsActionSearchForm {
    keyword?: string;
    username?: string;
    name?: string;
    router?: string;
    ip?: string;
    response_code?: string;
    startDate?: string;
    endDate?: string;
  }

  /** 变更日志字段明细 */
  export interface LogsActionChangeValue extends LogChangeValue {}

  /** 变更日志行 */
  export interface LogsActionChangeRow extends LogChangeRow {}

  /** 日志统计信息 */
  export interface LogsActionStatistics {
    total: number;
    today: number;
    success_count: number;
    warning_count: number;
    error_count: number;
    by_response_code: Record<string, number>;
    by_user: Record<string, number>;
    by_business: Record<string, number>;
    by_time: Record<string, number>;
  }

  /** 日志近实时窗口指标（与列表筛选一致） */
  export interface LogsActionRealtimeMetrics {
    server_time: string;
    last_activity_at: string | null;
    count_last_1m: number;
    count_last_5m: number;
    count_last_15m: number;
    errors_last_5m: number;
    events_per_minute_5m: number;
    by_response_code_last_5m: Record<string, number>;
  }

  /** 日志分析报告 */
  export interface LogsActionAnalysisReport {
    hourly_stats: Record<string, number>;
    weekly_stats: Record<string, number>;
    error_logs: LogsActionRow[];
  }

}

function normalizeActionLogStatistics(value: unknown): LogsActionApi.LogsActionStatistics {
  const record = asRecord(value);
  const byResponseCode = toNumberRecord(record.by_response_code);
  const byTime = toNumberRecord(record.by_time);
  const total = toFiniteNumber(record.total);
  const successCount = toFiniteNumber(record.success_count ?? byResponseCode['200']);
  const warningCount = toFiniteNumber(record.warning_count);
  const errorCount =
    record.error_count === undefined
      ? Math.max(0, total - successCount - warningCount)
      : toFiniteNumber(record.error_count);

  // 日志分析会被工作台、分析页和日志页复用，统一在 API 层收敛后端空值/字符串数字差异。
  return {
    total,
    today: toFiniteNumber(record.today ?? byTime.today),
    success_count: successCount,
    warning_count: warningCount,
    error_count: errorCount,
    by_response_code: byResponseCode,
    by_user: toNumberRecord(record.by_user),
    by_business: toNumberRecord(record.by_business),
    by_time: byTime,
  };
}

function normalizeRealtimeMetrics(value: unknown): LogsActionApi.LogsActionRealtimeMetrics {
  const record = asRecord(value);
  const lastActivityAt = record.last_activity_at;
  return {
    server_time: typeof record.server_time === 'string' ? record.server_time : '',
    last_activity_at: typeof lastActivityAt === 'string' && lastActivityAt !== '' ? lastActivityAt : null,
    count_last_1m: toFiniteNumber(record.count_last_1m),
    count_last_5m: toFiniteNumber(record.count_last_5m),
    count_last_15m: toFiniteNumber(record.count_last_15m),
    errors_last_5m: toFiniteNumber(record.errors_last_5m),
    events_per_minute_5m: toFiniteNumber(record.events_per_minute_5m),
    by_response_code_last_5m: toNumberRecord(record.by_response_code_last_5m),
  };
}

function normalizeAnalysisReport(value: unknown): LogsActionApi.LogsActionAnalysisReport {
  const record = asRecord(value);
  return {
    hourly_stats: fillNumberRange(record.hourly_stats, 0, 23),
    weekly_stats: fillNumberRange(record.weekly_stats, 0, 6),
    error_logs: Array.isArray(record.error_logs) ? (record.error_logs as LogsActionApi.LogsActionRow[]) : [],
  };
}

/**
 * 操作日志 API 服务
 */
class LogsActionApiService extends SystemApiService {
  constructor() {
    super();
  }

  /** 列表 / 近实时指标 / 分析共用的筛选 query（不含分页） */
  private buildActionLogQueryParams(params: LogsActionApi.LogsActionListParams = {}): Record<string, any> {
    const dateParams = formatDateParams(params.startDate, params.endDate);
    const queryParams: Record<string, any> = {};
    if (params.keyword) queryParams.keyword = params.keyword;
    if (params.username) queryParams.username = params.username;
    if (params.name) queryParams.name = params.name;
    if (params.exclude_name) queryParams.exclude_name = params.exclude_name;
    if (params.ip) queryParams.ip = params.ip;
    if (params.router) queryParams.router = params.router;
    if (params.response_code) queryParams.response_code = params.response_code;
    if (dateParams.startDate || dateParams.endDate) {
      queryParams.created_at = [dateParams.startDate || '', dateParams.endDate || ''];
    }
    return queryParams;
  }

  /**
   * 获取日志列表
   */
  async getActionLogList(params: LogsActionApi.LogsActionListParams = {}) {
    const pageParams = createPageParams(params.page, params.pageSize);
    const queryParams: any = {
      page: pageParams.page,
      pageSize: pageParams.pageSize,
      ...this.buildActionLogQueryParams(params),
    };

    return this.getList<LogsActionApi.LogsActionRow>('system/logs/action/index', pageParams.page, pageParams.pageSize, queryParams);
  }

  async getActionLogRecycleList(params: LogsActionApi.LogsActionListParams = {}) {
    const pageParams = createPageParams(params.page, params.pageSize);
    const queryParams: any = {
      page: pageParams.page,
      pageSize: pageParams.pageSize,
      ...this.buildActionLogQueryParams(params),
    };

    return this.getList<LogsActionApi.LogsActionRow>('system/logs/action/recycle', pageParams.page, pageParams.pageSize, queryParams);
  }

  /**
   * 获取日志详情
   */
  async getActionLogDetail(id: number) {
    return this.getDetail<LogsActionApi.LogsActionRow>('system/logs/action/info', id);
  }

  /**
   * 获取日志关联变更明细
   */
  async getActionLogChanges(id: number) {
    const data = await this.get<LogsActionApi.LogsActionChangeRow[]>(`system/logs/change/action/${id}`);
    return Array.isArray(data) ? data : [];
  }

  /**
   * 删除日志
   */
  async deleteActionLog(id: number) {
    return this.remove('system/logs/action/delete', id);
  }

  /**
   * 批量删除日志
   */
  async batchDeleteActionLogs(ids: number[]) {
    return this.remove('system/logs/action/delete', ids);
  }

  async recoveryActionLogs(ids: number[]) {
    return this.put(`system/logs/action/recovery/${ids.join(',')}`);
  }

  async realDeleteActionLogs(ids: number[]) {
    return this.delete(`system/logs/action/real-delete/${ids.join(',')}`);
  }

  /**
   * 清空日志
   */
  async clearActionLogs() {
    return this.post('system/logs/action/clear');
  }

  /**
   * 获取日志统计信息
   */
  async getActionLogStatistics(params: LogsActionApi.LogsActionListParams = {}) {
    const data = await this.get<LogsActionApi.LogsActionStatistics>(
      'system/logs/action/statistics',
      this.buildActionLogQueryParams(params),
    );
    return normalizeActionLogStatistics(data);
  }

  /**
   * 日志近实时窗口指标（与列表筛选条件一致）
   */
  async getActionLogRealtimeMetrics(params: LogsActionApi.LogsActionListParams = {}) {
    const data = await this.get<LogsActionApi.LogsActionRealtimeMetrics>(
      'system/logs/action/metrics',
      this.buildActionLogQueryParams(params),
    );
    return normalizeRealtimeMetrics(data);
  }

  /**
   * 获取日志分析报告
   */
  async getActionLogAnalysis(params: LogsActionApi.LogsActionListParams = {}) {
    const data = await this.get<LogsActionApi.LogsActionAnalysisReport>(
      'system/logs/action/analysis',
      this.buildActionLogQueryParams(params),
    );
    return normalizeAnalysisReport(data);
  }

}

// 导出单例实例
export const logsActionApiService = new LogsActionApiService();
