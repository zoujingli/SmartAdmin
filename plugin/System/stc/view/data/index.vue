<template>
  <Page title="系统数据">
    <template #extra>
      <Space :size="12" wrap class="justify-end">
        <Button :loading="loading" @click="loadAll">
          <span class="i-lucide-refresh-cw mr-1" />
          刷新数据
        </Button>
        <Button @click="handleCopyConfig">
          <span class="i-lucide-copy mr-1" />
          复制配置
        </Button>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <div class="mb-5">
        <div>
          <div class="text-foreground text-lg font-semibold">运行总览</div>
          <div class="text-foreground/60 mt-1 text-sm">
            聚合系统统计、模块矩阵、在线会话与配置预览，便于统一运维。
          </div>
        </div>
      </div>

      <CrudStatCards class="mb-5" :items="overviewCards" />

      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="overview" tab="系统概览">
          <Row :gutter="[16, 16]">
            <Col :lg="15" :span="24">
              <Card title="系统信息" :loading="loading">
                <CrudDetailDescriptions>
                  <DescriptionsItem label="系统名称">{{ info.name }}</DescriptionsItem>
                  <DescriptionsItem label="系统版本">{{ info.version }}</DescriptionsItem>
                  <DescriptionsItem label="PHP">{{ info.php_version }}</DescriptionsItem>
                  <DescriptionsItem label="Hyperf">{{ info.hyperf_version }}</DescriptionsItem>
                  <DescriptionsItem label="Swoole">{{ info.swoole_version }}</DescriptionsItem>
                  <DescriptionsItem label="服务器时间">{{ info.server_time }}</DescriptionsItem>
                  <DescriptionsItem label="时区">{{ info.timezone }}</DescriptionsItem>
                  <DescriptionsItem label="内存限制">{{ info.memory_limit }}</DescriptionsItem>
                  <DescriptionsItem label="执行超时" :span="2">{{ info.max_execution_time }}</DescriptionsItem>
                </CrudDetailDescriptions>
              </Card>

              <Card class="mt-4" title="公共能力基线" :loading="loading">
                <CrudNoticeAlert
                  custom-class="mb-4"
                  :message="`当前共 ${capabilities.summary.common_capability_count} 项公共能力，接入模块 ${capabilities.summary.module_count} 个。`"
                />
                <Row :gutter="[12, 12]" class="mb-4">
                  <Col :span="12">
                    <div class="system-data-kv-card">
                      <div class="text-foreground/60 text-xs">缓存驱动</div>
                      <div class="text-foreground mt-1 font-medium">{{ capabilities.summary.cache_driver || 'file' }}</div>
                    </div>
                  </Col>
                  <Col :span="12">
                    <div class="system-data-kv-card">
                      <div class="text-foreground/60 text-xs">权限口径</div>
                      <div class="text-foreground mt-1 text-sm font-medium">{{ capabilities.summary.permission_strategy || '-' }}</div>
                    </div>
                  </Col>
                  <Col :span="24">
                    <div class="system-data-kv-card">
                      <div class="text-foreground/60 text-xs">菜单来源</div>
                      <div class="text-foreground mt-1 text-sm font-medium">{{ capabilities.summary.menu_source || '-' }}</div>
                    </div>
                  </Col>
                </Row>

                <div class="grid gap-3 md:grid-cols-2">
                  <div
                    v-for="item in capabilities.common_features"
                    :key="item.key"
                    class="system-data-feature-card"
                  >
                    <div class="mb-2 flex items-center justify-between gap-3">
                      <span class="text-foreground font-medium">{{ item.name }}</span>
                      <CrudToneTag color="processing" :text="item.key" />
                    </div>
                    <div class="text-foreground/60 text-sm leading-6">{{ item.description }}</div>
                  </div>
                </div>
              </Card>
            </Col>

            <Col :lg="9" :span="24">
              <Card title="运行摘要" :loading="loading">
                <div class="space-y-3">
                  <div class="system-data-plain-card">
                    <div class="text-foreground/60 text-xs">在线态</div>
                    <div class="text-foreground mt-1 text-sm font-medium">
                      当前在线用户 {{ capabilities.summary.online_user_count }} 人，活跃会话 {{ capabilities.summary.online_session_count }} 个
                    </div>
                  </div>
                  <div class="system-data-plain-card">
                    <div class="text-foreground/60 text-xs">日志概况</div>
                    <div class="text-foreground mt-1 text-sm font-medium">
                      总日志 {{ statistics.log_count }} 条，今日新增 {{ statistics.today_logs }} 条
                    </div>
                  </div>
                  <div class="system-data-plain-card">
                    <div class="text-foreground/60 text-xs">组织结构</div>
                    <div class="text-foreground mt-1 text-sm font-medium">
                      部门 {{ statistics.dept_count }} 个，岗位 {{ statistics.post_count }} 个
                    </div>
                  </div>
                </div>
              </Card>

              <Card class="mt-4" title="最近新增用户" :loading="loading">
                <CrudEmptyState
                  v-if="statistics.recent_users.length === 0"
                  description="暂无最近用户"
                  size="lg"
                />
                <div v-else class="space-y-3">
                  <div
                    v-for="item in statistics.recent_users"
                    :key="item.id"
                    class="system-data-user-card"
                  >
                    <div class="flex items-center justify-between gap-3">
                      <div class="min-w-0">
                        <div class="text-foreground truncate font-medium">
                          {{ item.nickname || item.username }}
                        </div>
                        <div class="text-foreground/60 truncate text-xs">
                          {{ item.username }}
                        </div>
                      </div>
                      <CrudToneTag :text="item.created_at" />
                    </div>
                  </div>
                </div>
              </Card>
            </Col>
          </Row>
        </TabPane>

        <TabPane key="modules" tab="模块矩阵">
          <Row :gutter="[16, 16]">
            <Col :lg="16" :span="24">
              <Card :loading="loading" title="模块矩阵">
                <div class="mb-4 grid gap-3 md:grid-cols-3">
                  <div class="system-data-kv-card">
                    <div class="text-foreground/60 text-xs">模块数量</div>
                    <div class="text-foreground mt-1 text-xl font-semibold">{{ capabilities.summary.module_count }}</div>
                  </div>
                  <div class="system-data-kv-card">
                    <div class="text-foreground/60 text-xs">公共能力</div>
                    <div class="text-foreground mt-1 text-xl font-semibold">{{ capabilities.summary.common_capability_count }}</div>
                  </div>
                  <div class="system-data-kv-card">
                    <div class="text-foreground/60 text-xs">会话概况</div>
                    <div class="text-foreground mt-1 text-xl font-semibold">{{ capabilities.summary.online_session_count }}</div>
                  </div>
                </div>

                <Table
                  :columns="moduleColumns"
                  :data-source="capabilities.modules"
                  :pagination="false"
                  row-key="key"
                  :scroll="moduleTableScroll"
                  size="small"
                >
                  <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                      <div class="font-medium text-foreground">{{ record.name }}</div>
                      <div class="text-foreground/60 text-xs leading-5">{{ record.summary }}</div>
                    </template>
                    <template v-else-if="column.key === 'path'">
                      <CrudToneTag :text="record.path || '—'" />
                    </template>
                    <template v-else-if="column.key === 'features'">
                      <CrudTagList :items="record.features || []" color="processing" />
                    </template>
                    <template v-else-if="column.key === 'metrics'">
                      <div class="text-foreground">{{ record.page_count }} 页面</div>
                      <div class="text-foreground/60 text-xs">{{ record.action_count }} 动作 / {{ record.hidden_page_count }} 隐藏页</div>
                    </template>
                  </template>
                </Table>
              </Card>
            </Col>

            <Col :lg="8" :span="24">
              <Card :loading="loading" title="在线会话">
                <CrudNoticeAlert
                  custom-class="mb-4"
                  type="success"
                  :message="`当前 ${capabilities.summary.online_user_count} 个在线用户，${capabilities.summary.online_session_count} 个在线会话。`"
                />
                <CrudEmptyState
                  v-if="capabilities.online_users.length === 0"
                  description="暂无在线会话"
                  size="lg"
                />
                <div v-else class="space-y-3">
                  <div
                    v-for="item in capabilities.online_users"
                    :key="`${item.user_id}-${item.last_active_at}`"
                    class="system-data-session-card"
                  >
                    <div class="mb-2 flex items-center justify-between gap-3">
                      <span class="text-foreground truncate font-medium">
                        {{ item.nickname || item.username }}
                      </span>
                      <CrudToneTag color="success" :text="String(item.user_model || '').split('\\').pop() || '-'" />
                    </div>
                    <div class="text-foreground/60 text-xs">账号：{{ item.username }}</div>
                    <div class="text-foreground/60 mt-1 text-xs">最近活跃：{{ item.last_active_at }}</div>
                  </div>
                </div>
              </Card>
            </Col>
          </Row>
        </TabPane>

        <TabPane key="config" tab="配置预览">
          <Row :gutter="[16, 16]">
            <Col :lg="16" :span="24">
              <Card title="系统配置" :loading="loading">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                  <div class="text-foreground/60 text-sm">
                    当前配置共 {{ visibleConfigText.length }} 个字符。
                  </div>
                  <Space v-if="canSaveConfig" :size="8" wrap>
                    <Button v-if="!editingConfig" @click="handleEditConfig">
                      <span class="i-lucide-edit-3 mr-1" />
                      编辑配置
                    </Button>
                    <template v-else>
                      <Button @click="handleCancelEditConfig">
                        <span class="i-lucide-x mr-1" />
                        取消
                      </Button>
                      <Button type="primary" :loading="savingConfig" @click="handleSaveConfig">
                        <span class="i-lucide-save mr-1" />
                        保存配置
                      </Button>
                    </template>
                  </Space>
                </div>
                <CrudNoticeAlert
                  custom-class="mb-4"
                  :message="canSaveConfig ? '配置保存会覆盖同名 config_* 项，请确认 JSON 结构后再提交。' : '当前账号仅可查看配置预览。'"
                />
                <Textarea
                  v-if="editingConfig"
                  v-model:value="editableConfigText"
                  class="system-data-config-editor"
                  :rows="18"
                  placeholder="请输入系统配置 JSON"
                />
                <div v-else class="system-data-config-preview">
                  <pre>{{ visibleConfigText }}</pre>
                </div>
              </Card>
            </Col>

            <Col :lg="8" :span="24">
              <Card title="品牌配置预览" :loading="loading">
                <div v-if="configPreview" class="space-y-3">
                  <div class="system-data-kv-card">
                    <div class="text-foreground/60 text-xs">应用名称</div>
                    <div class="text-foreground mt-1 font-medium">{{ configPreview.app_name || '-' }}</div>
                  </div>
                  <div class="system-data-kv-card">
                    <div class="text-foreground/60 text-xs">版本号</div>
                    <div class="text-foreground mt-1 font-medium">{{ configPreview.app_version || '-' }}</div>
                  </div>
                  <div class="system-data-kv-card">
                    <div class="text-foreground/60 text-xs">应用描述</div>
                    <div class="text-foreground mt-1 text-sm leading-6">{{ configPreview.app_description || '-' }}</div>
                  </div>
                  <div class="system-data-kv-card">
                    <div class="text-foreground/60 text-xs">版权信息</div>
                    <div class="text-foreground mt-1 text-sm font-medium">
                      {{ configPreview.company_name || '-' }} / {{ configPreview.copyright_date || '-' }}
                    </div>
                  </div>
                </div>
                <CrudEmptyState
                  v-else
                  description="当前配置中未找到 app_meta 或 JSON 无法解析"
                  size="lg"
                />
              </Card>

              <Card class="mt-4" title="维护说明">
                <div class="space-y-3 text-sm">
                  <div class="system-data-plain-card">推荐将界面品牌信息统一维护在 <code>app_meta</code> 下。</div>
                  <div class="system-data-plain-card">如需调整配置，请通过专用配置维护入口处理，不建议在系统数据页承担编辑职责。</div>
                  <div class="system-data-plain-card">修改品牌信息后，登录页与主界面会在刷新后读取最新配置。</div>
                </div>
              </Card>

              <Card class="mt-4" title="配置结构">
                <CrudEmptyState
                  v-if="configRootEntries.length === 0"
                  description="暂无可展示的配置结构"
                  size="md"
                />
                <div v-else class="space-y-3">
                  <div v-for="item in configRootEntries" :key="item.key" class="system-data-plain-card">
                    <div class="flex items-center justify-between gap-3">
                      <span class="text-foreground font-medium">{{ item.key }}</span>
                      <CrudToneTag :text="item.type" />
                    </div>
                    <div class="text-foreground/60 mt-2 text-xs leading-5">{{ item.preview }}</div>
                  </div>
                </div>
              </Card>
            </Col>
          </Row>
        </TabPane>
      </Tabs>
    </Card>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useAccess } from '@vben/access';
