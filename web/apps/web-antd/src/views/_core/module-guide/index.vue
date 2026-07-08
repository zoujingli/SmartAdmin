<script setup lang="ts">
import type { ModuleGuideEntry } from '#/plugins/module-guide-provider';

import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { IconifyIcon } from '@vben/icons';
import { preferences } from '@vben/preferences';

import { Empty, Skeleton, message } from 'ant-design-vue';

import { getAuthLoginPath } from '#/api';
import { getModuleGuideProvider } from '#/plugins/module-guide-provider';

import { moduleGuideHomeTarget } from './entry-targets';

const router = useRouter();
const loading = ref(false);
const entries = ref<ModuleGuideEntry[]>([]);
const guideAppName = ref('');
const appDescription = ref('');
const appName = computed(() => guideAppName.value || preferences.app.name || 'SmartAdmin');
// 数字雨使用独立短粒子，避免长文本列或整层纹理移动造成“整块背景下落”的观感。
const binaryRainDropSeeds = [
  ['0', '2%', '18px', '-7.6s', '13.2s', '12px', '0.28'],
  ['1', '4.5%', '-12px', '-2.4s', '9.4s', '18px', '0.2'],
  ['01', '6.8%', '24px', '-10.2s', '15.6s', '9px', '0.24'],
  ['1', '9.4%', '-20px', '-5.1s', '11.3s', '14px', '0.22'],
  ['10', '12.2%', '12px', '-13.8s', '16.8s', '21px', '0.18'],
  ['0', '15%', '-26px', '-1.8s', '8.8s', '11px', '0.3'],
  ['1', '17.4%', '16px', '-8.9s', '12.4s', '16px', '0.23'],
  ['01', '19.8%', '-10px', '-3.7s', '10.2s', '8px', '0.22'],
  ['0', '22.5%', '28px', '-12.5s', '14.6s', '13px', '0.27'],
  ['10', '25.4%', '-16px', '-6.6s', '11.9s', '19px', '0.18'],
  ['1', '28%', '22px', '-4.3s', '9.8s', '15px', '0.26'],
  ['0', '30.6%', '-28px', '-15.1s', '17.2s', '10px', '0.21'],
  ['01', '33.2%', '14px', '-9.7s', '13.7s', '22px', '0.17'],
  ['1', '36%', '-22px', '-12.8s', '18.4s', '11px', '0.2'],
  ['0', '38.5%', '16px', '-6.1s', '15.1s', '17px', '0.19'],
  ['10', '41.2%', '-24px', '-11.6s', '19.2s', '9px', '0.18'],
  ['1', '43.8%', '20px', '-4.9s', '16.5s', '13px', '0.2'],
  ['0', '46.5%', '10px', '-14.4s', '10.8s', '20px', '0.17'],
  ['01', '49%', '-18px', '-8.2s', '14.2s', '8px', '0.18'],
  ['1', '51.7%', '26px', '-0.9s', '12.8s', '16px', '0.22'],
  ['0', '54.3%', '-12px', '-16.6s', '18.8s', '10px', '0.19'],
  ['10', '57%', '19px', '-7.1s', '11.6s', '18px', '0.18'],
  ['1', '59.5%', '-25px', '-3.2s', '15.8s', '12px', '0.21'],
  ['0', '62.3%', '13px', '-10.9s', '9.9s', '21px', '0.18'],
  ['01', '65%', '-18px', '-5.9s', '13.4s', '9px', '0.22'],
  ['1', '67.6%', '30px', '-12.1s', '16.2s', '14px', '0.25'],
  ['0', '70.1%', '-14px', '-2.8s', '10.6s', '18px', '0.2'],
  ['10', '72.8%', '21px', '-15.4s', '18.1s', '8px', '0.18'],
  ['1', '75.5%', '-30px', '-7.9s', '14.8s', '13px', '0.24'],
  ['0', '78.1%', '15px', '-1.4s', '8.9s', '22px', '0.17'],
  ['01', '80.7%', '-21px', '-9.2s', '12.7s', '10px', '0.2'],
  ['1', '83.4%', '24px', '-4.6s', '15.4s', '15px', '0.23'],
  ['0', '86%', '-16px', '-13.1s', '17.6s', '19px', '0.18'],
  ['10', '88.6%', '11px', '-6.8s', '11.1s', '8px', '0.22'],
  ['1', '91.1%', '-27px', '-10.6s', '14.9s', '14px', '0.25'],
  ['0', '93.7%', '18px', '-3.5s', '10.1s', '20px', '0.18'],
  ['01', '96.2%', '-12px', '-12.4s', '16.9s', '9px', '0.2'],
  ['1', '98%', '22px', '-5.6s', '12.2s', '12px', '0.26'],
  ['0', '5.8%', '-24px', '-14.9s', '18.6s', '9px', '0.17'],
  ['10', '13.6%', '18px', '-8.4s', '13.1s', '15px', '0.21'],
  ['1', '21.4%', '-16px', '-0.6s', '9.2s', '22px', '0.16'],
  ['0', '27.2%', '25px', '-11.3s', '17.4s', '10px', '0.19'],
  ['01', '34.4%', '-20px', '-6.4s', '12.9s', '17px', '0.18'],
  ['1', '42.6%', '14px', '-15.8s', '19.4s', '8px', '0.2'],
  ['0', '50.8%', '-29px', '-4.1s', '10.7s', '19px', '0.17'],
  ['10', '58.8%', '23px', '-9.9s', '15.2s', '11px', '0.22'],
  ['1', '66.9%', '-15px', '-13.6s', '18.9s', '16px', '0.19'],
  ['0', '74.3%', '27px', '-2.1s', '9.7s', '21px', '0.16'],
  ['01', '82.6%', '-23px', '-7.3s', '13.8s', '9px', '0.21'],
  ['1', '90.4%', '17px', '-16.1s', '17.9s', '18px', '0.18'],
  ['0', '95%', '-19px', '-5.4s', '11.8s', '13px', '0.23'],
] as const;
const binaryRainLayers = [
  { alpha: 0.08, delay: 0, drift: 0, duration: 0.72, size: 1.36, x: 0 },
  { alpha: 0.02, delay: 4.6, drift: 15, duration: 1.08, size: 1.12, x: 1.35 },
  { alpha: -0.02, delay: 9.8, drift: -18, duration: 1.48, size: 0.94, x: 2.7 },
] as const;
const binaryRainDrops = binaryRainDropSeeds.flatMap(
  ([text, x, drift, delay, duration, size, alpha], seedIndex) =>
    binaryRainLayers.flatMap((layer, layerIndex) => {
      if (layerIndex === 2 && seedIndex % 2 === 1) {
        return [];
      }

      const alphaValue = Number.parseFloat(alpha);
      const delayValue = Number.parseFloat(delay);
      const driftValue = Number.parseFloat(drift);
      const durationValue = Number.parseFloat(duration);
      const sizeValue = Number.parseFloat(size);
      const xValue = Number.parseFloat(x);

      return [...text].map((digit, digitIndex) => {
        const speedJitter = 1 + ((seedIndex + digitIndex) % 5) * 0.055;
        const layerDelay = delayValue - layer.delay - digitIndex * 1.2 - (seedIndex % 4) * 0.32;
        const layerDrift = driftValue + layer.drift + (digitIndex === 0 ? 0 : seedIndex % 2 === 0 ? 12 : -12);
        const layerDuration = Math.max(5.8, durationValue * layer.duration * speedJitter);
        const layerSize = Math.round(sizeValue * layer.size + 8);
        const layerX = Math.min(xValue + layer.x + digitIndex * 1.9 + (seedIndex % 3) * 0.18, 98.8);

        return {
          text: digit,
          style: {
            '--alpha': `${Math.min(Math.max(alphaValue + layer.alpha, 0.14), 0.4)}`,
            '--delay': `${layerDelay.toFixed(2)}s`,
            '--drift': `${layerDrift}px`,
            '--duration': `${layerDuration.toFixed(2)}s`,
            '--size': `${layerSize}px`,
            '--x': `${layerX.toFixed(2)}%`,
          },
        };
      });
    }),
);

