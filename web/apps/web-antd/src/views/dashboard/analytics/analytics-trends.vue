<script lang="ts" setup>
import type { EchartsUIType } from '@vben/plugins/echarts';

import { onMounted, ref, watch } from 'vue';

import { EchartsUI, useEcharts } from '@vben/plugins/echarts';

interface Props {
  labels?: string[];
  series?: Array<{
    name: string;
    data: number[];
    color?: string;
  }>;
}

const props = withDefaults(defineProps<Props>(), {
  labels: () => [],
  series: () => [],
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
    legend: {
      top: 0,
    },
    series: props.series.map((item) => ({
      areaStyle: {},
      data: item.data,
      itemStyle: {
        color: item.color,
      },
      name: item.name,
      smooth: true,
      type: 'line',
    })),
    tooltip: {
      axisPointer: {
        lineStyle: {
          color: '#019680',
          width: 1,
        },
      },
      trigger: 'axis',
    },
    xAxis: {
      axisTick: {
        show: false,
      },
      boundaryGap: false,
      data: props.labels,
      splitLine: {
        lineStyle: {
          type: 'solid',
          width: 1,
        },
        show: true,
      },
      type: 'category',
    },
    yAxis: [
      {
        axisTick: {
          show: false,
        },
        splitArea: {
          show: true,
        },
        splitNumber: 4,
        type: 'value',
      },
    ],
  });
}

watch(() => [props.labels, props.series], renderChart, { deep: true });

onMounted(() => {
  renderChart();
});
</script>

<template>
  <EchartsUI ref="chartRef" />
</template>