import {
  CrudDetailDescriptions,
  CrudEmptyState,
  CrudNoticeAlert,
  CrudStatCards,
  CrudTagList,
  CrudToneTag,
  Page,
} from '@vben/common-ui';
import {
  Button,
  Card,
  Col,
  DescriptionsItem,
  Modal,
  message,
  Row,
  Space,
  Table,
  Tabs,
  TabPane,
  Textarea,
} from 'ant-design-vue';

import { dataApiService } from '#/api';
import type { DataApi } from '#/api/system/data';
import { buildTableScrollX } from '#/utils/table';

type DataTab = 'config' | 'modules' | 'overview';

const activeTab = ref<DataTab>('overview');
const loading = ref(false);
const savingConfig = ref(false);
const editingConfig = ref(false);
const editableConfigText = ref('{}');
const { hasAccessByCodes } = useAccess();
const canSaveConfig = computed(() => hasAccessByCodes(['system.data.save']));

const statistics = ref<DataApi.Statistics>({
  user_count: 0,
  role_count: 0,
  menu_count: 0,
  dept_count: 0,
  post_count: 0,
  node_count: 0,
  log_count: 0,
  online_count: 0,
  online_session_count: 0,
  today_logs: 0,
  recent_users: [],
});

const info = ref<DataApi.SystemInfo>({
  name: '-',
  version: '-',
  php_version: '-',
  hyperf_version: '-',
  swoole_version: '-',
  server_time: '-',
  timezone: '-',
  memory_limit: '-',
  max_execution_time: '-',
});