function normalizeGuideEntries(source: ModuleGuideEntry[]) {
  const merged = new Map<string, ModuleGuideEntry>();
  for (const entry of source) {
    const code = String(entry.code || '').trim().toLowerCase();
    if (code === '') {
      continue;
    }

    merged.set(code, { ...entry, code });
  }

  return [...merged.values()].sort((left, right) => {
    const sortCompare = Number(right.sort || 0) - Number(left.sort || 0);
    return sortCompare === 0 ? left.code.localeCompare(right.code) : sortCompare;
  });
}

async function loadGuide() {
  loading.value = true;
  try {
    const provider = getModuleGuideProvider();
    if (!provider) {
      entries.value = [];
      return;
    }
    const guide = await provider();

    const guideEntries = normalizeGuideEntries(Array.isArray(guide.entries) ? guide.entries : []);
    if (!guide.enabled || guideEntries.length === 0) {
      await router.replace(getAuthLoginPath());
      return;
    }

    guideAppName.value = guide.app?.name || '';
    appDescription.value = guide.app?.description || '';
    entries.value = guideEntries;
  } catch (error) {
    console.error('load module guide failed', error);
    message.error('加载系统引导页失败');
  } finally {
    loading.value = false;
  }
}

function entryHomeTarget(entry: ModuleGuideEntry) {
  return moduleGuideHomeTarget(entry, getAuthLoginPath());
}

