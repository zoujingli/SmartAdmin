<template>
  <WebsiteCrudPage
    title="官网内容"
    entity-name="内容"
    api="system/website/content"
    description="维护新闻、案例、产品、方案等通用内容，公开接口只返回已到发布时间且未下线的数据。"
    intro-text="发布状态固定为 draft / scheduled / published / offline；定时发布时间未到前不会在公开接口出现。"
    search-placeholder="输入标题、Slug、路由或摘要"
    publishable
    :columns="columns"
    :filter-fields="filters"
    :form-fields="fields"
    form-help="正文会在写入时清洗危险 HTML；payload、SEO 必须填写 JSON 对象，标签一行一个。"
  />
</template>

<script setup lang="ts">
import WebsiteCrudPage from '../components/website-crud-page.vue';
import { contentTypeOptions, enabledStatusOptions, publishStatusOptions } from '../website-api';

const columns = [
  { title: '内容标题', dataIndex: 'title', valueType: 'longText', width: 240 },
  { title: '所属站点', dataIndex: ['site', 'name'], width: 140 },
  { title: '所属栏目', dataIndex: ['channel', 'name'], width: 140 },
  { title: '内容类型', dataIndex: 'type', width: 110 },
  { title: 'Slug', dataIndex: 'slug', valueType: 'copy', width: 160 },
  { title: '访问路由', dataIndex: 'route', valueType: 'route', width: 180 },
  { title: '发布状态', key: 'publish_status', dataIndex: 'publish_status', width: 120 },
  { title: '发布时间', dataIndex: 'published_at', width: 180 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
];

const filters: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options' },
  { label: '所属栏目', name: 'channel_id', type: 'select', optionApi: 'system/website/channel/options' },
  { label: '内容类型', name: 'type', type: 'select', options: contentTypeOptions },
  { label: '发布状态', name: 'publish_status', type: 'select', options: publishStatusOptions },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions },
];

const fields: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options', required: true, emptyValue: 0 },
  { label: '所属栏目', name: 'channel_id', type: 'select', optionApi: 'system/website/channel/options', defaultValue: 0, emptyValue: 0 },
  { label: '内容类型', name: 'type', type: 'select', options: contentTypeOptions, defaultValue: 'article', emptyValue: 'article' },
  { label: '内容标题', name: 'title', required: true, placeholder: '例如 官网管理模块初始化' },
  { label: '访问标识', name: 'slug', placeholder: '例如 website-module' },
  { label: '访问路由', name: 'route', placeholder: '例如 /news/website-module/' },
  { label: '内容摘要', name: 'summary', type: 'textarea', span: 24, maxlength: 1000, placeholder: '列表摘要或 SEO 描述' },
  { label: '封面地址', name: 'cover', span: 24, placeholder: '图片 URL 或文件地址' },
  { label: '正文HTML', name: 'content_html', type: 'textarea', span: 24, rows: 8, maxlength: 50000, placeholder: '<p>正文内容</p>' },
  { label: '扩展数据', name: 'payload', type: 'json', span: 24, jsonFallback: {} },
  { label: '内容标签', name: 'tags', type: 'tags', span: 24, placeholder: '一行一个标签' },
  { label: 'SEO配置', name: 'seo', type: 'json', span: 24, jsonFallback: { title: '', keywords: '', description: '' } },
  { label: '排序', name: 'sort', type: 'number', defaultValue: 0 },
  { label: '是否置顶', name: 'is_top', type: 'select', options: [{ label: '否', value: 0 }, { label: '是', value: 1 }], defaultValue: 0, emptyValue: 0 },
  { label: '发布状态', name: 'publish_status', type: 'select', options: publishStatusOptions, defaultValue: 'draft', emptyValue: 'draft' },
  { label: '发布时间', name: 'published_at', type: 'datetime', emptyValue: null },
  { label: '下线时间', name: 'offline_at', type: 'datetime', emptyValue: null },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
