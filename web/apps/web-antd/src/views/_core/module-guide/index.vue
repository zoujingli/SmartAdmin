<script setup lang="ts">
import type { DataApi } from '#/api';

import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { IconifyIcon } from '@vben/icons';
import { preferences } from '@vben/preferences';

import { Button, Empty, Skeleton, message } from 'ant-design-vue';

import { dataApiService } from '#/api';

const router = useRouter();
const loading = ref(false);
const entries = ref<DataApi.ModuleGuideEntry[]>([]);
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

function normalizePath(value: string) {
  const path = `/${String(value || '').trim().replace(/^\/+/, '')}`;
  return path === '/' ? '/' : path.replace(/\/+$/, '');
}

async function loadGuide() {
  loading.value = true;
  try {
    const guide = await dataApiService.getModuleGuide();

    const guideEntries = Array.isArray(guide.entries) ? guide.entries : [];
    if (!guide.enabled || guideEntries.length === 0) {
      await router.replace('/auth/login');
      return;
    }

    guideAppName.value = guide.app?.name || '';
    appDescription.value = guide.app?.description || '';
    entries.value = guideEntries;
  } catch (error) {
    console.error('load module guide failed', error);
    message.error('加载模块引导页失败');
  } finally {
    loading.value = false;
  }
}