function openEntry(entry: ModuleGuideEntry) {
  const targetPath = entryHomeTarget(entry);
  router.push(targetPath).catch((error) => {
    console.error('open module entry failed', error);
  });
}

onMounted(() => {
  loadGuide();
});
</script>

<template>
  <div class="module-guide">
    <div class="module-guide__backdrop" aria-hidden="true">
      <span class="module-guide__aurora"></span>
      <span class="module-guide__flow module-guide__flow--one"></span>
      <span class="module-guide__flow module-guide__flow--two"></span>
      <span class="module-guide__stars module-guide__stars--near"></span>
      <span class="module-guide__stars module-guide__stars--far"></span>
      <span
        v-for="(drop, index) in binaryRainDrops"
        :key="`${drop.style['--x']}-${index}`"
        class="module-guide__binary-drop"
        :class="{
          'module-guide__binary-drop--mobile-hidden': index > 95,
          'module-guide__binary-drop--soft': index % 4 === 1 || index % 4 === 2,
        }"
        :style="drop.style"
      >
        {{ drop.text }}
      </span>
      <span class="module-guide__beam module-guide__beam--one"></span>
      <span class="module-guide__beam module-guide__beam--two"></span>
      <span class="module-guide__scan"></span>
    </div>

    <main class="module-guide__main">
      <header class="module-guide__topbar">
        <div class="module-guide__brand-mini">
          <span class="module-guide__brand-mark">
            <IconifyIcon icon="lucide:layout-dashboard" />
          </span>
          <span>系统入口</span>
        </div>
      </header>

      <section class="module-guide__hero">
        <div class="module-guide__hero-copy">
          <div class="module-guide__hero-text">
            <div class="module-guide__eyebrow">
              <span class="module-guide__eyebrow-dot" />
              系统引导
            </div>
            <h1 class="module-guide__name">{{ appName }}</h1>
            <p class="module-guide__desc">
              {{ appDescription || '请选择要进入的业务系统。' }}
            </p>
          </div>

          <div class="module-guide__hero-status" aria-label="系统状态">
            <div class="module-guide__status-chip">
              <span class="module-guide__status-value">{{ entries.length }}</span>
              <span class="module-guide__status-label">可用入口</span>
            </div>
            <div class="module-guide__status-chip module-guide__status-chip--ready">
              <span class="module-guide__status-label">统一入口</span>
              <strong>已就绪</strong>
            </div>
          </div>
        </div>
      </section>

      <section class="module-guide__section">
        <div class="module-guide__section-head">
          <div>
            <div class="module-guide__section-title">业务入口</div>
            <div class="module-guide__section-desc">可用业务系统</div>
          </div>
          <div class="module-guide__count">{{ entries.length }} 个系统</div>
        </div>

        <Skeleton v-if="loading && entries.length === 0" active />
        <Empty
          v-else-if="entries.length === 0"
          class="module-guide__empty"
          description="暂无可用系统入口"
        />
        <section v-else class="module-guide__grid">
          <article
            v-for="entry in entries"
            :key="entry.code"
            class="module-guide__card"
            :class="`module-guide__card--${entry.code}`"
          >
            <span class="module-guide__card-glow" aria-hidden="true"></span>
            <div class="module-guide__card-body">
              <div class="module-guide__card-top">
                <div class="module-guide__icon" aria-hidden="true">
                  <IconifyIcon :icon="entry.icon || 'lucide:blocks'" />
                </div>
                <div class="module-guide__status">
                  <span class="module-guide__status-dot" />
                  可用
                </div>
              </div>
              <div class="module-guide__content">
                <div class="module-guide__card-head">
                  <div class="module-guide__title">{{ entry.name }}</div>
                  <IconifyIcon class="module-guide__arrow" icon="lucide:arrow-up-right" />
                </div>
                <div class="module-guide__summary">{{ entry.description }}</div>
                <div class="module-guide__card-foot">
                  <span class="module-guide__entry-code">{{ entry.code }}</span>
                  <div class="module-guide__actions">
                    <button type="button" class="module-guide__enter" @click="openEntry(entry)">
                      立即登录
                      <IconifyIcon icon="lucide:chevron-right" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </section>
      </section>
    </main>
  </div>
