<template>
  <div class="crud-detail-hero">
    <div v-if="hasAside" class="crud-detail-hero__aside">
      <slot name="aside" />
    </div>
    <div v-else class="crud-detail-hero__badge" :style="getToneStyle(tone)">
      <IconifyIcon :icon="normalizeIcon(icon)" class="crud-detail-hero__icon" />
    </div>
    <div class="crud-detail-hero__content">
      <div class="crud-detail-hero__title">{{ title }}</div>
      <div v-if="normalizedTags.length > 0" class="crud-detail-hero__meta">
        <Tag
          v-for="tag in normalizedTags"
          :key="`${tag.label}-${tag.color || 'default'}`"
          :color="tag.color"
        >
          {{ tag.label }}
        </Tag>
      </div>
      <div v-for="line in normalizedLines" :key="line" class="crud-detail-hero__subtext">
        {{ line }}
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, useSlots } from 'vue';

import { IconifyIcon } from '@vben/icons';

import { Tag, theme } from 'ant-design-vue';

export interface CrudDetailHeroTag {
  color?: string;
  label: number | string;
}

interface Props {
  icon: string;
  lines?: Array<false | null | string | undefined>;
  tags?: CrudDetailHeroTag[];
  title: string;
  tone?: 'danger' | 'info' | 'primary' | 'success' | 'warning';
}

const props = withDefaults(defineProps<Props>(), {
  lines: () => [],
  tags: () => [],
  tone: 'primary',
});

const { token } = theme.useToken();
const slots = useSlots();

const hasAside = computed(() => Boolean(slots.aside));

const normalizedLines = computed(() =>
  props.lines.filter((line): line is string => typeof line === 'string' && line.trim().length > 0),
);

const normalizedTags = computed(() =>
  props.tags.filter((tag) => String(tag.label).trim().length > 0),
);

function getToneStyle(tone: Props['tone']) {
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
.crud-detail-hero {
  background: v-bind('token.colorFillQuaternary');
  border: 1px solid v-bind('token.colorBorderSecondary');
  border-radius: 16px;
  display: grid;
  gap: 20px;
  grid-template-columns: auto minmax(0, 1fr);
  padding: 20px;
}

.crud-detail-hero__badge {
  align-items: center;
  border-radius: 18px;
  display: flex;
  height: 80px;
  justify-content: center;
  width: 80px;
}

.crud-detail-hero__aside {
  align-items: center;
  display: flex;
  justify-content: center;
  min-width: 0;
}

.crud-detail-hero__icon {
  height: 30px;
  width: 30px;
}

.crud-detail-hero__content {
  display: grid;
  gap: 10px;
  min-width: 0;
}

.crud-detail-hero__title {
  color: v-bind('token.colorText');
  font-size: 20px;
  font-weight: 600;
  line-height: 28px;
  overflow-wrap: anywhere;
}

.crud-detail-hero__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.crud-detail-hero__subtext {
  color: v-bind('token.colorTextSecondary');
  font-size: 13px;
  line-height: 20px;
  overflow-wrap: anywhere;
}

@media (max-width: 767px) {
  .crud-detail-hero {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
