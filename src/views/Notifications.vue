<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { BellRing, Sparkles } from 'lucide-vue-next'
import { useRouter } from 'vue-router'

import NotificationItem from '@/components/layout/NotificationItem.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Tabs from '@/components/ui/tabs/Tabs.vue'
import TabsContent from '@/components/ui/tabs/TabsContent.vue'
import TabsList from '@/components/ui/tabs/TabsList.vue'
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue'
import { resolveNotificationDestination } from '@/composables/useNotificationDestination'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import type { LayoutRole } from '@/layouts/navigation'
import type { UserRole } from '@/services/auth'
import type { Notification } from '@/services/notifications'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { getToken } from '@/services/http'

type NotificationTab = 'all' | 'unread'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const router = useRouter()
const { success, error } = useToast()

const activeTab = ref<NotificationTab>('all')

const layoutRole = computed<LayoutRole>(() => {
  if (authStore.user?.role === 'admin') return 'admin'
  if (authStore.user?.role === 'company') return 'company'
  if (authStore.user?.role === 'school') return 'school'
  return 'student'
})

const allNotifications = computed(() => notificationStore.items)
const unreadNotifications = computed(() => notificationStore.unreadItems)

function itemsForTab(tab: NotificationTab) {
  return tab === 'unread' ? unreadNotifications.value : allNotifications.value
}

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

async function handleMarkAllRead() {
  try {
    await notificationStore.markAllRead()
    success('All notifications marked as read.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to mark notifications as read.',
    })
  }
}

async function handleOpen(notification: Notification) {
  if (!notification.isRead) {
    await handleMarkRead(notification)
  }

  await router.push(resolveNotificationDestination(notification, authStore.user?.role as UserRole))
}

onMounted(() => {
  if (!getToken()) {
    return
  }

  if (!notificationStore.items.length) {
    void notificationStore.fetchNotifications().catch((caughtError: unknown) => {
      error(caughtError, {
        fallback: 'Unable to load notifications.',
      })
    })
  }
})
</script>

<template>
  <MainLayout :role="layoutRole" title="Notifications">
    <section class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="space-y-2">
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                <BellRing class="h-5 w-5" />
              </div>
              <div>
                <h2 class="text-2xl font-semibold text-slate-950">Notifications</h2>
                <p class="text-sm text-slate-600">Stay on top of application updates, endorsements, approvals, and system activity.</p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <Badge v-if="notificationStore.unreadCount" variant="warning">
              {{ notificationStore.unreadCount }} unread
            </Badge>
            <Button
              variant="outline"
              :disabled="!notificationStore.unreadCount"
              @click="handleMarkAllRead"
            >
              Mark All as Read
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <Tabs v-model="activeTab">
            <TabsList>
              <TabsTrigger value="all">All</TabsTrigger>
              <TabsTrigger value="unread">Unread</TabsTrigger>
            </TabsList>

            <TabsContent value="all">
              <div class="space-y-4">
                <template v-if="notificationStore.loading">
                  <div v-for="index in 6" :key="`all-${index}`" class="rounded-xl border border-border/80 p-4">
                    <div class="flex gap-4">
                      <Skeleton class="h-11 w-11 rounded-2xl" />
                      <div class="min-w-0 flex-1 space-y-3">
                        <Skeleton class="h-4 w-full" />
                        <Skeleton class="h-4 w-4/5" />
                        <Skeleton class="h-3 w-24" />
                      </div>
                    </div>
                  </div>
                </template>
                <template v-else-if="itemsForTab('all').length">
                  <NotificationItem
                    v-for="notification in itemsForTab('all')"
                    :key="notification.id"
                    :notification="notification"
                    @mark-read="handleMarkRead"
                    @open="handleOpen"
                  />
                </template>
                <Card v-else class="border-dashed border-border/80 shadow-none">
                  <CardContent class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                      <Sparkles class="h-7 w-7" />
                    </div>
                    <h3 class="mt-5 text-xl font-semibold text-slate-950">You're all caught up!</h3>
                    <p class="mt-2 max-w-md text-sm text-slate-600">
                      New updates will appear here as soon as there is activity related to your workspace.
                    </p>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <TabsContent value="unread">
              <div class="space-y-4">
                <template v-if="notificationStore.loading">
                  <div v-for="index in 4" :key="`unread-${index}`" class="rounded-xl border border-border/80 p-4">
                    <div class="flex gap-4">
                      <Skeleton class="h-11 w-11 rounded-2xl" />
                      <div class="min-w-0 flex-1 space-y-3">
                        <Skeleton class="h-4 w-full" />
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-20" />
                      </div>
                    </div>
                  </div>
                </template>
                <template v-else-if="itemsForTab('unread').length">
                  <NotificationItem
                    v-for="notification in itemsForTab('unread')"
                    :key="notification.id"
                    :notification="notification"
                    @mark-read="handleMarkRead"
                    @open="handleOpen"
                  />
                </template>
                <Card v-else class="border-dashed border-border/80 shadow-none">
                  <CardContent class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                      <Sparkles class="h-7 w-7" />
                    </div>
                    <h3 class="mt-5 text-xl font-semibold text-slate-950">You're all caught up!</h3>
                    <p class="mt-2 max-w-md text-sm text-slate-600">
                      There are no unread notifications right now.
                    </p>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </section>
  </MainLayout>
</template>