</template>

<style scoped>
.module-guide {
  --guide-bg: var(--ant-colorBgLayout, hsl(var(--background)));
  --guide-panel: var(--ant-colorBgContainer, hsl(var(--card)));
  --guide-panel-muted: var(--ant-colorFillQuaternary, hsl(var(--muted) / 0.52));
  --guide-text: var(--ant-colorText, hsl(var(--foreground)));
  --guide-text-soft: var(--ant-colorTextSecondary, hsl(var(--muted-foreground)));
  --guide-text-muted: var(--ant-colorTextTertiary, hsl(var(--muted-foreground)));
  --guide-line: var(--ant-colorBorderSecondary, hsl(var(--border)));
  --guide-line-strong: var(--ant-colorBorder, hsl(var(--border)));
  --guide-primary: var(--ant-colorPrimary, hsl(var(--primary)));
  --guide-primary-bg: var(--ant-colorPrimaryBg, hsl(var(--primary) / 0.12));
  --guide-primary-hover: var(--ant-colorPrimaryHover, hsl(var(--primary)));
  --guide-solid-text: var(--ant-colorTextLightSolid, hsl(var(--primary-foreground)));
  --guide-success: var(--ant-colorSuccess, hsl(var(--success)));
  --guide-success-bg: var(--ant-colorSuccessBg, hsl(var(--success) / 0.12));
  --guide-warning: var(--ant-colorWarning, hsl(var(--warning)));
  --guide-warning-bg: var(--ant-colorWarningBg, hsl(var(--warning) / 0.14));
  --guide-info: var(--ant-colorInfo, var(--guide-primary));
  --guide-info-bg: var(--ant-colorInfoBg, var(--guide-primary-bg));

  min-height: 100vh;
  padding: 16px 24px 32px;
  overflow: hidden;
  background: var(--guide-bg);
  color: var(--guide-text);
}

.module-guide__backdrop {
  display: none;
}

.module-guide__main {
  position: relative;
  width: min(1280px, 100%);
  margin: 0 auto;
}

.module-guide__topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 30px;
  margin-bottom: 12px;
}

.module-guide__brand-mini {
  display: inline-flex;
  gap: 9px;
  align-items: center;
  color: var(--guide-text-soft);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0;
}

.module-guide__brand-mark {
  display: flex;
  width: 30px;
  height: 30px;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--guide-line-strong);
  border-radius: 8px;
  color: var(--guide-primary);
  background: var(--guide-primary-bg);
  font-size: 17px;
}

.module-guide__hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  margin-bottom: 18px;
}

