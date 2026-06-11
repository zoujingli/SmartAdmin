/**
 * System 定时任务 API。
 */
import { SystemApiService } from '../base';
import { createPageParams, createSearchParams } from '../utils';

export namespace SchedulerApi {
  export interface TaskDefinition {
    code: string;
    name: string;
    description: string;
    group: string;
    owner_plugin: string;
    timeout: number;
    tenant_aware: number;
    handler: string;
  }

  export interface ScheduleTypeOption {
    label: string;
    value: string;
  }

  export interface ScheduledTask {
    id: number;
    tenant_id: number;
    owner_plugin: string;
    owner_type: string;
    owner_id: number;
    owner_name: string;
    code: string;
    name: string;
    group_name: string;
    schedule_type: string;
    schedule_config: Record<string, any>;
    params: Record<string, any>;
    timeout: number;
    next_run_at: string;
    last_run_at: string;
    last_status: string;
    last_message: string;
    running: number;
    locked_until: string | null;
    status: number;
    remark: string;
    created_at: string;
    updated_at: string;
  }

  export interface ScheduledTaskLog {
    id: number;
    tenant_id: number;
    task_id: number;
    owner_plugin: string;
    owner_type: string;
    owner_id: number;
    owner_name: string;
    task_code: string;
    task_name: string;
    trigger_type: string;
    status: string;
    message: string;
    result: Record<string, any>;
    started_at: string;
    finished_at: string;
    duration_ms: number;
    created_at: string;
    updated_at: string;
  }

  export interface TaskListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    status?: number;
    schedule_type?: string;
    last_status?: string;
    code?: string;
    owner_plugin?: string;
    owner_type?: string;
    owner_id?: number;
  }

  export interface LogListParams {
    page?: number;
    pageSize?: number;
    keyword?: string;
    task_id?: number;
    task_code?: string;
    status?: string;
    trigger_type?: string;
    owner_plugin?: string;
    owner_type?: string;
    owner_id?: number;
  }

  export interface TaskFormData {
    code: string;
    name: string;
    schedule_type: string;
    schedule_config: Record<string, any>;
    params?: Record<string, any>;
    timeout?: number;
    status?: number;
    remark?: string;
  }

  export interface TaskOptions {
    tasks: TaskDefinition[];
    schedule_types: ScheduleTypeOption[];
  }

  export interface RuntimeInfo {
    process: string;
    default_enabled: boolean;
    switch_env: null | string;
    registered_task_count: number;
    server_time: string;
  }
}

class SchedulerApiService extends SystemApiService {
  async getTaskList(params: SchedulerApi.TaskListParams = {}) {
    const pageParams = createPageParams(params.page, params.pageSize);
    return this.getList<SchedulerApi.ScheduledTask>('system/scheduler/task/index', pageParams.page, pageParams.pageSize, createSearchParams(params));
  }

  async getTaskDetail(id: number) {
    return this.getDetail<SchedulerApi.ScheduledTask>('system/scheduler/task/info', id);
  }

  async createTask(data: SchedulerApi.TaskFormData) {
    return this.create<SchedulerApi.ScheduledTask>('system/scheduler/task/create', data);
  }

  async updateTask(id: number, data: SchedulerApi.TaskFormData) {
    return this.update<SchedulerApi.ScheduledTask>('system/scheduler/task/update', id, data);
  }

  async updateTaskStatus(id: number, status: number) {
    return this.updateStatus('system/scheduler/task/status', id, status);
  }

  async runTask(id: number) {
    return this.post<Record<string, any>>(`system/scheduler/task/run/${id}`);
  }

  async deleteTask(id: number) {
    return this.remove('system/scheduler/task/delete', id);
  }

  async getTaskOptions() {
    return this.get<SchedulerApi.TaskOptions>('system/scheduler/task/options');
  }

  async getRuntimeInfo() {
    return this.get<SchedulerApi.RuntimeInfo>('system/scheduler/task/runtime');
  }

  async getLogList(params: SchedulerApi.LogListParams = {}) {
    const pageParams = createPageParams(params.page, params.pageSize);
    return this.getList<SchedulerApi.ScheduledTaskLog>('system/scheduler/log/index', pageParams.page, pageParams.pageSize, createSearchParams(params));
  }

  async getLogDetail(id: number) {
    return this.getDetail<SchedulerApi.ScheduledTaskLog>('system/scheduler/log/info', id);
  }
}

export const schedulerApiService = new SchedulerApiService();
