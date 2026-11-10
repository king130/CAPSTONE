<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  Activity,
  BarChart3,
  Bookmark,
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  ClipboardList,
  Clock3,
  CreditCard,
  FileCheck2,
  FileText,
  GraduationCap,
  Handshake,
  Home,
  KeyRound,
  LayoutDashboard,
  LogOut,
  MapPin,
  Menu,
  MessageCircle,
  PanelLeftClose,
  PanelLeftOpen,
  Receipt,
  School,
  Search,
  Send,
  Settings,
  Shield,
  Sparkles,
  User,
  Users,
} from 'lucide-vue-next'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Avatar from '@/components/ui/avatar/Avatar.vue'
import Button from '@/components/ui/button/Button.vue'
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue'
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue'
import Sheet from '@/components/ui/sheet/Sheet.vue'
import NotificationBell from '@/components/layout/NotificationBell.vue'
import SubscriptionAccessBanner from '@/components/SubscriptionAccessBanner.vue'
import StudentNumberRequiredBanner from '@/components/StudentNumberRequiredBanner.vue'
import { Moon, Sun } from 'lucide-vue-next'
import type { LayoutIconName, LayoutNavItem, LayoutNavSection, LayoutRole } from '@/layouts/navigation'
import { defaultNavSections, visibleNavSections } from '@/layouts/navigation'
import { useFloatingChat } from '@/composables/useFloatingChat'
import { useTheme } from '@/composables/useTheme'
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
const logoutConfirmOpen = ref(false)
const sidebarCollapsed = ref(false)
const { unreadCount, badgeLabel, open: openChat } = useFloatingChat()
const { isDark, toggleTheme } = useTheme()
const showChatButton = computed(() => {
  const role = authStore.user?.role
  return role === 'student' || role === 'school' || role === 'company' || role === 'municipal_hall'
})

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
  home: Home,
  bookmark: Bookmark,
  send: Send,
  'map-pin': MapPin,
  search: Search,
  'file-check': FileCheck2,
  'graduation-cap': GraduationCap,
  clipboard: ClipboardList,
  chart: BarChart3,
  shield: Shield,
  key: KeyRound,
  'credit-card': CreditCard,
  calendar: CalendarDays,
  sparkles: Sparkles,
  handshake: Handshake,
  receipt: Receipt,
}

const isStudent = computed(() => props.role === 'student')

const internTabs = [
  { key: 'openings', label: 'Openings', icon: Search, to: { name: 'intern-openings' } },
  { key: 'opportunities', label: 'Partners', icon: Building2, to: { name: 'intern-opportunities' } },
  { key: 'internship', label: 'Applications', icon: BriefcaseBusiness, to: { name: 'intern-applications' } },
  { key: 'dashboard', label: 'Interviews', icon: Activity, to: { name: 'intern-tracker' } },
  { key: 'settings', label: 'Profile', icon: User, to: { name: 'intern' } },
] as const

const internPrimaryTabKeys = new Set(internTabs.map((tab) => tab.key))

const resolvedNavSections = computed<LayoutNavSection[]>(() => {
  if (props.navItems) {
    return props.navItems.length
      ? [{ key: 'custom', label: '', items: props.navItems }]
      : []
  }

  if (props.role === 'admin' || props.role === 'student') {
    return defaultNavSections[props.role]
  }

  const assigned = authStore.user?.memberships?.[0]?.permissions ?? []
  return visibleNavSections(props.role, assigned)
})

/** Mobile hamburger: secondary pages only (primary tabs live in the bottom bar). */
const mobileNavSections = computed<LayoutNavSection[]>(() => {
  if (!isStudent.value) return resolvedNavSections.value
  return resolvedNavSections.value
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => !internPrimaryTabKeys.has(item.key)),
    }))
    .filter((section) => section.items.length > 0)
})

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

const contentOffsetClass = computed(() => {
  if (sidebarCollapsed.value) return 'md:pl-[4.5rem]'
  return 'md:pl-72'
})

function isItemActive(item: LayoutNavItem) {
  if (props.activeItem) {
    return item.key === props.activeItem
  }

  if (typeof item.to === 'object' && item.to && 'name' in item.to) {
    return route.name === item.to.name
  }

  return false
}

function isTabActive(tabKey: string) {
  if (props.activeItem) return props.activeItem === tabKey
  const tab = internTabs.find((entry) => entry.key === tabKey)
  return Boolean(tab && route.name === tab.to.name)
}