const capabilities = ref<DataApi.CapabilityOverview>({
  summary: {
    module_count: 0,
    common_capability_count: 0,
    cache_driver: 'file',
    cache_dynamic: true,
    permission_strategy: '-',
    menu_source: '-',
    online_user_count: 0,
    online_session_count: 0,
  },
  common_features: [],
  modules: [],
  online_users: [],
});

const configText = ref('{}');
const visibleConfigText = computed(() => (editingConfig.value ? editableConfigText.value : configText.value));

const overviewCards = computed(() => [
  {
    desc: `角色 ${statistics.value.role_count} / 部门 ${statistics.value.dept_count} / 岗位 ${statistics.value.post_count}`,
    icon: 'i-lucide-users',
    label: '用户与组织',
    tone: 'primary' as const,
    value: String(statistics.value.user_count),
  },
  {
    desc: `权限节点 ${statistics.value.node_count} 个`,
    icon: 'i-lucide-waypoints',
    label: '菜单与权限',
    tone: 'info' as const,
    value: String(statistics.value.menu_count),
  },
  {
    desc: `今日日志 ${statistics.value.today_logs} 条`,
    icon: 'i-lucide-file-text',
    label: '日志与活跃',
    tone: 'warning' as const,
    value: String(statistics.value.log_count),
  },
  {
    desc: `在线用户 ${statistics.value.online_count} 人`,
    icon: 'i-lucide-activity',
    label: '在线会话',
    tone: 'success' as const,
    value: String(statistics.value.online_session_count),
  },
]);

