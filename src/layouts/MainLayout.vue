<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Activity,
  BriefcaseBusiness,
  Building2,
  Clock3,
  FileText,
  LayoutDashboard,
  LogOut,
  Menu,
  Moon,
  School,
  Settings,
  Sun,
  User,
  Users,
} from 'lucide-vue-next'

import Avatar from '@/components/ui/avatar/Avatar.vue'
import Button from '@/components/ui/button/Button.vue'
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue'
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue'
import Sheet from '@/components/ui/sheet/Sheet.vue'
import NotificationBell from '@/components/layout/NotificationBell.vue'
import { useTheme } from '@/composables/useTheme'
import type { LayoutIconName, LayoutNavItem, LayoutRole } from '@/layouts/navigation'
import { defaultNavItems } from '@/layouts/navigation'
import { buildProfileAvatarUrl } from '@/services/profileMedia'
import { useAuthStore } from '@/stores/auth'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    title: string
    role: LayoutRole
    activeItem?: string
    navItems?: LayoutNavItem[]
  }>(),
  {
    activeItem: '',
    navItems: undefined,
  },
)

const emit = defineEmits<{
  navigate: [item: LayoutNavItem]
}>()

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const mobileOpen = ref(false)
const { isDark, toggleTheme } = useTheme()

const iconMap: Record<LayoutIconName, object> = {
  'layout-dashboard': LayoutDashboard,
  users: Users,
  school: School,
  building: Building2,
  'file-text': FileText,
  settings: Settings,
  user: User,
  briefcase: BriefcaseBusiness,
  activity: Activity,
  clock: Clock3,
}

const resolvedNavItems = computed(() => props.navItems ?? defaultNavItems[props.role])

const displayName = computed(() => authStore.user?.displayName || authStore.user?.email || 'User')

const initials = computed(() =>
  displayName.value
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2),
)

const avatarUrl = computed(() => {
  const currentUser = authStore.user
  const profile = currentUser?.profile as Record<string, unknown> | undefined

  if (currentUser?.uid && profile?.avatarPath) {
    return buildProfileAvatarUrl(currentUser.uid, currentUser.updatedAt)
  }

  return ''
})

function isItemActive(item: LayoutNavItem) {
  if (props.activeItem && item.key === props.activeItem) {
    return true
  }

  if (typeof item.to === 'object' && item.to && 'name' in item.to) {
    return route.name === item.to.name
  }

  return false
}

async function handleLogout() {
  mobileOpen.value = false
  await authStore.logout()
  router.push('/login')
}

function handleProfile() {
  mobileOpen.value = false
  if (authStore.user?.role === 'admin') {
    router.push('/admin/settings')
    return
  }

  router.push('/settings')
}

function handleItemClick(item: LayoutNavItem) {
  mobileOpen.value = false

  if (item.to) {
    router.push(item.to)
    return
  }

  emit('navigate', item)
}
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 border-r border-border bg-background/95 backdrop-blur lg:flex lg:flex-col">
      <div class="border-b border-border px-6 py-6">
        <div class="flex items-center gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary text-lg font-semibold text-primary-foreground">
            OJ
          </div>
          <div>
            <p class="text-sm font-semibold tracking-wide text-foreground">OJT Intern Path</p>
            <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">{{ role }}</p>
          </div>
        </div>
      </div>

      <nav class="flex-1 space-y-1 px-4 py-6">
        <Button
          v-for="item in resolvedNavItems"
          :key="item.key"
          variant="ghost"
          class="w-full justify-start px-3 py-6"
          :class="
            cn(
              isItemActive(item)
                ? 'bg-accent text-accent-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground',
            )
          "
          @click="handleItemClick(item)"
        >
          <component :is="iconMap[item.icon]" class="h-4 w-4" />
          <span>{{ item.label }}</span>
        </Button>
      </nav>
    </aside>

    <div class="lg:pl-72">
      <header class="sticky top-0 z-20 border-b border-border bg-background/90 backdrop-blur">
        <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
          <div class="flex items-center gap-3">
            <Sheet :open="mobileOpen" @update:open="mobileOpen = $event">
              <template #trigger>
                <Button variant="ghost" size="icon" class="lg:hidden" @click="mobileOpen = true">
                  <Menu class="h-5 w-5" />
                </Button>
              </template>

              <template #header>
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground">
                    OJ
                  </div>
                  <div>
                    <p class="text-sm font-semibold">OJT Intern Path</p>
                    <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">{{ role }}</p>
                  </div>
                </div>
              </template>

              <template #default>
                <nav class="space-y-2">
                  <Button
                    v-for="item in resolvedNavItems"
                    :key="item.key"
                    variant="ghost"
                    class="w-full justify-start px-3 py-6"
                    :class="
                      cn(
                        isItemActive(item)
                          ? 'bg-accent text-accent-foreground shadow-sm'
                          : 'text-muted-foreground hover:text-foreground',
                      )
                    "
                    @click="handleItemClick(item)"
                  >
                    <component :is="iconMap[item.icon]" class="h-4 w-4" />
                    <span>{{ item.label }}</span>
                  </Button>
                </nav>
              </template>
            </Sheet>

            <div>
              <p class="text-sm font-medium text-muted-foreground">Workspace</p>
              <h1 class="text-xl font-semibold tracking-tight text-foreground">{{ title }}</h1>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <NotificationBell />

            <Button
              variant="ghost"
              size="icon"
              class="rounded-full"
              :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
              @click="toggleTheme"
            >
              <Sun v-if="isDark" class="h-4 w-4" />
              <Moon v-else class="h-4 w-4" />
            </Button>

            <DropdownMenu>
              <template #trigger>
                <Button variant="ghost" class="h-auto rounded-full p-1.5">
                  <Avatar :src="avatarUrl" :fallback="initials" :alt="displayName" />
                </Button>
              </template>

              <template #default="{ close }">
                <div class="px-3 py-2">
                  <p class="text-sm font-semibold">{{ displayName }}</p>
                  <p class="text-xs text-muted-foreground">{{ authStore.user?.email }}</p>
                </div>
                <DropdownMenuItem @click="handleProfile(); close()">
                  <Settings class="h-4 w-4" />
                  <span>Settings</span>
                </DropdownMenuItem>
                <DropdownMenuItem class="text-red-600 hover:text-red-600" @click="handleLogout(); close()">
                  <LogOut class="h-4 w-4" />
                  <span>Logout</span>
                </DropdownMenuItem>
              </template>
            </DropdownMenu>
          </div>
        </div>
      </header>

      <main class="min-h-[calc(100vh-4rem)] p-4 sm:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
