<template>
  <WebsiteCrudPage
    title="官网区块"
    entity-name="区块"
    api="system/website/block"
    description="维护首页、栏目页等页面区块数据，前端可按 page_code 和 group_code 拉取后自由渲染。"
    intro-text="区块只保存结构化数据和媒体/链接引用，不重复实现上传系统。"
    search-placeholder="输入页面、分组、编码或标题"
    :columns="columns"
    :filter-fields="filters"
    :form-fields="fields"
    form-help="payload、media、link 必须填写 JSON 对象；公开接口只返回已发布且在有效时间内的区块。"
  />
</template>

<script setup lang="ts">
import WebsiteCrudPage from '../components/website-crud-page.vue';
import { blockTypeOptions, enabledStatusOptions, publishStatusOptions } from '../website-api';

const columns = [
  { title: '区块编码', dataIndex: 'code', valueType: 'copy', width: 160 },
  { title: '区块名称', dataIndex: 'name', width: 180 },
  { title: '所属站点', dataIndex: ['site', 'name'], width: 140 },
  { title: '页面编码', dataIndex: 'page_code', valueType: 'copy', width: 130 },
  { title: '分组编码', dataIndex: 'group_code', width: 130 },
  { title: '区块类型', dataIndex: 'type', width: 110 },
  { title: '区块标题', dataIndex: 'title', valueType: 'longText', width: 220 },
  { title: '发布状态', key: 'publish_status', dataIndex: 'publish_status', width: 120 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
];

const filters: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options' },
  { label: '页面编码', name: 'page_code', type: 'text' },
  { label: '分组编码', name: 'group_code', type: 'text' },
  { label: '区块类型', name: 'type', type: 'select', options: blockTypeOptions },
  { label: '发布状态', name: 'publish_status', type: 'select', options: publishStatusOptions },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions },
];

const fields: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options', required: true, emptyValue: 0 },
  { label: '页面编码', name: 'page_code', required: true, defaultValue: 'home', placeholder: '例如 home 或 safe' },
  { label: '分组编码', name: 'group_code', defaultValue: 'main', emptyValue: 'main', placeholder: '例如 hero、solution、main' },
  { label: '区块编码', name: 'code', required: true, placeholder: '例如 main-hero' },
  { label: '区块名称', name: 'name', required: true, placeholder: '例如 首页首屏' },
  { label: '区块类型', name: 'type', type: 'select', options: blockTypeOptions, defaultValue: 'custom', emptyValue: 'custom' },
  { label: '区块标题', name: 'title', span: 24, placeholder: '前端展示标题' },
  { label: '区块副标', name: 'subtitle', type: 'textarea', span: 24, maxlength: 500, placeholder: '前端展示副标题' },
  { label: '区块数据', name: 'payload', type: 'json', span: 24, jsonFallback: {} },
  { label: '媒体数据', name: 'media', type: 'json', span: 24, jsonFallback: {} },
  { label: '链接数据', name: 'link', type: 'json', span: 24, jsonFallback: {} },
  { label: '排序', name: 'sort', type: 'number', defaultValue: 0 },
  { label: '发布状态', name: 'publish_status', type: 'select', options: publishStatusOptions, defaultValue: 'draft', emptyValue: 'draft' },
  { label: '发布时间', name: 'published_at', type: 'datetime', emptyValue: null },
  { label: '下线时间', name: 'offline_at', type: 'datetime', emptyValue: null },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
