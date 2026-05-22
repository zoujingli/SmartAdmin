<template>
  <Row :gutter="[16, 16]" class="crud-stat-cards">
    <Col v-for="item in items" :key="item.label" :xs="24" :sm="12" :xl="6">
      <Card class="crud-stat-card">
        <div class="crud-stat-card__header">
          <div class="crud-stat-card__icon" :style="getToneStyle(item.tone)">
            <IconifyIcon :icon="normalizeIcon(item.icon)" class="crud-stat-card__icon-inner" />
          </div>
          <div class="crud-stat-card__label">{{ item.label }}</div>
        </div>
        <div class="crud-stat-card__value">{{ item.value }}</div>
        <div class="crud-stat-card__desc" :title="item.desc">{{ item.desc }}</div>
      </Card>
    </Col>
  </Row>
</template>

<script lang="ts" setup>
import { Card, Col, Row, theme } from 'ant-design-vue';
import { IconifyIcon } from '@vben/icons';

export interface CrudStatCardItem {
  desc: string;
  icon: string;
  label: string;
  tone?: 'danger' | 'info' | 'primary' | 'success' | 'warning';
  value: string;
}

interface Props {
  items: CrudStatCardItem[];
}

defineProps<Props>();

const { token } = theme.useToken();

function getToneStyle(tone?: CrudStatCardItem['tone']) {
  const styleMap = {
    danger: {
      backgroundColor: token.value.colorErrorBg,
      color: token.value.colorError,
    },
    info: {
      backgroundColor: token.value.colorInfoBg ?? token.value.colorPrimaryBg,
      color: token.value.colorInfo ?? token.value.colorPrimary,
    },
    primary: {
      backgroundColor: token.value.colorPrimaryBg,
      color: token.value.colorPrimary,
    },
    success: {
      backgroundColor: token.value.colorSuccessBg,
      color: token.value.colorSuccess,
    },
    warning: {
      backgroundColor: token.value.colorWarningBg,
      color: token.value.colorWarning,
    },
  } as const;

  return styleMap[tone || 'primary'];
}

function normalizeIcon(icon: string) {
  let normalized = icon;

  if (icon.startsWith('i-')) {
    const raw = icon.slice(2);
    const splitIndex = raw.indexOf('-');

    if (splitIndex > 0) {
      normalized = `${raw.slice(0, splitIndex)}:${raw.slice(splitIndex + 1)}`;
    }
  }

  const aliases: Record<string, string> = {
    'lucide:buildings': 'lucide:building-2',
    'lucide:circle-check-big': 'lucide:circle-check',
    'lucide:upload-cloud': 'lucide:cloud-upload',
  };

  return aliases[normalized] ?? normalized;
}
</script>

<style scoped>
.crud-stat-card :deep(.ant-card-body) {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 16px;
}

.crud-stat-card :deep(.ant-card-body::before),
.crud-stat-card :deep(.ant-card-body::after) {
  display: none;
}

.crud-stat-card__header {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.crud-stat-card__icon {
  display: flex;
  height: 32px;
  width: 32px;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  flex-shrink: 0;
}

.crud-stat-card__icon-inner {
  height: 15px;
  width: 15px;
}

.crud-stat-card__label {
  color: v-bind('token.colorText');
  overflow: hidden;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.crud-stat-card__value {
  color: v-bind('token.colorText');
  overflow: hidden;
  font-size: 26px;
  font-weight: 700;
  line-height: 1;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.crud-stat-card__desc {
  color: v-bind('token.colorTextSecondary');
  overflow: hidden;
  font-size: 12px;
  line-height: 1.6;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 768px) {
  .crud-stat-card :deep(.ant-card-body) {
    padding: 12px 14px;
  }
}
</style>
