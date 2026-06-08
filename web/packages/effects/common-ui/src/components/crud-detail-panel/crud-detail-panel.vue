<template>
  <div class="crud-detail-panel">
    <slot />
  </div>
</template>

<style scoped>
.crud-detail-panel {
  display: grid;
  gap: 20px;
  grid-template-columns: minmax(0, 1fr);
  min-width: 0;
}
</style>
