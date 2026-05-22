<script lang="ts" setup>
import type { NotificationItem } from '@vben/layouts';

import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { AuthenticationLoginExpiredModal } from '@vben/common-ui';
import { useWatermark } from '@vben/hooks';
import {
  BasicLayout,
  LockScreen,
  Notification,
  UserDropdown,
} from '@vben/layouts';
import { preferences, preferencesManager, resetPreferences } from '@vben/preferences';
import { useAccessStore, useTabbarStore, useUserStore } from '@vben/stores';
import { message, Modal } from 'ant-design-vue';

import {
  getAuthEntry,
  getAuthEntryByUserInfo,
  getAuthEntryConfig,
  getAuthProfilePath,
  isPluginAuthEntry,
  profileApiService,
} from '#/api';
import { dataApiService } from '#/api/system/data';
import { noticeApiService } from '#/api/system/notice';
import {
  buildPersistableUiPreferencesPayload,
  systemUiMeta,
} from '#/preferences/user-preferences';
import { useAuthStore } from '#/store';
import LoginForm from '#/views/_core/authentication/login.vue';

const notifications = ref<NotificationItem[]>([]);
const unreadCount = ref(0);

const userStore = useUserStore();
const authStore = useAuthStore();
const accessStore = useAccessStore();
const tabbarStore = useTabbarStore();
const route = useRoute();
const router = useRouter();
const { destroyWatermark, updateWatermark } = useWatermark();
const currentEntry = computed(() => getAuthEntryByUserInfo(userStore.userInfo) || getAuthEntry());
const currentEntryConfig = computed(() => getAuthEntryConfig(currentEntry.value));
const isPluginClient = computed(() => isPluginAuthEntry(currentEntry.value));
const showDot = computed(() => !isPluginClient.value && unreadCount.value > 0);
const canClearBusinessCache = computed(() => !isPluginClient.value && (
  accessStore.accessCodes.includes('*')
  || accessStore.accessCodes.includes('system.data.clear-cache')
));

function formatNoticeDate(value?: null | string) {
  return value || '刚刚';
}

function toNotificationItem(item: any): NotificationItem {
  return {
    id: item.id,
    date: formatNoticeDate(item.published_at || item.created_at),
    isRead: !!item.is_read,
    level: item.level,
    link: item.link || '/system/notice',
    message: item.content || '系统公告',
    query: item.link ? undefined : { tab: 'inbox' },
    title: item.title,
  };
}

async function loadNotifications() {
  if (!accessStore.accessToken || isPluginClient.value) {
    notifications.value = [];
    unreadCount.value = 0;
    return;
  }

  try {
    const [inbox, unread] = await Promise.all([
      noticeApiService.getInbox({ page: 1, pageSize: 10 }),
      noticeApiService.getUnreadCount(),
    ]);
    notifications.value = (inbox.items || []).map(toNotificationItem);
    unreadCount.value = unread.count || 0;
  } catch (error) {
    console.error('load notifications failed', error);
  }
}

async function refreshNotifications() {
  await loadNotifications();
}

const menus = computed(() => {
  const items = [
    {
      text: '个人资料',
      icon: 'lucide:user',
      handler: () => {
        router.push(getAuthProfilePath(currentEntry.value));
      },
    },
  ];

  if (canClearBusinessCache.value) {
    items.push({
      text: '清理缓存',
      icon: 'lucide:brush-cleaning',
      handler: () => {
        const isSuper = accessStore.accessCodes.includes('*');
        Modal.confirm({
          title: isSuper ? '确认清理全站业务缓存？' : '确认清理我的缓存？',
          content: isSuper
            ? '将清理全站业务缓存白名单，不会影响 JWT 黑名单和当前登录令牌。'
            : '将清理当前登录用户的缓存、标签页和本地偏好。',
          async onOk() {
            const result = await dataApiService.clearCache();
            resetPreferences();
            preferencesManager.clearCache();
            tabbarStore.$reset();
            accessStore.setAccessMenus([]);
            accessStore.setAccessRoutes([]);
            accessStore.setAccessCodes([]);
            accessStore.setIsAccessChecked(false);
            message.success(result.message || '缓存已清理');
            window.location.reload();
          },
        });
      },
    });
  }

  return items;
});

