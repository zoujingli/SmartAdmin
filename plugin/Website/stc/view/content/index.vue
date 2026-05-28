<template>
  <WebsiteCrudPage
    title="官网内容"
    entity-name="内容"
    api="system/website/content"
    description="维护新闻、案例、产品、方案等通用内容，公开接口只返回已到发布时间且未下线的数据。"
    intro-text="发布状态固定为 draft / scheduled / published / offline；定时发布时间未到前不会在公开接口出现。"
    search-placeholder="输入标题、访问标识、路由或摘要"
    publishable
    :columns="columns"
    :filter-fields="filters"
    :form-fields="fields"
    form-help="正文使用富文本编辑器；扩展字段逐项添加，SEO 信息按普通文本填写，标签一行一个。"
  />
</template>

<script setup lang="ts">
import WebsiteCrudPage from '../components/website-crud-page.vue';
import { contentTypeOptions, enabledStatusOptions, publishStatusOptions } from '../website-api';

const columns = [
  { title: '内容标题', dataIndex: 'title', valueType: 'longText', width: 240 },
  { title: '所属站点', dataIndex: ['site', 'name'], width: 140 },
  { title: '所属栏目', dataIndex: ['channel', 'name'], width: 140 },
  { title: '内容类型', dataIndex: 'type', valueType: 'option', options: contentTypeOptions, width: 110 },
  { title: '访问标识', dataIndex: 'slug', valueType: 'copy', width: 160 },
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
  { label: '所属栏目', name: 'channel_id', type: 'select', optionApi: 'system/website/channel/options', defaultValue: 0, emptyValue: 0, emptyLabel: '不归属栏目', filterBySite: true },
  { label: '内容类型', name: 'type', type: 'select', options: contentTypeOptions, defaultValue: 'article', emptyValue: 'article' },
  { label: '内容标题', name: 'title', required: true, placeholder: '例如 官网管理模块初始化' },
  { label: '访问标识', name: 'slug', placeholder: '例如 website-module' },
  { label: '访问路由', name: 'route', placeholder: '例如 /news/website-module/' },
  { label: '内容摘要', name: 'summary', type: 'textarea', span: 24, maxlength: 1000, placeholder: '列表摘要或 SEO 描述' },
  { label: '封面图片', name: 'cover', type: 'image', span: 24, buttonText: '上传/选择封面', placeholder: '请上传或选择封面图片' },
  { label: '正文内容', name: 'content_html', type: 'richtext', span: 24, placeholder: '请输入正文内容，可插入图片或视频。' },
  { label: '扩展字段', name: 'payload', type: 'kv', span: 24, keyPlaceholder: '字段名，如 author', valuePlaceholder: '字段内容，如 官网运营', help: '选填，用于产品参数、案例行业等额外展示字段，不需要写 JSON。' },
  { label: '内容标签', name: 'tags', type: 'tags', span: 24, placeholder: '一行一个标签' },
  { label: 'SEO 信息', name: 'seo', type: 'object', span: 24, children: [
    { label: 'SEO标题', name: 'title', placeholder: '默认可不填，使用内容标题' },
    { label: '关键词', name: 'keywords', placeholder: '多个关键词用逗号分隔' },
    { label: '页面描述', name: 'description', type: 'textarea', span: 24, maxlength: 500, rows: 3, placeholder: '内容详情页搜索描述' },
  ] },
  { label: '排序', name: 'sort', type: 'number', defaultValue: 0 },
  { label: '是否置顶', name: 'is_top', type: 'select', options: [{ label: '否', value: 0 }, { label: '是', value: 1 }], defaultValue: 0, emptyValue: 0 },
  { label: '发布状态', name: 'publish_status', type: 'select', options: publishStatusOptions, defaultValue: 'draft', emptyValue: 'draft' },
  { label: '发布时间', name: 'published_at', type: 'datetime', emptyValue: null },
  { label: '下线时间', name: 'offline_at', type: 'datetime', emptyValue: null },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