function openEntry(entry: DataApi.ModuleGuideEntry) {
  const target = normalizePath(entry.home_path || entry.login_path || '/auth/login');
  router.push(target).catch((error) => {
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
          <span>MODULE GATEWAY</span>
        </div>
        <Button class="module-guide__login" size="large" @click="router.push('/auth/login')">
          <IconifyIcon icon="lucide:shield-check" />
          系统后台
        </Button>
      </header>

      <section class="module-guide__hero">
        <div class="module-guide__hero-copy">
          <div class="module-guide__eyebrow">
            <span class="module-guide__eyebrow-dot" />
            模块引导
          </div>
          <h1 class="module-guide__name">{{ appName }}</h1>
          <p class="module-guide__desc">
            {{ appDescription || '请选择要进入的业务模块。' }}
          </p>
        </div>

        <aside class="module-guide__console" aria-label="模块状态">
          <div class="module-guide__console-head">
            <span class="module-guide__console-dot"></span>
            <span class="module-guide__console-dot"></span>
            <span class="module-guide__console-dot"></span>
            <span class="module-guide__console-title">ACCESS NODE</span>
          </div>
          <div class="module-guide__console-body">
            <div class="module-guide__metric">
              <span class="module-guide__metric-value">{{ entries.length }}</span>
              <span class="module-guide__metric-label">ACTIVE MODULES</span>
            </div>
            <div class="module-guide__pulse-line"></div>
            <div class="module-guide__console-row">
              <span>PUBLIC ENTRY</span>
              <strong>READY</strong>
            </div>
          </div>
        </aside>
      </section>

      <section class="module-guide__section">
        <div class="module-guide__section-head">
          <div>
            <div class="module-guide__section-title">业务入口</div>
            <div class="module-guide__section-desc">独立插件子系统</div>
          </div>
          <div class="module-guide__count">{{ entries.length }} 个模块</div>
        </div>

        <Skeleton v-if="loading && entries.length === 0" active />
        <Empty
          v-else-if="entries.length === 0"
          class="module-guide__empty"
          description="暂无可用模块入口"
        />
        <section v-else class="module-guide__grid">
          <button
            v-for="entry in entries"
            :key="entry.code"
            type="button"
            class="module-guide__card"
            @click="openEntry(entry)"
          >
            <span class="module-guide__card-glow" aria-hidden="true"></span>
            <div class="module-guide__card-body">
              <div class="module-guide__card-top">
                <div class="module-guide__icon" aria-hidden="true">
                  <IconifyIcon :icon="entry.icon || 'lucide:blocks'" />
                </div>
                <div class="module-guide__status">
                  <span class="module-guide__status-dot" />
                  ONLINE
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
                  <span class="module-guide__enter">
                    进入模块
                    <IconifyIcon icon="lucide:chevron-right" />
                  </span>
                </div>
              </div>
            </div>
          </button>
        </section>
      </section>
    </main>
  </div>
</template>

<style scoped>
.module-guide {
  --guide-void: #040711;
  --guide-base: #080d18;
  --guide-panel: rgb(11 18 32 / 78%);
  --guide-panel-strong: rgb(15 24 42 / 92%);
  --guide-text: #f8fbff;
  --guide-text-soft: rgb(226 238 255 / 84%);
  --guide-text-muted: rgb(184 199 225 / 68%);
  --guide-line: rgb(124 205 255 / 18%);
  --guide-line-strong: rgb(146 220 255 / 36%);
  --guide-primary: #38bdf8;
  --guide-success: #2dd4bf;
  --guide-info: #a3e635;
  --guide-warm: #f59e0b;

  position: relative;
  min-height: 100vh;
  padding: 34px 24px 58px;
  overflow: hidden;
  background:
    radial-gradient(circle at 18% 14%, rgb(56 189 248 / 30%), transparent 30%),
    radial-gradient(circle at 82% 16%, rgb(45 212 191 / 18%), transparent 30%),
    radial-gradient(circle at 48% 88%, rgb(245 158 11 / 10%), transparent 30%),
    linear-gradient(145deg, var(--guide-void) 0%, var(--guide-base) 46%, #0d111f 100%);
  color: var(--guide-text);
  isolation: isolate;
}

.module-guide::before {
  position: absolute;
  inset: 0;
  pointer-events: none;
  content: "";
  background-image:
    linear-gradient(rgb(90 196 255 / 8%) 1px, transparent 1px),
    linear-gradient(90deg, rgb(90 196 255 / 8%) 1px, transparent 1px);
  background-position: center top;
  background-size: 42px 42px;
  mask-image:
    linear-gradient(180deg, rgb(0 0 0 / 82%), rgb(0 0 0 / 42%) 48%, transparent 100%);
}

.module-guide::after {
  position: absolute;
  inset: 0;
  pointer-events: none;
  content: "";
  background:
    linear-gradient(90deg, rgb(255 255 255 / 3%), transparent 18%, transparent 82%, rgb(255 255 255 / 3%)),
    repeating-linear-gradient(0deg, transparent 0 9px, rgb(255 255 255 / 2%) 10px);
  mix-blend-mode: screen;
  opacity: 0.34;
}

/* 动态背景只作为公开引导页装饰层，避免参与布局和交互，移动端通过媒体查询降噪。 */
.module-guide__backdrop {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}

.module-guide__aurora {
  position: absolute;
  inset: -38%;
  background:
    conic-gradient(from 35deg at 52% 50%, transparent 0deg, rgb(56 189 248 / 28%) 58deg, transparent 126deg, rgb(45 212 191 / 18%) 194deg, transparent 268deg, rgb(163 230 53 / 12%) 316deg, transparent 360deg),
    radial-gradient(circle at 30% 46%, rgb(56 189 248 / 18%), transparent 36%),
    radial-gradient(circle at 72% 44%, rgb(45 212 191 / 14%), transparent 38%);
  filter: blur(34px);
  mix-blend-mode: screen;
  opacity: 0.76;
  transform-origin: center;
  animation: guide-aurora-pulse 12s ease-in-out infinite;
  will-change: opacity;
}

.module-guide__flow {
  position: absolute;
  width: 62vw;
  height: 62vw;
  min-width: 520px;
  min-height: 520px;
  border: 1px solid rgb(56 189 248 / 12%);
  border-radius: 50%;
  background:
    radial-gradient(circle, rgb(56 189 248 / 14%), transparent 58%),
    conic-gradient(from 90deg, transparent, rgb(56 189 248 / 18%), transparent, rgb(45 212 191 / 16%), transparent);
  filter: blur(0.2px);
  opacity: 0.62;
  transform-origin: center;
  animation: guide-ring-pulse 10s ease-in-out infinite;
  will-change: opacity;
}

.module-guide__flow--one {
  top: -24vw;
  right: -20vw;
}

.module-guide__flow--two {
  bottom: -28vw;
  left: -22vw;
  border-color: rgb(45 212 191 / 10%);
  background:
    radial-gradient(circle, rgb(45 212 191 / 12%), transparent 60%),
    conic-gradient(from 210deg, transparent, rgb(45 212 191 / 16%), transparent, rgb(245 158 11 / 10%), transparent);
  animation-delay: -8s;
  animation-direction: alternate-reverse;
}

.module-guide__stars {
  position: absolute;
  inset: -20%;
  background-repeat: repeat;
  mix-blend-mode: screen;
  opacity: 0.52;
  transform: translate3d(0, 0, 0);
  will-change: opacity;
}

.module-guide__stars--near {
  background-image:
    radial-gradient(circle, rgb(255 255 255 / 82%) 0 1px, transparent 1.5px),
    radial-gradient(circle, rgb(56 189 248 / 64%) 0 1px, transparent 1.5px),
    radial-gradient(circle, rgb(45 212 191 / 58%) 0 1px, transparent 1.5px);
  background-position:
    0 0,
    42px 68px,
    96px 24px;
  background-size:
    136px 136px,
    184px 184px,
    220px 220px;
  animation: guide-stars-pulse 7s ease-in-out infinite;
}

.module-guide__stars--far {
  background-image:
    radial-gradient(circle, rgb(146 220 255 / 54%) 0 1px, transparent 1.5px),
    radial-gradient(circle, rgb(255 255 255 / 34%) 0 1px, transparent 1.5px);
  background-position:
    18px 42px,
    78px 18px;
  background-size:
    240px 240px,
    320px 320px;
  opacity: 0.38;
}

.module-guide__binary-drop {
  position: absolute;
  color: rgb(146 220 255 / 46%);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  line-height: 2.2;
  text-shadow:
    0 0 12px rgb(56 189 248 / 42%),
    0 0 28px rgb(45 212 191 / 24%);
  white-space: pre-line;
  mask-image: linear-gradient(90deg, transparent, rgb(0 0 0 / 74%) 18%, rgb(0 0 0 / 62%) 72%, transparent);
  mix-blend-mode: screen;
  opacity: 0.34;
  will-change: transform, opacity;
}

.module-guide__binary-drop {
  top: -10vh;
  left: var(--x);
  display: block;
  min-width: 1ch;
  color: rgb(146 220 255 / 52%);
  font-size: var(--size);
  line-height: 1.16;
  text-align: center;
  white-space: nowrap;
  opacity: 0;
  mask-image: none;
  animation: guide-binary-drop var(--duration) linear infinite;
  animation-delay: var(--delay);
  will-change: transform, opacity;
}

.module-guide__binary-drop--soft {
  color: rgb(45 212 191 / 34%);
}

.module-guide__beam {
  position: absolute;
  width: 620px;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgb(56 189 248 / 72%), transparent);
  filter: drop-shadow(0 0 18px rgb(56 189 248 / 48%));
  transform: rotate(-22deg);
  animation: guide-beam-sweep 9s ease-in-out infinite;
  will-change: transform, opacity;
}

.module-guide__beam--one {
  top: 148px;
  right: -120px;
  animation-delay: -2s;
}

.module-guide__beam--two {
  bottom: 178px;
  left: -150px;
  background: linear-gradient(90deg, transparent, rgb(45 212 191 / 62%), transparent);
  filter: drop-shadow(0 0 18px rgb(45 212 191 / 42%));
  animation-delay: -6s;
  animation-direction: alternate-reverse;
}

.module-guide__scan {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(90deg, transparent 0%, rgb(56 189 248 / 5%) 48%, transparent 72%),
    repeating-linear-gradient(180deg, transparent 0 34px, rgb(56 189 248 / 3%) 35px, transparent 36px);
  opacity: 0.24;
}

.module-guide__main {
  position: relative;
  z-index: 1;
  width: min(1280px, 100%);
  margin: 0 auto;
}

.module-guide__topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 58px;
  margin-bottom: 42px;
}

.module-guide__brand-mini {
  display: inline-flex;
  gap: 12px;
  align-items: center;
  color: var(--guide-text-soft);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0;
}

.module-guide__brand-mark {
  display: flex;
  width: 36px;
  height: 36px;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--guide-line-strong);
  border-radius: 8px;
  color: var(--guide-primary);
  background:
    linear-gradient(135deg, rgb(56 189 248 / 24%), rgb(45 212 191 / 10%)),
    rgb(255 255 255 / 4%);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 14%),
    0 0 28px rgb(56 189 248 / 16%);
  font-size: 20px;
}