function askLogout() {
  mobileOpen.value = false
  logoutConfirmOpen.value = true
}

async function confirmLogout() {
  logoutConfirmOpen.value = false
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
    return
  }

  emit('navigate', item)
}

const navLinkBaseClass =
  'shell-nav-item relative flex h-11 w-full items-center gap-3 rounded-lg text-sm font-medium transition-colors'

function navLinkClass(item: LayoutNavItem) {
  return cn(
    navLinkBaseClass,
    sidebarCollapsed.value ? 'justify-center px-0' : 'px-3',
    isItemActive(item)
      ? 'bg-sky-50 font-semibold text-sky-900 dark:bg-slate-800 dark:text-white'
      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
  )
}

const shellSurfaceClass =
  'border-slate-200 bg-white text-slate-900 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100'

const shellBorderClass = 'border-slate-200 dark:border-slate-800'

const logoutButtonClass = computed(() =>
  cn(
    'h-11 w-full text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
    sidebarCollapsed.value ? 'justify-center px-0' : 'justify-start px-3',
  ),
)

const activeIndicatorClass =
  'absolute top-1.5 bottom-1.5 left-0 w-1 rounded-r-full bg-sky-500'

function syncTabbarClearance() {
  if (typeof document === 'undefined') return
  const phone = window.matchMedia('(max-width: 767px)').matches
  document.documentElement.style.setProperty(
    '--shell-tabbar-height',
    phone && isStudent.value ? '4.25rem' : '0px',
  )
}

watch(isStudent, syncTabbarClearance, { immediate: true })

onMounted(() => {
  syncTabbarClearance()
  window.addEventListener('resize', syncTabbarClearance)
  const stored = window.localStorage.getItem('ojt-sidebar-collapsed')
  if (stored === '1') sidebarCollapsed.value = true
})

onUnmounted(() => {
  window.removeEventListener('resize', syncTabbarClearance)
  document.documentElement.style.setProperty('--shell-tabbar-height', '0px')
})

watch(sidebarCollapsed, (value) => {
  window.localStorage.setItem('ojt-sidebar-collapsed', value ? '1' : '0')
})
</script>

