<script setup lang="ts">
import type { WorkbenchTodoItem } from '../typing';

import { computed } from 'vue';
import { useRouter } from 'vue-router';

import { theme } from 'ant-design-vue';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  VbenButton,
  VbenCheckbox,
} from '@vben-core/shadcn-ui';

interface Props {
  items?: WorkbenchTodoItem[];
  title: string;
}

defineOptions({
  name: 'WorkbenchTodo',
});

withDefaults(defineProps<Props>(), {
  items: () => [],
});

const router = useRouter();
const { token } = theme.useToken();

const levelLabelMap: Record<string, string> = {
  danger: '高风险',
  info: '待处理',
  success: '正常',
  warning: '关注',
};

const levelStyleMap = computed<Record<string, Record<string, string>>>(() => ({
  danger: {
    backgroundColor: `color-mix(in srgb, ${token.value.colorError} 12%, transparent)`,
    color: token.value.colorError,
  },
  info: {
    backgroundColor: `color-mix(in srgb, ${token.value.colorPrimary} 12%, transparent)`,
    color: token.value.colorPrimary,
  },
  success: {
    backgroundColor: `color-mix(in srgb, ${token.value.colorSuccess} 12%, transparent)`,
    color: token.value.colorSuccess,
  },
  warning: {
    backgroundColor: `color-mix(in srgb, ${token.value.colorWarning} 12%, transparent)`,
    color: token.value.colorWarning,
  },
}));

function handleNavigate(url?: string) {
  const raw = url?.trim();
  if (!raw) {
    return;
  }

  if (raw.startsWith('http')) {
    window.open(raw, '_blank', 'noopener,noreferrer');
    return;
  }

  const path = raw.startsWith('/') ? raw : `/${raw}`;
  router.push(path).catch(() => undefined);
}
</script>

<template>
  <Card>
    <CardHeader class="py-4">
      <CardTitle class="text-lg">{{ title }}</CardTitle>
    </CardHeader>
    <CardContent class="flex flex-wrap p-5 pt-0">
      <ul class="divide-border w-full divide-y" role="list">
        <li
          v-for="item in items"
          :key="item.title"
          :class="{
            'select-none line-through opacity-60': item.completed,
          }"
          class="flex cursor-pointer justify-between gap-x-6 py-5"
        >
          <div class="flex min-w-0 items-center gap-x-4">
            <VbenCheckbox v-model="item.completed" name="completed" />
            <div class="min-w-0 flex-auto">
              <div class="flex items-center gap-2">
                <p class="text-foreground text-sm font-semibold leading-6">
                  {{ item.title }}
                </p>
                <span
                  v-if="item.level"
                  :style="levelStyleMap[item.level]"
                  class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                >
                  {{ levelLabelMap[item.level] || item.level }}
                </span>
              </div>
              <!-- eslint-disable vue/no-v-html -->
              <p
                class="text-foreground/80 *:text-primary mt-1 truncate text-xs leading-5"
                v-html="item.content"
              ></p>
              <div v-if="item.url && item.action_text && !item.completed" class="mt-3">
                <VbenButton size="sm" variant="outline" @click="handleNavigate(item.url)">
                  {{ item.action_text }}
                </VbenButton>
              </div>
            </div>
          </div>
          <div class="hidden h-full shrink-0 sm:flex sm:flex-col sm:items-end">
            <span class="text-foreground/80 mt-6 text-xs leading-6">
              {{ item.date }}
            </span>
          </div>
        </li>
      </ul>
    </CardContent>
  </Card>
</template>