.module-guide__login {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  border-color: var(--guide-line-strong);
  border-radius: 8px;
  color: var(--guide-text);
  background:
    linear-gradient(135deg, rgb(255 255 255 / 10%), rgb(255 255 255 / 4%)),
    rgb(9 15 27 / 72%);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 12%),
    0 14px 40px rgb(0 0 0 / 24%);
  font-weight: 700;
}

.module-guide__login:hover,
.module-guide__login:focus {
  border-color: rgb(56 189 248 / 70%);
  color: #fff;
  background:
    linear-gradient(135deg, rgb(56 189 248 / 20%), rgb(45 212 191 / 10%)),
    rgb(9 15 27 / 78%);
}

.module-guide__hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 28px;
  align-items: stretch;
  margin-bottom: 42px;
}

.module-guide__hero-copy {
  position: relative;
  min-height: 260px;
  padding: 36px 38px;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  overflow: hidden;
  background:
    linear-gradient(120deg, rgb(56 189 248 / 14%), transparent 46%),
    linear-gradient(180deg, rgb(255 255 255 / 8%), rgb(255 255 255 / 3%)),
    var(--guide-panel);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 12%),
    0 28px 80px rgb(0 0 0 / 38%);
}

.module-guide__hero-copy::before {
  position: absolute;
  right: -90px;
  bottom: -110px;
  width: 380px;
  height: 380px;
  content: "";
  border: 1px solid rgb(56 189 248 / 18%);
  border-radius: 50%;
  background:
    radial-gradient(circle, rgb(56 189 248 / 20%), transparent 58%),
    conic-gradient(from 120deg, transparent, rgb(45 212 191 / 28%), transparent, rgb(56 189 248 / 16%), transparent);
  filter: blur(0.2px);
  opacity: 0.86;
}