.module-guide__hero-copy {
  display: flex;
  min-height: 112px;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 18px 22px 18px 24px;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  background: var(--guide-panel);
}

.module-guide__hero-text {
  min-width: 0;
}

.module-guide__eyebrow {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
  color: var(--guide-primary);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0;
  line-height: 1.2;
}

.module-guide__eyebrow-dot {
  width: 7px;
  height: 7px;
  border-radius: 8px;
  background: var(--guide-primary);
}

.module-guide__name {
  max-width: 820px;
  margin: 0;
  color: var(--guide-text);
  font-size: 26px;
  font-weight: 850;
  line-height: 1.18;
}

.module-guide__desc {
  max-width: 760px;
  margin: 8px 0 0;
  color: var(--guide-text-soft);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.65;
}

.module-guide__hero-status {
  display: inline-flex;
  flex: 0 0 auto;
  gap: 10px;
  align-items: center;
  justify-content: flex-end;
}

.module-guide__status-chip {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  min-height: 34px;
  padding: 0 12px;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  color: var(--guide-text-muted);
  background: var(--guide-panel-muted);
  font-size: 11px;
  font-weight: 820;
  white-space: nowrap;
}

.module-guide__status-value {
  color: var(--guide-text);
  font-size: 18px;
  font-weight: 900;
  line-height: 1;
}

.module-guide__status-label {
  letter-spacing: 0;
}

.module-guide__status-chip--ready {
  border-color: var(--guide-success);
  background: var(--guide-success-bg);
}

.module-guide__status-chip strong {
  color: var(--guide-success);
  font-weight: 850;
}

.module-guide__section {
  position: relative;
}

.module-guide__section-head {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
}

.module-guide__section-title {
  color: var(--guide-text);
  font-size: 18px;
  font-weight: 850;
  line-height: 1.4;
}

.module-guide__section-desc {
  margin-top: 4px;
  color: var(--guide-text-muted);
  font-size: 14px;
  font-weight: 650;
  line-height: 1.6;
}

.module-guide__count {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  min-height: 34px;
  padding: 0 13px;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  color: var(--guide-text-soft);
  background: var(--guide-panel);
  font-size: 13px;
  font-weight: 800;
}

.module-guide__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.module-guide__card {
  --guide-accent: var(--guide-primary);
  --guide-accent-bg: var(--guide-primary-bg);

  position: relative;
  display: block;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  overflow: hidden;
  color: inherit;
  text-align: left;
  background: var(--guide-panel);
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    transform 0.18s ease;
}

.module-guide__card--website,
.module-guide__card--points {
  --guide-accent: var(--guide-success);
  --guide-accent-bg: var(--guide-success-bg);
}

.module-guide__card--material,
.module-guide__card--license {
  --guide-accent: var(--guide-warning);
  --guide-accent-bg: var(--guide-warning-bg);
}

.module-guide__card--asset {
  --guide-accent: var(--guide-info);
  --guide-accent-bg: var(--guide-info-bg);
}

.module-guide__card--system,
.module-guide__card--default {
  --guide-accent: var(--guide-text-muted);
  --guide-accent-bg: var(--guide-panel-muted);
}

.module-guide__card::before {
  position: absolute;
  inset: 0 auto 0 0;
  width: 3px;
  content: "";
  background: var(--guide-accent);
}

.module-guide__card-glow {
  display: none;
}

.module-guide__card:hover {
  border-color: var(--guide-accent);
  background: var(--guide-accent-bg);
  transform: translateY(-2px);
}

.module-guide__card-body {
  position: relative;
  display: flex;
  height: 100%;
  min-height: 218px;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
}

.module-guide__card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.module-guide__icon {
  display: flex;
  width: 56px;
  height: 56px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  color: var(--guide-accent);
  background: var(--guide-accent-bg);
  font-size: 28px;
}

.module-guide__status {
  display: inline-flex;
  gap: 7px;
  align-items: center;
  padding: 6px 9px;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  color: var(--guide-text-soft);
  background: var(--guide-panel-muted);
  font-size: 11px;
  font-weight: 850;
  line-height: 1;
}

