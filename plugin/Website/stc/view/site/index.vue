<template>
  <WebsiteCrudPage
    title="官网站点"
    entity-name="站点"
    api="system/website/site"
    description="维护多个官网站点的域名、SEO、联系方式和启停状态，是公开接口解析 site 与 Host 的入口。"
    intro-text="公开接口必须先解析到启用站点，站点禁用后该域名不会继续返回官网数据。"
    search-placeholder="输入站点编码、名称或域名"
    :columns="columns"
    :filter-fields="filters"
    :form-fields="fields"
    form-help="别名域名一行一个；SEO、联系方式和扩展配置必须填写 JSON 对象。"
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
  { label: '租户ID', name: 'tenant_id', type: 'number', defaultValue: 0, help: '公开接口会跟随站点 tenant_id 过滤栏目、导航、内容和区块。' },
  { label: '站点编码', name: 'code', required: true, placeholder: '例如 dmsai' },
  { label: '站点名称', name: 'name', required: true, placeholder: '例如 德玛仕智慧厨房' },
  { label: '主域名', name: 'domain', required: true, placeholder: '例如 dmsai.cn' },
  { label: '备用域名', name: 'aliases', type: 'tags', span: 24, placeholder: '一行一个，例如 www.dmsai.cn' },
  { label: 'Logo', name: 'logo', span: 12, placeholder: '文件 URL 或资源地址' },
  { label: 'Favicon', name: 'favicon', span: 12, placeholder: '文件 URL 或资源地址' },
  { label: 'SEO配置', name: 'seo', type: 'json', span: 24, jsonFallback: { title: '', keywords: '', description: '' } },
  { label: '联系方式', name: 'contact', type: 'json', span: 24, jsonFallback: { phone: '', address: '' } },
  { label: '扩展配置', name: 'config', type: 'json', span: 24, jsonFallback: {} },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
