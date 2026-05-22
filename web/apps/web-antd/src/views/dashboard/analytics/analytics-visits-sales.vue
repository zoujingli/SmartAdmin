<script lang="ts" setup>
import type { EchartsUIType } from '@vben/plugins/echarts';

import { onMounted, ref, watch } from 'vue';

import { EchartsUI, useEcharts } from '@vben/plugins/echarts';

interface Props {
  items?: Array<{ name: string; value: number }>;
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
});

const chartRef = ref<EchartsUIType>();
const { renderEcharts } = useEcharts(chartRef);

function renderChart() {
  renderEcharts({
    series: [
      {
        animationDelay() {
          return Math.random() * 400;
        },
        animationEasing: 'exponentialInOut',
        animationType: 'scale',
        center: ['50%', '50%'],
        color: ['#5ab1ef', '#b6a2de', '#67e0e3', '#2ec7c9', '#f9a825', '#ef5350'],
        data: [...props.items].sort((left, right) => left.value - right.value),
        name: '业务热度',
        radius: '80%',
        roseType: 'radius',
        type: 'pie',
      },
    ],
    tooltip: {
      trigger: 'item',
    },
  });
}

watch(() => props.items, renderChart, { deep: true });

onMounted(() => {
  renderChart();
});
</script>

<template>
  <EchartsUI ref="chartRef" />
</template>
