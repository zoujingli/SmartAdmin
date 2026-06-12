<script lang="ts" setup>
import { AuthenticationQrCodeLogin } from '@vben/common-ui';

import { getAuthLoginPath } from '#/api';

defineOptions({ name: 'QrCodeLogin' });
</script>

<template>
  <AuthenticationQrCodeLogin :login-path="getAuthLoginPath()" />
</template>
