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
    form-help="区块字段、媒体和链接都用普通表单维护；公开接口只返回已发布且在有效时间内的区块。"
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
  { title: '区块类型', dataIndex: 'type', valueType: 'option', options: blockTypeOptions, width: 110 },
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
  { label: '区块字段', name: 'payload', type: 'kv', span: 24, keyPlaceholder: '字段名，如 badge', valuePlaceholder: '展示内容，如 智慧厨房解决方案', help: '选填，给前端展示的额外字段逐项添加，不需要写 JSON。' },
  { label: '媒体资源', name: 'media', type: 'object', span: 24, children: [
    { label: '主图', name: 'image', type: 'image', buttonText: '上传/选择主图', placeholder: '请上传或选择主图' },
    { label: '视频', name: 'video', type: 'video', buttonText: '上传/选择视频', placeholder: '请上传或选择视频' },
    { label: '图标', name: 'icon', type: 'image', buttonText: '上传/选择图标', placeholder: '请上传或选择图标' },
  ] },
  { label: '跳转链接', name: 'link', type: 'object', span: 24, children: [
    { label: '按钮文字', name: 'text', placeholder: '例如 查看详情' },
    { label: '链接地址', name: 'url', placeholder: '例如 /safe/' },
    { label: '打开方式', name: 'target', type: 'select', options: [{ label: '当前窗口', value: 'self' }, { label: '新窗口', value: 'blank' }], defaultValue: 'self' },
  ] },
  { label: '排序', name: 'sort', type: 'number', defaultValue: 0 },
  { label: '发布状态', name: 'publish_status', type: 'select', options: publishStatusOptions, defaultValue: 'draft', emptyValue: 'draft' },
  { label: '发布时间', name: 'published_at', type: 'datetime', emptyValue: null },
  { label: '下线时间', name: 'offline_at', type: 'datetime', emptyValue: null },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