.module-guide__status-dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: var(--guide-accent);
}

.module-guide__content {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
}

.module-guide__card-head {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
}

.module-guide__title {
  color: var(--guide-text);
  font-size: 21px;
  font-weight: 850;
  line-height: 1.4;
}

.module-guide__summary {
  min-height: 58px;
  margin-top: 10px;
  color: var(--guide-text-soft);
  font-size: 13px;
  font-weight: 600;
  line-height: 1.7;
}

.module-guide__card-foot {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 13px;
  border-top: 1px solid var(--guide-line);
}

.module-guide__entry-code {
  overflow: hidden;
  color: var(--guide-text-muted);
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.module-guide__actions {
  display: inline-flex;
  flex: 0 0 auto;
  gap: 8px;
  align-items: center;
  justify-content: flex-end;
}

.module-guide__enter {
  display: inline-flex;
  flex: 0 0 auto;
  gap: 4px;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 0 13px;
  border: 1px solid var(--guide-primary);
  border-radius: 8px;
  color: var(--guide-solid-text);
  background: var(--guide-primary);
  font-size: 13px;
  font-weight: 850;
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    gap 0.18s ease;
}

.module-guide__card:hover .module-guide__enter {
  gap: 8px;
}

.module-guide__enter:hover {
  border-color: var(--guide-primary-hover);
  background: var(--guide-primary-hover);
}

.module-guide__enter:focus-visible {
  outline: 2px solid var(--guide-primary);
  outline-offset: 3px;
}

.module-guide__arrow {
  flex: 0 0 auto;
  color: var(--guide-accent);
  font-size: 20px;
  opacity: 0.6;
  transition:
    opacity 0.18s ease,
    transform 0.18s ease;
}

.module-guide__card:hover .module-guide__arrow {
  opacity: 1;
  transform: translate(2px, -2px);
}

.module-guide__empty {
  padding: 80px 0;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  background: var(--guide-panel);
}

@media (max-width: 1024px) {
  .module-guide__hero {
    grid-template-columns: 1fr;
  }

  .module-guide__hero-copy {
    align-items: flex-start;
    flex-direction: column;
    gap: 14px;
    min-height: 98px;
    padding-right: 24px;
  }

  .module-guide__hero-status {
    justify-content: flex-start;
  }

  .module-guide__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .module-guide {
    padding: 8px 10px 22px;
  }

  .module-guide__topbar {
    justify-content: center;
    min-height: 26px;
    margin-bottom: 6px;
  }

  .module-guide__brand-mini {
    justify-content: center;
  }

  .module-guide__hero {
    gap: 0;
    margin-bottom: 12px;
  }

  .module-guide__hero-copy {
    gap: 12px;
    min-height: 88px;
    padding: 14px 16px;
  }

  .module-guide__name {
    font-size: 22px;
  }

  .module-guide__desc {
    display: none;
  }

  .module-guide__hero-status {
    flex-wrap: wrap;
    width: 100%;
  }

  .module-guide__status-chip {
    flex: 1 1 140px;
    justify-content: center;
    min-height: 30px;
    padding: 0 10px;
    font-size: 10px;
  }

  .module-guide__section-head {
    gap: 8px;
    flex-direction: row;
    align-items: center;
    margin-bottom: 8px;
  }

  .module-guide__section-desc {
    display: none;
  }

  .module-guide__count {
    min-height: 28px;
    padding: 0 10px;
    font-size: 12px;
  }

  .module-guide__grid {
    grid-template-columns: 1fr;
  }

  .module-guide__card-body {
    min-height: 204px;
    gap: 14px;
    padding: 18px;
  }

  .module-guide__card-foot {
    align-items: stretch;
    flex-direction: column;
  }

  .module-guide__actions {
    width: 100%;
    justify-content: space-between;
  }

  .module-guide__enter {
    flex: 1 1 0;
    justify-content: center;
  }

  .module-guide__icon {
    width: 48px;
    height: 48px;
    font-size: 24px;
  }

  .module-guide__title {
    font-size: 20px;
  }
}
</style>
