<template>
  <WebsiteCrudPage
    title="访客线索"
    entity-name="线索"
    api="system/website/lead"
    description="查看官网访客提交的咨询线索，支持处理状态和备注维护，不提供后端导出接口。"
    intro-text="手机号、邮箱等敏感字段只在授权后台展示；处理操作日志会脱敏请求字段。"
    search-placeholder="输入联系人、手机号、邮箱、公司或主题"
    lead-mode
    :allow-create="false"
    :allow-edit="false"
    :allow-status="false"
    :columns="columns"
    :filter-fields="filters"
    :form-fields="[]"
  />
</template>

<script setup lang="ts">
import WebsiteCrudPage from '../components/website-crud-page.vue';
import { leadStatusOptions } from '../website-api';

const columns = [
  { title: '联系人', dataIndex: 'name', width: 120 },
  { title: '所属站点', dataIndex: ['site', 'name'], width: 140 },
  { title: '手机号', dataIndex: 'mobile', valueType: 'copy', width: 150 },
  { title: '邮箱', dataIndex: 'email', valueType: 'copy', width: 200 },
  { title: '公司', dataIndex: 'company', width: 180 },
  { title: '咨询主题', dataIndex: 'subject', valueType: 'longText', width: 220 },
  { title: '咨询内容', dataIndex: 'content', valueType: 'longText', width: 260 },
  { title: '处理状态', key: 'lead_status', dataIndex: 'status', width: 120 },
  { title: '来源页面', dataIndex: 'source_url', valueType: 'longText', width: 220 },
  { title: '提交IP', dataIndex: 'ip', width: 140 },
  { title: '提交时间', dataIndex: 'created_at', width: 180 },
  { title: '处理时间', dataIndex: 'handled_at', width: 180 },
];

const filters: any[] = [
  { label: '所属站点', name: 'site_id', type: 'select', optionApi: 'system/website/site/options' },
  { label: '处理状态', name: 'status', type: 'select', options: leadStatusOptions },
];
</script>