.module-guide__hero-copy::after {
  position: absolute;
  inset: auto 30px 26px;
  height: 1px;
  content: "";
  background: linear-gradient(90deg, rgb(56 189 248 / 74%), transparent 72%);
  box-shadow: 0 0 18px rgb(56 189 248 / 46%);
}

.module-guide__eyebrow {
  position: relative;
  z-index: 1;
  display: inline-flex;
  gap: 9px;
  align-items: center;
  margin-bottom: 14px;
  color: rgb(129 231 255);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0;
}

.module-guide__eyebrow-dot {
  width: 8px;
  height: 8px;
  border-radius: 8px;
  background: var(--guide-info);
  box-shadow: 0 0 18px rgb(163 230 53 / 68%);
}

.module-guide__name {
  position: relative;
  z-index: 1;
  max-width: 820px;
  margin: 0;
  color: var(--guide-text);
  font-size: 48px;
  font-weight: 850;
  line-height: 1.08;
  text-shadow: 0 0 30px rgb(56 189 248 / 18%);
}

.module-guide__desc {
  position: relative;
  z-index: 1;
  max-width: 720px;
  margin: 18px 0 0;
  color: var(--guide-text-soft);
  font-size: 16px;
  font-weight: 600;
  line-height: 1.9;
}

.module-guide__console {
  display: flex;
  min-height: 260px;
  flex-direction: column;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  overflow: hidden;
  background:
    linear-gradient(145deg, rgb(45 212 191 / 14%), transparent 48%),
    linear-gradient(180deg, rgb(255 255 255 / 8%), rgb(255 255 255 / 3%)),
    rgb(8 14 26 / 82%);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 12%),
    0 28px 80px rgb(0 0 0 / 34%);
}