function toPlainConfigObject(value: unknown): Record<string, any> | null {
  if (typeof value === 'string') {
    const text = value.trim();
    if (!text) {
      return null;
    }

    try {
      return toPlainConfigObject(JSON.parse(text));
    } catch {
      return null;
    }
  }

  return value && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, any>)
    : null;
}

function normalizeAppMetaPreview(value: unknown): Record<string, any> | null {
  const appMeta = toPlainConfigObject(value);
  const nestedAppMeta = toPlainConfigObject(appMeta?.app_meta);
  if (!appMeta) {
    return null;
  }

  if (!nestedAppMeta) {
    return appMeta;
  }

  const normalized = { ...nestedAppMeta, ...appMeta };
  delete normalized.app_meta;
  return normalized;
}

const configPreview = computed(() => {
  try {
    const parsed = toPlainConfigObject(JSON.parse(visibleConfigText.value || '{}'));
    // app_meta 可能被历史配置保存成 JSON 字符串或二次嵌套对象，预览时统一取最终字段对象。
    return normalizeAppMetaPreview(parsed?.app_meta);
  } catch {
    return null;
  }
});

const configRootEntries = computed(() => {
  try {
    const parsed = JSON.parse(visibleConfigText.value || '{}');
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return [];
    }

    return Object.entries(parsed).map(([key, value]) => {
      const type = Array.isArray(value) ? 'array' : typeof value;
      const preview =
        typeof value === 'string'
          ? value
          : JSON.stringify(value, null, 2).replace(/\s+/g, ' ').slice(0, 120) || '—';

      return { key, preview, type };
    });
  } catch {
    return [];
  }
});

