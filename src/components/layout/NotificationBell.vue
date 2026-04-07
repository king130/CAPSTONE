<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { Bell } from 'lucide-vue-next'
import { useRouter } from 'vue-router'

import NotificationItem from '@/components/layout/NotificationItem.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Popover from '@/components/ui/popover/Popover.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { resolveNotificationDestination } from '@/composables/useNotificationDestination'
import { useToast } from '@/composables/useToast'
import { useNotificationStore } from '@/stores/notifications'
import { useAuthStore } from '@/stores/auth'
import type { Notification } from '@/services/notifications'
import { getToken } from '@/services/http'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const router = useRouter()
const { success, error } = useToast()

const recentItems = computed(() => notificationStore.recentItems)
const unreadCount = computed(() => notificationStore.unreadCount)
const currentRole = computed(() => authStore.user?.role ?? null)

async function handleMarkRead(notification: Notification) {
  try {
    await notificationStore.markRead(notification.id)
    success('Notification marked as read.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update notification.',
    })
  }
}

async function handleOpen(notification: Notification, close: () => void) {
  if (!notification.isRead) {
    await handleMarkRead(notification)
  }

  close()
  await router.push(resolveNotificationDestination(notification, currentRole.value))
}

function handleViewAll(close: () => void) {
  close()
  void router.push('/notifications')
}

onMounted(() => {
  if (!getToken()) return

  if (!notificationStore.items.length) {
    void notificationStore.fetchNotifications().catch(() => {})
  }
})

watch(
  () => authStore.user?.uid,
  (uid) => {
    if (!uid || !getToken()) {
      notificationStore.clear()
      return
    }

    void notificationStore.fetchNotifications().catch(() => {})
  },
)
</script>

<template>
  <Popover>
    <template #trigger>
      <Button variant="ghost" size="icon" class="relative rounded-full" aria-label="Open notifications">
        <Bell class="h-5 w-5" />
        <Badge
          v-if="unreadCount"
          class="absolute -right-1 -top-1 min-w-5 justify-center border-red-200 bg-red-500 px-1.5 text-[10px] text-white"
        >
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </Badge>
      </Button>
    </template>

    <template #default="{ close }">
      <div class="space-y-3">
        <div class="flex items-center justify-between px-2 py-1">
          <div>
            <p class="text-sm font-semibold text-slate-950">Notifications</p>
            <p class="text-xs text-slate-500">Recent updates from your workspace</p>
          </div>
          <Button variant="ghost" size="sm" class="text-sky-700" @click="handleViewAll(close)">View All</Button>
        </div>

        <div class="space-y-2">
          <template v-if="notificationStore.loading">
            <div v-for="index in 3" :key="index" class="rounded-lg border border-border/80 p-3">
              <div class="flex gap-3">
                <Skeleton class="h-10 w-10 rounded-xl" />
                <div class="min-w-0 flex-1 space-y-2">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-4/5" />
                  <Skeleton class="h-3 w-20" />
                </div>
              </div>
            </div>
          </template>
          <template v-else-if="recentItems.length">
            <NotificationItem
              v-for="notification in recentItems"
              :key="notification.id"
              :notification="notification"
              compact
              @mark-read="handleMarkRead"
              @open="handleOpen($event, close)"
            />
          </template>
          <div
            v-else
            class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
          >
            You're all caught up.
          </div>
        </div>
      </div>
    </template>
  </Popover>
</template>