.module-guide__console-head {
  display: flex;
  gap: 8px;
  align-items: center;
  min-height: 42px;
  padding: 0 16px;
  border-bottom: 1px solid var(--guide-line);
  background: rgb(255 255 255 / 4%);
}

.module-guide__console-dot {
  width: 7px;
  height: 7px;
  border-radius: 8px;
  background: rgb(56 189 248 / 74%);
}

.module-guide__console-dot:nth-child(2) {
  background: rgb(45 212 191 / 78%);
}

.module-guide__console-dot:nth-child(3) {
  background: rgb(245 158 11 / 78%);
}

.module-guide__console-title {
  margin-left: auto;
  color: var(--guide-text-muted);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0;
}

.module-guide__console-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: space-between;
  padding: 24px;
}

.module-guide__metric {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.module-guide__metric-value {
  color: var(--guide-text);
  font-size: 72px;
  font-weight: 850;
  line-height: 0.95;
  text-shadow: 0 0 28px rgb(45 212 191 / 26%);
}

.module-guide__metric-label {
  color: var(--guide-text-muted);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0;
}

.module-guide__pulse-line {
  height: 46px;
  margin: 22px 0;
  background:
    linear-gradient(90deg, transparent, rgb(45 212 191 / 50%), transparent) center / 100% 1px no-repeat,
    linear-gradient(90deg, transparent 0 12%, rgb(45 212 191 / 46%) 12% 15%, transparent 15% 28%, rgb(56 189 248 / 70%) 28% 32%, transparent 32% 48%, rgb(163 230 53 / 62%) 48% 51%, transparent 51% 100%) center / 100% 100% no-repeat;
  filter: drop-shadow(0 0 12px rgb(45 212 191 / 26%));
}

.module-guide__console-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  border: 1px solid var(--guide-line);
  border-radius: 8px;
  color: var(--guide-text-muted);
  background: rgb(255 255 255 / 4%);
  font-size: 12px;
  font-weight: 750;
}

.module-guide__console-row strong {
  color: var(--guide-info);
  font-weight: 850;
}

.module-guide__section {
  position: relative;
}

.module-guide__section-head {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 18px;
}

.module-guide__section-title {
  color: var(--guide-text);
  font-size: 22px;
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
  background: rgb(255 255 255 / 5%);
  font-size: 13px;
  font-weight: 800;
}

.module-guide__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 22px;
}

.module-guide__card {
  --guide-accent: var(--guide-primary);

  position: relative;
  display: block;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 1px solid color-mix(in srgb, var(--guide-accent) 58%, rgb(255 255 255 / 14%));
  border-radius: 8px;
  overflow: hidden;
  color: inherit;
  text-align: left;
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--guide-accent) 24%, transparent), transparent 44%),
    linear-gradient(180deg, rgb(255 255 255 / 9%), rgb(255 255 255 / 4%)),
    var(--guide-panel-strong);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 14%),
    inset 0 0 0 1px rgb(255 255 255 / 4%),
    0 24px 70px rgb(0 0 0 / 34%),
    0 0 46px color-mix(in srgb, var(--guide-accent) 14%, transparent);
  cursor: pointer;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;
}

.module-guide__card:nth-child(3n + 2) {
  --guide-accent: var(--guide-success);
}

.module-guide__card:nth-child(3n) {
  --guide-accent: var(--guide-warm);
}

.module-guide__card::before {
  position: absolute;
  inset: 0 0 auto;
  height: 3px;
  content: "";
  background: linear-gradient(90deg, transparent, var(--guide-accent), transparent);
  box-shadow: 0 0 28px color-mix(in srgb, var(--guide-accent) 62%, transparent);
}

