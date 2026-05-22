<script lang="ts" setup>
import type { EchartsUIType } from '@vben/plugins/echarts';

import { onMounted, ref, watch } from 'vue';

import { EchartsUI, useEcharts } from '@vben/plugins/echarts';

interface Props {
  indicators?: Array<{ name: string; max: number }>;
  series?: Array<{
    name: string;
    value: number[];
    color?: string;
  }>;
}

const props = withDefaults(defineProps<Props>(), {
  indicators: () => [],
  series: () => [],
});

const chartRef = ref<EchartsUIType>();
const { renderEcharts } = useEcharts(chartRef);

function renderChart() {
  renderEcharts({
    legend: {
      bottom: 0,
      data: props.series.map((item) => item.name),
    },
    radar: {
      indicator: props.indicators,
      radius: '60%',
      splitNumber: 6,
    },
    series: [
      {
        data: props.series.map((item) => ({
          itemStyle: {
            color: item.color,
          },
          name: item.name,
          value: item.value,
        })),
        itemStyle: {
          borderRadius: 10,
          borderWidth: 2,
        },
        symbolSize: 0,
        type: 'radar',
      },
    ],
    tooltip: {},
  });
}

watch(() => [props.indicators, props.series], renderChart, { deep: true });

onMounted(() => {
  renderChart();
});
</script>

<template>
  <EchartsUI ref="chartRef" />
</template>
