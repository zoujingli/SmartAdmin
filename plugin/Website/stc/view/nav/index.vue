<template>
  <WebsiteCrudPage
    title="官网导航"
    entity-name="导航"
    api="system/website/nav"
    description="维护顶部、底部等导航树，支持站内路由、外部链接、关联栏目和关联内容。"
    intro-text="导航只影响后台维护的数据，官网展示前端可按 position 拉取并自行渲染。"
    search-placeholder="输入导航标题、路由或链接"
    :columns="columns"
    :filter-fields="filters"
    :form-fields="fields"
  />
</template>

<script setup lang="ts">
import WebsiteCrudPage from '../components/website-crud-page.vue';
import { enabledStatusOptions, navLinkTypeOptions, navPositionOptions, navTargetOptions } from '../website-api';

const columns = [
  { title: '导航标题', dataIndex: 'title', width: 180 },
  { title: '所属站点', dataIndex: ['site', 'name'], width: 140 },
  { title: '导航位置', dataIndex: 'position', valueType: 'option', options: navPositionOptions, width: 110 },
  { title: '链接类型', dataIndex: 'link_type', valueType: 'option', options: navLinkTypeOptions, width: 120 },
  { title: '站内路由', dataIndex: 'route', valueType: 'route', width: 180 },
  { title: '外部地址', dataIndex: 'url', valueType: 'longText', width: 220 },
  { title: '父级导航', dataIndex: 'parent_id', valueType: 'option', optionApi: 'system/website/nav/options', emptyValue: 0, emptyLabel: '顶级导航', width: 120 },
  { title: '排序', dataIndex: 'sort', width: 90 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
];

const filters: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options' },
  { label: '导航位置', name: 'position', type: 'select', options: navPositionOptions },
  { label: '链接类型', name: 'link_type', type: 'select', options: navLinkTypeOptions },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions },
];

const fields: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options', required: true, emptyValue: 0 },
  { label: '父级导航', name: 'parent_id', type: 'select', optionApi: 'system/website/nav/options', defaultValue: 0, emptyValue: 0, emptyLabel: '顶级导航', filterBySite: true, excludeSelf: true },
  { label: '导航位置', name: 'position', type: 'select', options: navPositionOptions, defaultValue: 'top', emptyValue: 'top' },
  { label: '导航标题', name: 'title', required: true, placeholder: '例如 智慧食安' },
  { label: '链接类型', name: 'link_type', type: 'select', options: navLinkTypeOptions, defaultValue: 'route', emptyValue: 'route' },
  { label: '站内路由', name: 'route', placeholder: '例如 /safe/' },
  { label: '外部地址', name: 'url', placeholder: '例如 https://www.demashi.net.cn' },
  { label: '关联栏目', name: 'channel_id', type: 'select', optionApi: 'system/website/channel/options', defaultValue: 0, emptyValue: 0, emptyLabel: '不关联栏目', filterBySite: true },
  { label: '关联内容', name: 'content_id', type: 'select', optionApi: 'system/website/content/options', defaultValue: 0, emptyValue: 0, emptyLabel: '不关联内容', filterBySite: true },
  { label: '打开方式', name: 'target', type: 'select', options: navTargetOptions, defaultValue: 'self', emptyValue: 'self' },
  { label: '排序', name: 'sort', type: 'number', defaultValue: 0 },
  { label: '当前状态', name: 'status', type: 'select', options: enabledStatusOptions, defaultValue: 1, emptyValue: 1 },
];
</script>