const avatar = computed(() => {
  return userStore.userInfo?.avatar ?? preferences.app.defaultAvatar;
});

/** 下拉展示名：优先昵称，其次用户名 */
const userDisplayName = computed(
  () =>
    userStore.userInfo?.realName ||
    userStore.userInfo?.username ||
    '',
);

/** 副标题：邮箱 / 手机 / 用户名 */
const userDescription = computed(() => {
  const u = userStore.userInfo;
  if (!u) {
    return '';
  }
  const email = u.profile?.email?.trim();
  const phone = u.profile?.phone?.trim();
  return email || phone || u.username || '';
});

/** 角色角标：取第一个角色名，无则隐藏 */
const userRoleTag = computed(() => {
  const roles = userStore.userInfo?.roles;
  return roles?.length ? roles[0]! : '';
});

/** Logo 版本角标：统一补齐 v 前缀，仅展示后台系统参数中的运行版本，不参与浏览器标题。 */
const appVersionTag = computed(() => {
  const version = systemUiMeta.appVersion.trim();
  if (!version) {
    return '';
  }

  return version.toLowerCase().startsWith('v') ? version : `v${version}`;
});

async function handleLogout() {
  await authStore.logout(false);
}

async function handleSavePreferences() {
  try {
    const data = await profileApiService.savePreferences({
      ui_preferences: buildPersistableUiPreferencesPayload(),
    });

    userStore.setUserInfo(data);
    message.success('界面配置已保存');
  } catch {
    // 错误提示已由全局请求拦截器处理
  }
}

function handleNoticeClear() {
  noticeApiService.archiveAll().then(refreshNotifications);
}

function handleMakeAll() {
  noticeApiService.readAll().then(refreshNotifications);
}

function handleReadNotice(item: NotificationItem) {
  noticeApiService.read([Number(item.id)]).then(refreshNotifications);
}

function handleRemoveNotice(item: NotificationItem) {
  noticeApiService.archive([Number(item.id)]).then(refreshNotifications);
}

function handleViewAllNotices() {
  router.push({
    path: '/system/notice',
    query: { tab: 'inbox' },
  });
}
watch(
  () => preferences.app.watermark,
  async (enable) => {
    if (enable) {
      await updateWatermark({
        content: `${userStore.userInfo?.username} - ${userStore.userInfo?.realName}`,
      });
    } else {
      destroyWatermark();
    }
  },
  {
    immediate: true,
  },
);

watch(
  () => accessStore.accessToken,
  () => {
    loadNotifications();
  },
  { immediate: true },
);

watch(
  () => route.fullPath,
  () => {
    if (accessStore.accessToken) {
      loadNotifications();
    }
  },
);

onMounted(() => {
  loadNotifications();
});
</script>

<template>
  <BasicLayout
    @clear-preferences-and-logout="handleLogout"
    @save-preferences="handleSavePreferences"
  >
    <template #logo-text>
      <span class="flex min-w-0 items-center gap-1.5">
        <span class="truncate text-nowrap font-semibold text-foreground">
          {{ isPluginClient ? currentEntryConfig.name : preferences.app.name }}
        </span>
        <span
          v-if="appVersionTag"
          class="shrink-0 rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium leading-none text-primary"
        >
          {{ appVersionTag }}
        </span>
      </span>
    </template>
    <template #user-dropdown>
      <UserDropdown
        :avatar
        :menus
        :text="userDisplayName"
        :description="userDescription"
        :tag-text="userRoleTag"
        @logout="handleLogout"
      />
    </template>
    <template v-if="!isPluginClient" #notification>
      <Notification
        :dot="showDot"
        :notifications="notifications"
        @clear="handleNoticeClear"
        @make-all="handleMakeAll"
        @read="handleReadNotice"
        @remove="handleRemoveNotice"
        @view-all="handleViewAllNotices"
      />
    </template>
    <template #extra>
      <AuthenticationLoginExpiredModal
        v-model:open="accessStore.loginExpired"
        :avatar
      >
        <LoginForm />
      </AuthenticationLoginExpiredModal>
    </template>
    <template #lock-screen>
      <LockScreen :avatar @to-login="handleLogout" />
    </template>
  </BasicLayout>
</template>