.module-guide__card::after {
  position: absolute;
  inset: 0;
  pointer-events: none;
  content: "";
  background-image:
    linear-gradient(90deg, color-mix(in srgb, var(--guide-accent) 16%, transparent) 1px, transparent 1px),
    linear-gradient(color-mix(in srgb, var(--guide-accent) 12%, transparent) 1px, transparent 1px);
  background-size: 28px 28px;
  mask-image: linear-gradient(180deg, rgb(0 0 0 / 45%), transparent 62%);
  opacity: 0.72;
}

.module-guide__card-glow {
  position: absolute;
  right: -82px;
  bottom: -88px;
  width: 210px;
  height: 210px;
  pointer-events: none;
  border-radius: 50%;
  background: radial-gradient(circle, color-mix(in srgb, var(--guide-accent) 34%, transparent), transparent 66%);
  opacity: 0.8;
  transition:
    opacity 0.18s ease,
    transform 0.18s ease;
}

.module-guide__card:hover {
  border-color: color-mix(in srgb, var(--guide-accent) 86%, white 8%);
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--guide-accent) 30%, transparent), transparent 42%),
    linear-gradient(180deg, rgb(255 255 255 / 12%), rgb(255 255 255 / 5%)),
    var(--guide-panel-strong);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 18%),
    0 30px 82px rgb(0 0 0 / 42%),
    0 0 0 1px color-mix(in srgb, var(--guide-accent) 18%, transparent),
    0 0 58px color-mix(in srgb, var(--guide-accent) 22%, transparent);
  transform: translateY(-6px);
}

.module-guide__card:hover .module-guide__card-glow {
  opacity: 1;
  transform: scale(1.08);
}

.module-guide__card:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--guide-accent) 72%, white);
  outline-offset: 4px;
}

.module-guide__card-body {
  position: relative;
  z-index: 1;
  display: flex;
  height: 100%;
  min-height: 282px;
  flex-direction: column;
  gap: 22px;
  padding: 26px;
}

.module-guide__card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.module-guide__icon {
  display: flex;
  width: 62px;
  height: 62px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--guide-accent) 62%, transparent);
  border-radius: 8px;
  color: var(--guide-accent);
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--guide-accent) 28%, transparent), rgb(255 255 255 / 4%)),
    rgb(255 255 255 / 5%);
  font-size: 30px;
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 14%),
    0 14px 34px color-mix(in srgb, var(--guide-accent) 22%, transparent);
}

.module-guide__status {
  display: inline-flex;
  gap: 7px;
  align-items: center;
  padding: 6px 9px;
  border: 1px solid color-mix(in srgb, var(--guide-accent) 42%, rgb(255 255 255 / 14%));
  border-radius: 8px;
  color: var(--guide-text-soft);
  background: rgb(255 255 255 / 6%);
  font-size: 11px;
  font-weight: 850;
  line-height: 1;
}

.module-guide__status-dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: var(--guide-accent);
  box-shadow: 0 0 12px color-mix(in srgb, var(--guide-accent) 64%, transparent);
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
  font-size: 22px;
  font-weight: 850;
  line-height: 1.4;
}

.module-guide__summary {
  min-height: 84px;
  margin-top: 12px;
  color: var(--guide-text-soft);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.7;
}

