<script lang="ts" setup>
import type { UploadAsset, UploadFieldValue } from '@vben/common-ui';

import { AdminImageUpload, Page } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import {
  Button,
  Card,
  Col,
  Form,
  FormItem,
  Input,
  Row,
  Textarea,
  message,
} from 'ant-design-vue';
import { computed, onMounted, reactive, ref } from 'vue';

import {
  getAuthEntry,
  getAuthEntryByUserInfo,
  getAuthEntryConfig,
  isPluginAuthEntry,
} from '#/api';
import { profileApiService } from '#/api/core/profile';
import { useAuthStore } from '#/store';

const authStore = useAuthStore();
const userStore = useUserStore();

const profileSaving = ref(false);
const passwordSaving = ref(false);
const avatarAsset = ref<null | UploadAsset>(null);
const currentEntry = computed(() => getAuthEntryByUserInfo(userStore.userInfo) || getAuthEntry());
const currentEntryConfig = computed(() => getAuthEntryConfig(currentEntry.value));
const isPluginProfile = computed(() => isPluginAuthEntry(currentEntry.value));
const profileText = computed(() => currentEntryConfig.value.profile || {});
const pageTitle = computed(() => profileText.value.title || '个人资料');
const pageDescription = computed(() => (
  isPluginProfile.value
    ? profileText.value.description || `维护${currentEntryConfig.value.name}账号资料、联系方式、头像与登录密码。`
    : '维护昵称、联系方式与登录密码；用户名由管理员在「用户管理」中维护。'
));

const profileForm = reactive({
  nickname: '',
  email: '',
  phone: '',
  avatar: '',
  signed: '',
});

const pwdForm = reactive({
  old_password: '',
  new_password: '',
  confirm: '',
});

const avatarFieldValue = computed<UploadFieldValue>({
  get: () => avatarAsset.value,
  set: (value) => {
    const asset = Array.isArray(value) ? (value[0] ?? null) : value;
    avatarAsset.value = asset;
    profileForm.avatar = asset?.url ?? '';
  },
});

function getAvatarName(url: string) {
  const normalized = String(url || '').trim();
  if (!normalized) {
    return 'avatar';
  }

  try {
    const pathname = new URL(normalized).pathname;
    return pathname.split('/').filter(Boolean).pop() || 'avatar';
  } catch {
    return normalized.split('/').filter(Boolean).pop() || 'avatar';
  }
}

function createAvatarAsset(url: string): UploadAsset {
  const name = getAvatarName(url);

  return {
    id: 0,
    url,
    preview_url: url,
    download_url: url,
    hash: null,
    suffix: name.includes('.') ? name.split('.').pop() || '' : '',
    origin_name: name,
    object_name: name,
    storage_mode: 0,
    storage_path: '',
    mime_type: '',
    size_byte: 0,
    size_info: '',
  };
}

function syncProfileForm() {
  const u = userStore.userInfo;
  const p = u?.profile;
  profileForm.nickname = p?.nickname ?? u?.realName ?? '';
  profileForm.email = p?.email ?? '';
  profileForm.phone = p?.phone ?? '';
  profileForm.avatar = p?.avatar ?? u?.avatar ?? '';
  profileForm.signed = p?.signed ?? u?.desc ?? '';
  avatarAsset.value = profileForm.avatar ? createAvatarAsset(profileForm.avatar) : null;
}

onMounted(async () => {
  try {
    await authStore.fetchUserInfo();
  } catch {
    message.error('加载用户信息失败');
  }
  syncProfileForm();
});

async function handleSaveProfile() {
  profileSaving.value = true;
  try {
    const data = await profileApiService.updateProfile({ ...profileForm });
    userStore.setUserInfo(data);
    message.success('资料已保存');
    syncProfileForm();
  } finally {
    profileSaving.value = false;
  }
}

async function handleChangePassword() {
  if (!pwdForm.old_password || !pwdForm.new_password) {
    message.warning('请填写原密码和新密码');
    return;
  }
  if (pwdForm.new_password !== pwdForm.confirm) {
    message.error('两次输入的新密码不一致');
    return;
  }
  passwordSaving.value = true;
  try {
    await profileApiService.changePassword({
      old_password: pwdForm.old_password,
      new_password: pwdForm.new_password,
    });
    message.success('密码已修改');
    pwdForm.old_password = '';
    pwdForm.new_password = '';
    pwdForm.confirm = '';
  } finally {
    passwordSaving.value = false;
  }
}
</script>

<template>
  <Page :title="pageTitle" :description="pageDescription">
    <Row :gutter="[16, 16]" align="stretch" wrap>
      <Col :xs="24" :lg="12">
        <Card title="基本资料" class="h-full">
          <Form layout="vertical" class="max-w-full">
            <FormItem label="用户名">
              <Input :value="userStore.userInfo?.username" disabled />
            </FormItem>
            <FormItem :label="isPluginProfile ? profileText.nicknameLabel || '姓名' : '昵称'">
              <Input v-model:value="profileForm.nickname" :placeholder="isPluginProfile ? `${profileText.nicknameLabel || '姓名'} / 显示名称` : '显示名称'" allow-clear />
            </FormItem>
            <FormItem label="邮箱">
              <Input v-model:value="profileForm.email" placeholder="邮箱" allow-clear />
            </FormItem>
            <FormItem label="手机">
              <Input v-model:value="profileForm.phone" placeholder="手机号" allow-clear />
            </FormItem>
            <FormItem label="头像">
              <AdminImageUpload
                v-model="avatarFieldValue"
                :allow-select-existing="false"
                :clearable="true"
                button-text="上传头像"
                scene="image"
              />
            </FormItem>
            <FormItem :label="isPluginProfile ? profileText.signedLabel || '签名' : '个性签名'">
              <Textarea
                v-model:value="profileForm.signed"
                :rows="3"
                placeholder="一句话介绍自己"
                allow-clear
              />
            </FormItem>
            <FormItem>
              <Button type="primary" :loading="profileSaving" @click="handleSaveProfile">
                保存资料
              </Button>
            </FormItem>
          </Form>
        </Card>
      </Col>
      <Col :xs="24" :lg="12">
        <Card title="修改密码" class="h-full">
          <Form layout="vertical" class="max-w-full">
            <FormItem label="当前密码">
              <Input.Password v-model:value="pwdForm.old_password" autocomplete="current-password" />
            </FormItem>
            <FormItem label="新密码">
              <Input.Password v-model:value="pwdForm.new_password" autocomplete="new-password" />
            </FormItem>
            <FormItem label="确认新密码">
              <Input.Password v-model:value="pwdForm.confirm" autocomplete="new-password" />
            </FormItem>
            <FormItem>
              <Button type="primary" :loading="passwordSaving" @click="handleChangePassword">
                修改密码
              </Button>
            </FormItem>
          </Form>
        </Card>
      </Col>
    </Row>
  </Page>
</template>
