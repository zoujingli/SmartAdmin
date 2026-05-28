<template>
  <WebsiteCrudPage
    title="官网站点"
    entity-name="站点"
    api="system/website/site"
    description="维护多个官网站点的域名、SEO、联系方式和启停状态，是接口应用绑定站点后的公开资料来源。"
    intro-text="公开接口只按接口应用绑定站点读取数据，站点禁用后不再返回官网资料。"
    search-placeholder="输入站点编码、名称或域名"
    :columns="columns"
    :filter-fields="filters"
    :form-fields="fields"
    form-help="只填写业务能理解的资料；租户归属由登录上下文自动处理，别名域名一行一个。"
  />
</template>

<script setup lang="ts">
import WebsiteCrudPage from '../components/website-crud-page.vue';
import { enabledStatusOptions } from '../website-api';

const columns = [
  { title: '站点编码', dataIndex: 'code', valueType: 'copy', width: 130 },
  { title: '站点名称', dataIndex: 'name', width: 180 },
  { title: '主域名', dataIndex: 'domain', valueType: 'copy', width: 180 },
  { title: '备用域名', dataIndex: 'aliases', valueType: 'tags', width: 220 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
  { title: '更新时间', dataIndex: 'updated_at', width: 180 },
];

const filters: any[] = [
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions },
];

const fields: any[] = [
  { label: '站点编码', name: 'code', required: true, placeholder: '例如 dmsai' },
  { label: '站点名称', name: 'name', required: true, placeholder: '例如 德玛仕智慧厨房' },
  { label: '主域名', name: 'domain', required: true, placeholder: '例如 dmsai.cn' },
  { label: '备用域名', name: 'aliases', type: 'tags', span: 24, placeholder: '一行一个，例如 www.dmsai.cn' },
  { label: 'Logo', name: 'logo', type: 'image', span: 12, buttonText: '上传/选择 Logo', placeholder: '请上传或选择 Logo' },
  { label: 'Favicon', name: 'favicon', type: 'image', span: 12, buttonText: '上传/选择图标', placeholder: '请上传或选择浏览器图标' },
  { label: 'SEO 信息', name: 'seo', type: 'object', span: 24, children: [
    { label: 'SEO标题', name: 'title', placeholder: '例如 德玛仕智慧厨房解决方案' },
    { label: '关键词', name: 'keywords', placeholder: '多个关键词用逗号分隔' },
    { label: '页面描述', name: 'description', type: 'textarea', span: 24, maxlength: 500, rows: 3, placeholder: '用于搜索引擎展示的站点简介' },
  ] },
  { label: '联系方式', name: 'contact', type: 'object', span: 24, children: [
    { label: '联系电话', name: 'phone', placeholder: '例如 400-xxx-xxxx' },
    { label: '联系邮箱', name: 'email', placeholder: '例如 service@example.com' },
    { label: '联系地址', name: 'address', type: 'textarea', span: 24, maxlength: 500, rows: 2, placeholder: '公司或展厅地址' },
  ] },
  { label: '扩展配置', name: 'config', type: 'kv', span: 24, keyPlaceholder: '配置项，如 icp', valuePlaceholder: '配置内容，如 粤ICP备xxxx号', help: '选填，需要额外给官网展示的配置可逐项添加，不需要写 JSON。' },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
