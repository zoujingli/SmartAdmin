<template>
  <WebsiteCrudPage
    title="官网栏目"
    entity-name="栏目"
    api="system/website/channel"
    description="维护官网栏目树、栏目编码和公开访问路由，内容列表与页面接口都会按栏目归属过滤。"
    intro-text="栏目编码建议长期稳定；路由会统一标准化为 /xxx/ 形式。"
    search-placeholder="输入栏目编码、名称或路由"
    :columns="columns"
    :filter-fields="filters"
    :form-fields="fields"
    form-help="父级栏目必须属于同一站点；SEO 配置必须填写 JSON 对象。"
  />
</template>

<script setup lang="ts">
import WebsiteCrudPage from '../components/website-crud-page.vue';
import { channelTypeOptions, enabledStatusOptions } from '../website-api';

const columns = [
  { title: '栏目编码', dataIndex: 'code', valueType: 'copy', width: 150 },
  { title: '栏目名称', dataIndex: 'name', width: 180 },
  { title: '所属站点', dataIndex: ['site', 'name'], width: 140 },
  { title: '访问路由', dataIndex: 'route', valueType: 'route', width: 180 },
  { title: '栏目类型', dataIndex: 'type', width: 110 },
  { title: '父级ID', dataIndex: 'parent_id', width: 90 },
  { title: '排序', dataIndex: 'sort', width: 90 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
];

const filters: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options' },
  { label: '栏目类型', name: 'type', type: 'select', options: channelTypeOptions },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions },
];

const fields: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options', required: true, emptyValue: 0 },
  { label: '父级栏目', name: 'parent_id', type: 'select', optionApi: 'system/website/channel/options', defaultValue: 0, emptyValue: 0 },
  { label: '栏目编码', name: 'code', required: true, placeholder: '例如 safe' },
  { label: '栏目名称', name: 'name', required: true, placeholder: '例如 智慧食安' },
  { label: '访问路由', name: 'route', required: true, placeholder: '例如 /safe/' },
  { label: '栏目类型', name: 'type', type: 'select', options: channelTypeOptions, defaultValue: 'page', emptyValue: 'page' },
  { label: 'SEO配置', name: 'seo', type: 'json', span: 24, jsonFallback: { title: '' } },
  { label: '排序', name: 'sort', type: 'number', defaultValue: 0 },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