const moduleColumns = [
  { title: '模块', key: 'name', width: 240 },
  { title: '路径', key: 'path', width: 180 },
  { title: '能力标签', key: 'features' },
  { title: '规模', key: 'metrics', width: 150 },
];

const moduleTableScroll = buildTableScrollX(moduleColumns, { minWidth: 720 });

const loadAll = async () => {
  try {
    loading.value = true;
    const [statisticsResp, infoResp, configResp, capabilityResp] = await Promise.all([
      dataApiService.getStatistics(),
      dataApiService.getSystemInfo(),
      dataApiService.getConfig(),
      dataApiService.getCapabilities(),
    ]);

    statistics.value = statisticsResp || statistics.value;
    info.value = infoResp || info.value;
    capabilities.value = capabilityResp || capabilities.value;
    configText.value = JSON.stringify(configResp || {}, null, 2);
    if (!editingConfig.value) {
      editableConfigText.value = configText.value;
    }
  } catch (error) {
    console.error('加载系统数据失败:', error);
    message.error('加载系统数据失败');
  } finally {
    loading.value = false;
  }
};

const handleCopyConfig = async () => {
  try {
    await navigator.clipboard.writeText(visibleConfigText.value);
    message.success('配置已复制到剪贴板');
  } catch (error) {
    console.error('copy config failed', error);
    message.error('复制配置失败');
  }
};

const handleEditConfig = () => {
  editableConfigText.value = configText.value;
  editingConfig.value = true;
};

const handleCancelEditConfig = () => {
  editableConfigText.value = configText.value;
  editingConfig.value = false;
};

const handleSaveConfig = () => {
  let payload: Record<string, any>;

  try {
    const parsed = JSON.parse(editableConfigText.value || '{}');
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      message.error('系统配置必须是 JSON 对象');
      return;
    }
    payload = parsed;
  } catch (error) {
    console.error('parse config failed', error);
    message.error('系统配置 JSON 格式不正确');
    return;
  }

  Modal.confirm({
    title: '确认保存系统配置？',
    content: '保存后会更新对应 config_* 配置项，界面品牌信息将在刷新后读取最新值。',
    async onOk() {
      try {
        savingConfig.value = true;
        // 配置写入接口按对象键名覆盖 config_* 项，这里只提交已解析的对象，避免非法结构进入通用配置表。
        const updated = await dataApiService.updateConfig(payload);
        configText.value = JSON.stringify(updated || payload, null, 2);
        editableConfigText.value = configText.value;
        editingConfig.value = false;
        message.success('配置已保存');
        await loadAll();
      } finally {
        savingConfig.value = false;
      }
    },
  });
};

onMounted(() => {
  loadAll();
});
</script>

<style scoped>
.system-data-kv-card {
  padding: 0.75rem 1rem;
  border: 1px solid var(--ant-colorBorderSecondary);
  border-radius: 0.875rem;
  background: var(--ant-colorFillQuaternary);
}

.system-data-feature-card {
  padding: 0.75rem 1rem;
  border: 1px solid var(--ant-colorBorderSecondary);
  border-radius: 0.875rem;
  background: var(--ant-colorFillQuaternary);
}

.system-data-user-card,
.system-data-session-card,
.system-data-plain-card {
  padding: 0.75rem 1rem;
  border: 1px solid var(--ant-colorBorderSecondary);
  border-radius: 0.875rem;
  background: var(--ant-colorFillQuaternary);
}

.system-data-config-preview {
  max-height: 36rem;
  overflow: auto;
  padding: 1rem;
  border: 1px solid var(--ant-colorBorderSecondary);
  border-radius: 0.875rem;
  background: var(--ant-colorFillQuaternary);
}

.system-data-config-preview pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  color: var(--ant-colorText);
  font-size: 12px;
  line-height: 1.7;
}

</style>
