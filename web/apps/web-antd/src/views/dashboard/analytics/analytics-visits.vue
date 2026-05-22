<script lang="ts" setup>
import type { EchartsUIType } from '@vben/plugins/echarts';

import { onMounted, ref, watch } from 'vue';

import { EchartsUI, useEcharts } from '@vben/plugins/echarts';

interface Props {
  labels?: string[];
  data?: number[];
  seriesName?: string;
}

const props = withDefaults(defineProps<Props>(), {
  labels: () => [],
  data: () => [],
  seriesName: '数量',
});

const chartRef = ref<EchartsUIType>();
const { renderEcharts } = useEcharts(chartRef);

function renderChart() {
  renderEcharts({
    grid: {
      bottom: 0,
      containLabel: true,
      left: '1%',
      right: '1%',
      top: '2%',
    },
    series: [
      {
        barMaxWidth: 80,
        data: props.data,
        name: props.seriesName,
        type: 'bar',
      },
    ],
    tooltip: {
      axisPointer: {
        lineStyle: {
          width: 1,
        },
      },
      trigger: 'axis',
    },
    xAxis: {
      data: props.labels,
      type: 'category',
    },
    yAxis: {
      splitNumber: 4,
      type: 'value',
    },
  });
}

watch(() => [props.labels, props.data, props.seriesName], renderChart, { deep: true });

onMounted(() => {
  renderChart();
});
</script>

<template>
  <EchartsUI ref="chartRef" />
</template>