.module-guide__card-foot {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding: 13px 14px;
  border: 1px solid color-mix(in srgb, var(--guide-accent) 28%, rgb(255 255 255 / 12%));
  border-radius: 8px;
  background:
    linear-gradient(90deg, color-mix(in srgb, var(--guide-accent) 16%, transparent), transparent),
    rgb(255 255 255 / 5%);
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

.module-guide__enter {
  display: inline-flex;
  flex: 0 0 auto;
  gap: 4px;
  align-items: center;
  color: color-mix(in srgb, var(--guide-accent) 82%, white);
  font-size: 13px;
  font-weight: 850;
  transition:
    color 0.18s ease,
    gap 0.18s ease;
}

.module-guide__card:hover .module-guide__enter {
  gap: 8px;
  color: var(--guide-accent);
}

.module-guide__arrow {
  flex: 0 0 auto;
  color: var(--guide-accent);
  font-size: 20px;
  opacity: 0.5;
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

@keyframes guide-aurora-pulse {
  0% {
    opacity: 0.58;
  }

  50% {
    opacity: 0.82;
  }

  100% {
    opacity: 0.58;
  }
}

@keyframes guide-ring-pulse {
  0% {
    opacity: 0.46;
  }

  50% {
    opacity: 0.66;
  }

  100% {
    opacity: 0.46;
  }
}

@keyframes guide-stars-pulse {
  0% {
    opacity: 0.44;
  }

  50% {
    opacity: 0.68;
  }

  100% {
    opacity: 0.44;
  }
}

@keyframes guide-binary-drop {
  0% {
    opacity: 0;
    transform: translate3d(calc(var(--drift) * -0.3), -12vh, 0) scale(0.88);
  }

  12% {
    opacity: var(--alpha);
  }

  46% {
    opacity: calc(var(--alpha) * 0.9);
    transform: translate3d(calc(var(--drift) * 0.35), 48vh, 0) scale(1);
  }

  70% {
    opacity: calc(var(--alpha) * 0.64);
  }

  100% {
    opacity: 0;
    transform: translate3d(var(--drift), 118vh, 0) scale(0.96);
  }
}

@keyframes guide-beam-sweep {
  0% {
    opacity: 0;
    transform: translate3d(-16%, 0, 0) rotate(-22deg) scaleX(0.68);
  }

  18%,
  68% {
    opacity: 0.86;
  }

  100% {
    opacity: 0;
    transform: translate3d(22%, 0, 0) rotate(-22deg) scaleX(1.18);
  }
}

@media (max-width: 1024px) {
  .module-guide__hero {
    grid-template-columns: 1fr;
  }

  .module-guide__console {
    min-height: 190px;
  }

  .module-guide__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .module-guide {
    padding: 18px 14px 32px;
  }

  .module-guide::before {
    background-size: 34px 34px;
    opacity: 0.64;
  }

  .module-guide__aurora {
    inset: -48%;
    opacity: 0.48;
  }

  .module-guide__flow {
    min-width: 360px;
    min-height: 360px;
    opacity: 0.38;
  }

  .module-guide__stars {
    opacity: 0.26;
  }

  .module-guide__binary-drop {
    opacity: 0.18;
  }

  .module-guide__binary-drop {
    font-size: clamp(12px, var(--size), 24px);
    line-height: 1.1;
  }

  .module-guide__binary-drop--mobile-hidden {
    display: none;
  }

  .module-guide__beam {
    width: 420px;
    opacity: 0.58;
  }

  .module-guide__topbar {
    gap: 14px;
    align-items: stretch;
    flex-direction: column;
    margin-bottom: 22px;
  }

  .module-guide__brand-mini {
    justify-content: center;
  }

  .module-guide__login {
    justify-content: center;
    width: 100%;
  }

  .module-guide__hero {
    gap: 14px;
    margin-bottom: 26px;
  }

  .module-guide__hero-copy {
    min-height: 220px;
    padding: 26px 22px;
  }

  .module-guide__name {
    font-size: 34px;
  }

  .module-guide__desc {
    font-size: 14px;
  }

  .module-guide__console {
    min-height: 166px;
  }

  .module-guide__metric-value {
    font-size: 50px;
  }

  .module-guide__section-head {
    flex-direction: column;
  }

  .module-guide__grid {
    grid-template-columns: 1fr;
  }

  .module-guide__card-body {
    min-height: 238px;
    padding: 22px;
  }

  .module-guide__icon {
    width: 54px;
    height: 54px;
    font-size: 26px;
  }

  .module-guide__title {
    font-size: 20px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .module-guide::before,
  .module-guide::after,
  .module-guide__aurora,
  .module-guide__flow,
  .module-guide__stars,
  .module-guide__binary-drop,
  .module-guide__beam {
    animation: none;
  }
}
</style>