<template>
  <div class="min-h-dvh-safe bg-background text-foreground">
    <aside
      class="fixed top-0 left-0 z-sidebar hidden h-[100dvh] select-none flex-col border-r pt-safe backdrop-blur md:flex"
      :class="[
        shellSurfaceClass,
        sidebarCollapsed ? 'w-[4.5rem]' : 'w-72',
      ]"
    >
      <div class="shrink-0 border-b px-2 py-3" :class="shellBorderClass">
        <div
          class="flex"
          :class="sidebarCollapsed ? 'flex-col items-center gap-2' : 'items-center gap-3 px-2'"
        >
          <div
            class="flex shrink-0 items-center justify-center rounded-xl bg-primary font-semibold text-primary-foreground"
            :class="sidebarCollapsed ? 'h-9 w-9 text-sm' : 'h-11 w-11 text-lg'"
            aria-hidden="true"
          >
            OJ
          </div>
          <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
            <p class="text-sm font-semibold tracking-wide text-slate-900 dark:text-slate-100">OJT Intern Path</p>
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">{{ role }}</p>
          </div>
          <Button
            v-if="!sidebarCollapsed"
            variant="ghost"
            size="icon"
            class="hidden h-11 w-11 shrink-0 lg:inline-flex"
            aria-label="Collapse sidebar"
            title="Collapse sidebar"
            @click="sidebarCollapsed = true"
          >
            <PanelLeftClose class="h-4 w-4" />
          </Button>
        </div>
        <Button
          v-if="sidebarCollapsed"
          variant="ghost"
          size="icon"
          class="mx-auto mt-1 flex h-11 w-11"
          aria-label="Expand sidebar"
          title="Expand sidebar"
          @click="sidebarCollapsed = false"
        >
          <PanelLeftOpen class="h-4 w-4" />
        </Button>
        <Button
          v-else
          variant="ghost"
          size="sm"
          class="mt-2 hidden h-11 w-full md:inline-flex lg:hidden"
          aria-label="Collapse sidebar"
          title="Collapse sidebar"
          @click="sidebarCollapsed = true"
        >
          <PanelLeftClose class="h-4 w-4" />
          <span>Collapse</span>
        </Button>
      </div>

      <nav class="shell-menu-scroll space-y-3 p-2" :class="sidebarCollapsed ? 'px-1.5' : 'p-3'">
        <div v-for="section in resolvedNavSections" :key="section.key">
          <div
            v-if="section.label && !sidebarCollapsed"
            class="flex items-center gap-2 px-3 pt-3 pb-1.5"
          >
            <p class="shrink-0 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
              {{ section.label }}
            </p>
            <span class="h-[1px] flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true" />
          </div>
          <ul class="space-y-1">
            <li v-for="item in section.items" :key="item.key">
              <RouterLink
                v-if="item.to"
                :to="item.to"
                :class="navLinkClass(item)"
                :title="item.label"
                :aria-label="item.label"
                @click="mobileOpen = false"
              >
                <span
                  v-if="isItemActive(item)"
                  :class="activeIndicatorClass"
                  aria-hidden="true"
                />
                <component :is="iconMap[item.icon]" class="h-5 w-5 shrink-0" aria-hidden="true" />
                <span v-if="!sidebarCollapsed" class="truncate">{{ item.label }}</span>
              </RouterLink>
              <button
                v-else
                type="button"
                :class="navLinkClass(item)"
                :title="item.label"
                :aria-label="item.label"
                @click="handleItemClick(item)"
              >
                <span
                  v-if="isItemActive(item)"
                  :class="activeIndicatorClass"
                  aria-hidden="true"
                />
                <component :is="iconMap[item.icon]" class="h-5 w-5 shrink-0" aria-hidden="true" />
                <span v-if="!sidebarCollapsed" class="truncate">{{ item.label }}</span>
              </button>
            </li>
          </ul>
        </div>
      </nav>

      <div class="shrink-0 border-t p-2 pb-[max(0.75rem,var(--safe-bottom))]" :class="[shellBorderClass, sidebarCollapsed ? 'px-1.5' : 'p-3']">
        <Button
          variant="ghost"
          :class="logoutButtonClass"
          title="Logout"
          aria-label="Logout"
          @click="askLogout"
        >
          <LogOut class="h-5 w-5 shrink-0" aria-hidden="true" />
          <span v-if="!sidebarCollapsed">Logout</span>
        </Button>
      </div>
    </aside>

    <div :class="contentOffsetClass">
      <header
        class="sticky top-0 z-header border-b pt-safe backdrop-blur"
        :class="cn(shellSurfaceClass, 'bg-white/90 dark:bg-slate-900/90')"
      >
        <div class="flex h-14 items-center justify-between gap-3 px-3 sm:h-16 sm:gap-4 sm:px-6">
          <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
            <Sheet :open="mobileOpen" @update:open="mobileOpen = $event">
              <template #trigger>
                <Button
                  variant="ghost"
                  size="icon"
                  class="shrink-0 md:hidden"
                  :aria-label="isStudent ? 'More menu' : 'Open menu'"
                  @click="mobileOpen = true"
                >
                  <Menu class="h-5 w-5" />
                </Button>
              </template>

              <template #header>
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground">
                    OJ
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">OJT Intern Path</p>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">{{ role }}</p>
                  </div>
                </div>
              </template>

              <template #default>
                <nav class="space-y-4 p-1">
                  <div v-for="section in mobileNavSections" :key="section.key">
                    <div
                      v-if="section.label"
                      class="flex items-center gap-2 px-3 pt-3 pb-1.5"
                    >
                      <p class="shrink-0 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ section.label }}
                      </p>
                      <span class="h-[1px] flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true" />
                    </div>
                    <ul class="space-y-1">
                      <li v-for="item in section.items" :key="item.key">
                        <RouterLink
                          v-if="item.to"
                          :to="item.to"
                          :class="navLinkClass(item)"
                          :title="item.label"
                          :aria-label="item.label"
                          @click="mobileOpen = false"
                        >
                          <span
                            v-if="isItemActive(item)"
                            :class="activeIndicatorClass"
                            aria-hidden="true"
                          />
                          <component :is="iconMap[item.icon]" class="h-5 w-5 shrink-0" aria-hidden="true" />
                          <span class="truncate">{{ item.label }}</span>
                        </RouterLink>
                        <button
                          v-else
                          type="button"
                          :class="navLinkClass(item)"
                          :title="item.label"
                          :aria-label="item.label"
                          @click="handleItemClick(item)"
                        >
                          <span
                            v-if="isItemActive(item)"
                            :class="activeIndicatorClass"
                            aria-hidden="true"
                          />
                          <component :is="iconMap[item.icon]" class="h-5 w-5 shrink-0" aria-hidden="true" />
                          <span class="truncate">{{ item.label }}</span>
                        </button>
                      </li>
                    </ul>
                  </div>
                  <p
                    v-if="isStudent && mobileNavSections.length === 0"
                    class="px-3 py-2 text-sm text-muted-foreground"
                  >
                    Primary pages are in the bottom tabs.
                  </p>
                </nav>
              </template>

              <template #footer>
                <Button variant="ghost" :class="logoutButtonClass" @click="askLogout">
                  <LogOut class="h-4 w-4 shrink-0" />
                  <span>Logout</span>
                </Button>
              </template>
            </Sheet>

            <div class="min-w-0">
              <p class="hidden text-sm font-medium text-slate-400 dark:text-slate-500 sm:block">Workspace</p>
              <h1 class="truncate text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-xl">{{ title }}</h1>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            <Button
              v-if="showChatButton"
              variant="ghost"
              size="icon"
              class="relative rounded-full"
              :aria-label="unreadCount > 0 ? `Open messages, ${unreadCount} unread` : 'Open messages'"
              @click="openChat"
            >
              <MessageCircle class="h-5 w-5" />
              <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
              >
                {{ badgeLabel }}
              </span>
            </Button>

            <NotificationBell />

            <DropdownMenu>
              <template #trigger>
                <Button variant="ghost" class="h-11 w-11 rounded-full p-1.5">
                  <Avatar :src="avatarUrl" :fallback="initials" :alt="displayName" />
                </Button>
              </template>

              <template #default="{ close }">
                <div class="px-3 py-2">
                  <p class="truncate text-sm font-semibold">{{ displayName }}</p>
                  <p class="truncate text-xs text-muted-foreground">{{ authStore.user?.email }}</p>
                </div>
                <DropdownMenuItem @click="handleProfile(); close()">
                  <Settings class="h-4 w-4" />
                  <span>Settings</span>
                </DropdownMenuItem>
                <DropdownMenuItem @click="toggleTheme(); close()">
                  <Sun v-if="isDark" class="h-4 w-4" />
                  <Moon v-else class="h-4 w-4" />
                  <span>{{ isDark ? 'Light mode' : 'Dark mode' }}</span>
                </DropdownMenuItem>
                <DropdownMenuItem class="text-destructive hover:text-destructive" @click="askLogout(); close()">
                  <LogOut class="h-4 w-4" />
                  <span>Logout</span>
                </DropdownMenuItem>
              </template>
            </DropdownMenu>
          </div>
        </div>
      </header>

      <main
        class="min-h-[calc(100dvh-3.5rem)] p-3 sm:min-h-[calc(100dvh-4rem)] sm:p-6"
        :class="isStudent ? 'pb-[calc(5.5rem+var(--safe-bottom))] md:pb-6' : 'pb-[max(1rem,var(--safe-bottom))]'"
      >
        <SubscriptionAccessBanner />
        <StudentNumberRequiredBanner />
        <slot />
      </main>
    </div>

    <nav
      v-if="isStudent"
      class="fixed inset-x-0 bottom-0 z-header border-t border-slate-200 bg-white/95 pb-safe backdrop-blur md:hidden dark:border-slate-800 dark:bg-slate-900/95"
      aria-label="Intern primary navigation"
    >
      <ul class="grid h-[4.25rem] grid-cols-5">
        <li v-for="tab in internTabs" :key="tab.key">
          <RouterLink
            :to="tab.to"
            class="flex h-full min-h-11 flex-col items-center justify-center gap-0.5 px-1 text-[10px] font-medium transition-colors"
            :class="
              isTabActive(tab.key)
                ? 'text-sky-700 dark:text-sky-300'
                : 'text-slate-500 dark:text-slate-400'
            "
          >
            <component :is="tab.icon" class="h-5 w-5 shrink-0" />
            <span class="truncate">{{ tab.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <AlertDialog
      :open="logoutConfirmOpen"
      title="Log out?"
      description="You will need to sign in again to return to this workspace."
      action-label="Log out"
      action-variant="destructive"
      @update:open="logoutConfirmOpen = $event"
      @action="confirmLogout"
    />
  </div>
</template>
