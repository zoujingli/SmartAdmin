<script setup lang="ts">
import type { WorkbenchProjectItem } from '../typing';

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  VbenIcon,
} from '@vben-core/shadcn-ui';

interface Props {
  items?: WorkbenchProjectItem[];
  title: string;
}

defineOptions({
  name: 'WorkbenchProject',
});

withDefaults(defineProps<Props>(), {
  items: () => [],
});

const emit = defineEmits(['click']);

function isNavigable(item: WorkbenchProjectItem): boolean {
  const url = item.url?.trim();
  return Boolean(url && (url.startsWith('/') || url.startsWith('http')));
}

function onTileActivate(item: WorkbenchProjectItem) {
  if (!isNavigable(item)) {
    return;
  }
  emit('click', item);
}
</script>

<template>
  <Card>
    <CardHeader class="py-4">
      <CardTitle class="text-lg">{{ title }}</CardTitle>
    </CardHeader>
    <CardContent class="flex flex-wrap p-0">
      <template v-for="(item, index) in items" :key="item.title">
        <div
          :class="{
            'border-r-0': index % 3 === 2,
            'border-b-0': index < 3,
            'pb-4': index > 2,
            'rounded-bl-xl': index === items.length - 3,
            'rounded-br-xl': index === items.length - 1,
            'cursor-pointer hover:shadow-xl': isNavigable(item),
            'cursor-default': !isNavigable(item),
          }"
          :role="isNavigable(item) ? 'button' : undefined"
          :tabindex="isNavigable(item) ? 0 : undefined"
          class="border-border group w-full border-r border-t p-4 transition-all md:w-1/2 lg:w-1/3"
          @click="onTileActivate(item)"
          @keydown.enter.prevent="onTileActivate(item)"
          @keydown.space.prevent="onTileActivate(item)"
        >
          <div class="flex items-center">
            <VbenIcon
              :color="item.color"
              :icon="item.icon"
              class="size-8 transition-all duration-300 group-hover:scale-110"
            />
            <span class="ml-4 text-lg font-medium">{{ item.title }}</span>
          </div>
          <div
            class="workbench-project-content text-foreground/80 mt-4 h-16 overflow-y-auto break-words pr-1 text-sm leading-6"
          >
            {{ item.content }}
          </div>
        </div>
      </template>
    </CardContent>
  </Card>
</template>

<style scoped>
.workbench-project-content {
  scrollbar-width: thin;
  scrollbar-color: transparent transparent;
}

.workbench-project-content::-webkit-scrollbar {
  width: 4px;
}

.workbench-project-content::-webkit-scrollbar-track {
  background: transparent;
}

.workbench-project-content::-webkit-scrollbar-thumb {
  border-radius: 9999px;
  background: transparent;
}

.workbench-project-content:hover {
  scrollbar-color: color-mix(in srgb, currentColor 18%, transparent) transparent;
}

.workbench-project-content:hover::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, currentColor 18%, transparent);
}
</style>
