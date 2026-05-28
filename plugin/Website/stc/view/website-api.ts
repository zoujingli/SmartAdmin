import { requestClient } from '#/api/request';

export const publishStatusOptions = [
  { label: '草稿', value: 'draft', color: 'default' },
  { label: '定时发布', value: 'scheduled', color: 'processing' },
  { label: '已发布', value: 'published', color: 'success' },
  { label: '已下线', value: 'offline', color: 'default' },
];

export const leadStatusOptions = [
  { label: '待处理', value: 'pending', color: 'warning' },
  { label: '处理中', value: 'processing', color: 'processing' },
  { label: '已处理', value: 'handled', color: 'success' },
  { label: '无效线索', value: 'invalid', color: 'default' },
];

export const channelTypeOptions = [
  { label: '页面', value: 'page' },
  { label: '列表', value: 'list' },
  { label: '外链', value: 'link' },
];

export const navPositionOptions = [
  { label: '顶部导航', value: 'top' },
  { label: '底部导航', value: 'bottom' },
  { label: '侧边导航', value: 'side' },
];

export const navLinkTypeOptions = [
  { label: '站内路由', value: 'route' },
  { label: '外部链接', value: 'url' },
  { label: '关联栏目', value: 'channel' },
  { label: '关联内容', value: 'content' },
];

export const navTargetOptions = [
  { label: '当前窗口', value: 'self' },
  { label: '新窗口', value: 'blank' },
];

export const contentTypeOptions = [
  { label: '文章', value: 'article' },
  { label: '新闻', value: 'news' },
  { label: '案例', value: 'case' },
  { label: '产品', value: 'product' },
  { label: '方案', value: 'solution' },
];

export const blockTypeOptions = [
  { label: '首屏', value: 'hero' },
  { label: '分区', value: 'section' },
  { label: '轮播', value: 'banner' },
  { label: '自定义', value: 'custom' },
];

export const enabledStatusOptions = [
  { label: '启用', value: 1, color: 'success' },
  { label: '禁用', value: 0, color: 'default' },
];

export function optionText(options: any[], value: any) {
  return options.find((item) => String(item.value) === String(value))?.label || String(value ?? '');
}

export function optionColor(options: any[], value: any) {
  return options.find((item) => String(item.value) === String(value))?.color || 'default';
}

export function statusText(status: number) {
  return Number(status) === 1 ? '启用' : '禁用';
}

export function statusColor(status: number) {
  return Number(status) === 1 ? 'success' : 'default';
}

export function pageParams(pagination: { current: number; pageSize: number }, params: Record<string, any> = {}) {
  return { ...params, page: pagination.current, pageSize: pagination.pageSize };
}

export function stringifyJson(value: unknown, fallback = '{}') {
  if (value === null || value === undefined || value === '') {
    return fallback;
  }
  if (typeof value === 'string') {
    try {
      return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
      return value;
    }
  }

  return JSON.stringify(value, null, 2);
}

export function parseJsonField(value: unknown, fallback: any = {}) {
  if (value === null || value === undefined || value === '') {
    return fallback;
  }
  if (typeof value !== 'string') {
    return value;
  }

  return JSON.parse(value);
}

export function splitStringList(value: unknown) {
  if (Array.isArray(value)) {
    return value.map((item) => String(item || '').trim()).filter(Boolean);
  }

  return String(value || '')
    .split(/[，,\n]+/u)
    .map((item) => item.trim())
    .filter(Boolean);
}

export async function loadSiteOptions(keyword = '') {
  return await requestClient.get<any[]>('system/website/site/options', { params: { keyword } });
}
