import type { Component } from 'vue';

let globalOverlay: Component | null = null;

/** 插件可以注册一个全局浮层，公共壳只负责渲染，不直接依赖业务插件。 */
export function configureGlobalOverlay(component: Component | null) {
  globalOverlay = component;
}

export function getGlobalOverlay(): Component | null {
  return globalOverlay;
}
