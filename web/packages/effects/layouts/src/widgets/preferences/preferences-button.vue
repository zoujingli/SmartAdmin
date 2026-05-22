<script lang="ts" setup>
import { Settings } from '@vben/icons';

import { VbenIconButton } from '@vben-core/shadcn-ui';

import Preferences from './preferences.vue';

const emit = defineEmits<{ clearPreferencesAndLogout: []; savePreferences: [] }>();

function clearPreferencesAndLogout() {
  emit('clearPreferencesAndLogout');
}

function savePreferences() {
  emit('savePreferences');
}
</script>
<template>
  <Preferences
    @clear-preferences-and-logout="clearPreferencesAndLogout"
    @save-preferences="savePreferences"
  >
    <VbenIconButton class="hover:animate-[shrink_0.3s_ease-in-out]">
      <Settings class="text-foreground size-4" />
    </VbenIconButton>
  </Preferences>
</template>
