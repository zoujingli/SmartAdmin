<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue';

import { AuthPageLayout } from '@vben/layouts';
import { preferences } from '@vben/preferences';

import { coreAuthApiService } from '#/api';
import { $t } from '#/locales';
import { applyUiMetaPreferences } from '#/preferences/user-preferences';

const appName = computed(() => preferences.app.name);
const logo = computed(() => preferences.logo.source);
const loginTitle = ref('');
const loginDescription = ref('');
const pageTitle = computed(() => loginTitle.value || $t('authentication.pageTitle'));
const pageDescription = computed(() => loginDescription.value || $t('authentication.pageDesc'));

onMounted(async () => {
  try {
    const meta = await coreAuthApiService.getUiMeta();
    applyUiMetaPreferences(meta);
    loginTitle.value = meta.login_title || '';
    loginDescription.value = meta.login_description || '';
  } catch {
    loginTitle.value = '';
    loginDescription.value = '';
  }
});
</script>

<template>
  <AuthPageLayout
    :app-name="appName"
    :logo="logo"
    :page-description="pageDescription"
    :page-title="pageTitle"
  >
    <!-- 自定义工具栏 -->
    <!-- <template #toolbar></template> -->
  </AuthPageLayout>
</template>
