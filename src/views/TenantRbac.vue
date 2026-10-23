<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Building2, ChevronDown, Plus, School, Search, ShieldCheck, Trash2, Users } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import { defaultNavItems, type LayoutNavItem, type LayoutRole } from '@/layouts/navigation'
import {
  createTenantMember,
  createManagedAccount,
  createTenantRole,
  deleteTenantRole,
  getTenantRbac,
  listManageableTenants,
  syncTenantRolePermissions,
  updateTenantMemberRole,
  type TenantMember,
  type TenantPermission,
  type TenantRole,
  type TenantSummary,
} from '@/services/tenantRbac'
import { permissionAliasGroup, permissionDisplayLabel } from '@/services/permissions'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

type PermissionCategoryId = 'view' | 'manage' | 'reports' | 'approve' | 'other'

const CATEGORY_ORDER: PermissionCategoryId[] = ['view', 'manage', 'reports', 'approve', 'other']
const CATEGORY_LABELS: Record<PermissionCategoryId, string> = {
  view: 'View',
  manage: 'Manage',
  reports: 'Reports',
  approve: 'Approve',
  other: 'Other',
}

const props = defineProps<{
  mode: 'roles' | 'permissions'
}>()

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const { success, error } = useToast()

const loading = ref(false)
const creatingRole = ref(false)
const creatingAccount = ref(false)
const creatingMember = ref(false)
const deletingRoleId = ref<string | null>(null)
const savingRoleId = ref<string | null>(null)
const savingMemberAssignments = ref(false)
const tenants = ref<TenantSummary[]>([])
const roles = ref<TenantRole[]>([])
const members = ref<TenantMember[]>([])
const permissions = ref<TenantPermission[]>([])
const selectedTenantId = ref('')
const search = ref('')
const focusedRoleId = ref('')
const collapsedCategories = ref<Record<string, boolean>>({})
const rolePermissionDrafts = ref<Record<string, string[]>>({})
const memberRoleDrafts = ref<Record<string, string>>({})
const newRole = ref({
  name: '',
  slug: '',
  description: '',
})
const newAccount = ref({
  type: 'company' as 'company' | 'school' | 'municipal_hall',
  name: '',
  adminName: '',
  email: '',
  plan: 'free',
  billingCycle: 'monthly',
  lguType: 'municipality',
  locationArea: '',
})
const newMember = ref({
  name: '',
  email: '',
  roleId: '',
  title: '',
})

const currentRole = computed<LayoutRole>(() => {
  const role = authStore.user?.role
  if (role === 'admin' || role === 'company' || role === 'school' || role === 'student') {
    return role
  }

  return 'company'
})

const navItems = computed<LayoutNavItem[]>(() => defaultNavItems[currentRole.value])

const pageTitle = computed(() => (props.mode === 'roles' ? 'Role Management' : 'Permissions'))
const pageSubtitle = computed(() => {
  if (currentRole.value === 'school') {
    return props.mode === 'roles'
      ? 'Create school roles and assign them to coordinators and staff.'
      : 'Toggle what each school role can view, manage, report on, and approve.'
  }
  if (currentRole.value === 'company') {
    return props.mode === 'roles'
      ? 'Create company roles and assign them to your team.'
      : 'Toggle what each company role can view, manage, report on, and approve.'
  }
  return props.mode === 'roles'
    ? 'Manage tenant roles and member assignments.'
    : 'Assign permissions across tenant roles.'
})
const activeItem = computed(() => (props.mode === 'roles' ? 'tenant-role-management' : 'tenant-permission-assignment'))
const selectedTenant = computed(() => tenants.value.find((tenant) => tenant.id === selectedTenantId.value) ?? null)
const showAccountSelector = computed(() => isSystemAdmin.value || tenants.value.length > 1)
const isSystemAdmin = computed(() =>
  authStore.user?.role === 'admin' ||
  authStore.user?.platformRole?.slug === 'system_admin' ||
  authStore.user?.platformRole?.permissions?.includes('platform.manage_users') === true,
)
const canManage = computed(() => {
  if (isSystemAdmin.value) {
    return true
  }

  const membershipPermissions = authStore.user?.memberships?.[0]?.permissions ?? []
  return (
    membershipPermissions.includes('manage_roles') ||
    membershipPermissions.includes('manage_permissions') ||
    membershipPermissions.includes('org.manage_roles')
  )
})

const filteredRoles = computed(() => {
  const query = search.value.trim().toLowerCase()
  let list = roles.value

  if (query) {
    list = list.filter((role) => {
      const haystack = [role.name, role.slug, role.description || '', ...role.permissions].join(' ').toLowerCase()
      return haystack.includes(query)
    })
  }

  if (focusedRoleId.value && props.mode === 'permissions') {
    return [...list].sort((a, b) => {
      if (a.id === focusedRoleId.value) return -1
      if (b.id === focusedRoleId.value) return 1
      return a.name.localeCompare(b.name)
    })
  }

  return list
})

const filteredPermissions = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return permissions.value

  return permissions.value.filter((permission) => {
    const haystack = [permission.key, permissionLabel(permission), permission.description || ''].join(' ').toLowerCase()
    return haystack.includes(query)
  })
})

const permissionCategories = computed(() => {
  const groups: Record<PermissionCategoryId, TenantPermission[]> = {
    view: [],
    manage: [],
    reports: [],
    approve: [],
    other: [],
  }

  for (const permission of filteredPermissions.value) {
    groups[categorizePermission(permission.key)].push(permission)
  }

  return CATEGORY_ORDER
    .map((id) => ({
      id,
      label: CATEGORY_LABELS[id],
      permissions: groups[id],
      collapsed: Boolean(collapsedCategories.value[id]),
    }))
    .filter((group) => group.permissions.length > 0)
})

const hasPendingMemberRoleChanges = computed(() =>
  members.value.some((member) => (memberRoleDrafts.value[member.id] ?? member.role.id) !== member.role.id),
)
const rolesWithPendingPermissionChanges = computed(() =>
  roles.value.filter((role) => hasPendingPermissionChanges(role)),
)
const hasAnyPendingPermissionChanges = computed(() => rolesWithPendingPermissionChanges.value.length > 0)

function tenantIcon(type: 'company' | 'school') {
  return type === 'school' ? School : Building2
}

function categorizePermission(key: string): PermissionCategoryId {
  const normalized = key.toLowerCase()
  if (normalized.includes('report') || normalized.includes('kpi')) return 'reports'
  if (normalized.includes('approve') || normalized.includes('review')) return 'approve'
  if (normalized.startsWith('org.view_') || normalized === 'view_reports' || normalized.startsWith('platform.view_')) {
    return 'view'
  }
  if (normalized.includes('manage') || normalized.includes('upload')) return 'manage'
  if (normalized.includes('view')) return 'view'
  return 'other'
}

function permissionLabel(permission: TenantPermission) {
  return permissionDisplayLabel(permission.key)
}

function aliasesForPermission(permissionKey: string) {
  const meta = permissions.value.find((item) => item.key === permissionKey)
  return permissionAliasGroup(permissionKey, meta?.aliases)
}

function roleHasPermission(role: TenantRole, permissionKey: string) {
  const draft = rolePermissionDrafts.value[role.id]
  const source = draft ?? role.permissions
  return aliasesForPermission(permissionKey).some((key) => source.includes(key))
}

function syncMemberDrafts(items: TenantMember[]) {
  const nextDrafts: Record<string, string> = {}
  for (const member of items) {
    nextDrafts[member.id] = memberRoleDrafts.value[member.id] ?? member.role.id
  }
  memberRoleDrafts.value = nextDrafts
}

function syncRoleDrafts(items: TenantRole[]) {
  const nextDrafts: Record<string, string[]> = {}
  for (const role of items) {
    nextDrafts[role.id] = [...(rolePermissionDrafts.value[role.id] ?? role.permissions)]
  }
  rolePermissionDrafts.value = nextDrafts
}

function roleDraftPermissions(role: TenantRole) {
  return rolePermissionDrafts.value[role.id] ?? role.permissions
}

function hasPendingPermissionChanges(role: TenantRole) {
  const current = [...role.permissions].sort().join('|')
  const draft = [...roleDraftPermissions(role)].sort().join('|')
  return current !== draft
}

function togglePermissionDraft(role: TenantRole, permissionKey: string, enabled: boolean) {
  const current = roleDraftPermissions(role)
  const group = aliasesForPermission(permissionKey)
  rolePermissionDrafts.value = {
    ...rolePermissionDrafts.value,
    [role.id]: enabled
      ? Array.from(new Set([...current, ...group]))
      : current.filter((item) => !group.includes(item)),
  }
}

function toggleCategory(categoryId: string) {
  collapsedCategories.value = {
    ...collapsedCategories.value,
    [categoryId]: !collapsedCategories.value[categoryId],
  }
}

async function loadTenants() {
  if (!canManage.value) return

  loading.value = true
  try {
    const items = await listManageableTenants()
    tenants.value = items

    const preferredTenantId =
      authStore.user?.activeOrganization?.id ||
      items[0]?.id ||
      ''

    if (!selectedTenantId.value || !items.some((item) => item.id === selectedTenantId.value)) {
      selectedTenantId.value = preferredTenantId
    }
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load tenants.' })
  } finally {
    loading.value = false
  }
}

async function loadTenantRbac() {
  if (!selectedTenantId.value) {
    roles.value = []
    members.value = []
    permissions.value = []
    return
  }

  loading.value = true
  try {
    const payload = await getTenantRbac(selectedTenantId.value)
    roles.value = payload.roles
    members.value = payload.members
    permissions.value = payload.permissions
    newMember.value.roleId = payload.roles[0]?.id ?? ''
    syncRoleDrafts(payload.roles)
    syncMemberDrafts(payload.members)
    await scrollFocusedRoleIntoView()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load role permissions.' })
  } finally {
    loading.value = false
  }
}

async function createRole() {
  if (!selectedTenantId.value || !newRole.value.name.trim()) return

  creatingRole.value = true
  try {
    const role = await createTenantRole(selectedTenantId.value, {
      name: newRole.value.name.trim(),
      slug: newRole.value.slug.trim() || undefined,
      description: newRole.value.description.trim() || undefined,
    })

    roles.value = [...roles.value, role].sort((a, b) => a.name.localeCompare(b.name))
    syncRoleDrafts(roles.value)
    newRole.value = { name: '', slug: '', description: '' }
    success('Role created.', {
      description: `${role.name} is ready for permission assignment.`,
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to create role.' })
  } finally {
    creatingRole.value = false
  }
}

async function createAccount() {
  if (!isSystemAdmin.value) return
  if (!newAccount.value.name.trim() || !newAccount.value.adminName.trim() || !newAccount.value.email.trim()) return
  if (newAccount.value.type === 'municipal_hall' && !newAccount.value.locationArea.trim()) return

  creatingAccount.value = true
  try {
    const response = await createManagedAccount({
      type: newAccount.value.type,
      name: newAccount.value.name.trim(),
      adminName: newAccount.value.adminName.trim(),
      email: newAccount.value.email.trim(),
      plan: newAccount.value.plan,
      billingCycle: newAccount.value.billingCycle,
      ...(newAccount.value.type === 'municipal_hall'
        ? {
            lguType: newAccount.value.lguType,
            locationArea: newAccount.value.locationArea.trim(),
          }
        : {}),
    })

    tenants.value = [...tenants.value, response.account].sort((a, b) => a.name.localeCompare(b.name))
    selectedTenantId.value = response.account.id
    newAccount.value = {
      type: 'company',
      name: '',
      adminName: '',
      email: '',
      plan: 'free',
      billingCycle: 'monthly',
      lguType: 'municipality',
      locationArea: '',
    }

    success('Account created.', {
      description: `Temporary password: ${response.temporaryPassword}`,
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to create account.' })
  } finally {
    creatingAccount.value = false
  }
}

async function createMember() {
  if (!selectedTenantId.value) return
  if (!newMember.value.name.trim() || !newMember.value.email.trim() || !newMember.value.roleId) return

  creatingMember.value = true
  try {
    const response = await createTenantMember(selectedTenantId.value, {
      name: newMember.value.name.trim(),
      email: newMember.value.email.trim(),
      roleId: newMember.value.roleId,
      title: newMember.value.title.trim() || undefined,
    })

    members.value = [...members.value, response.member].sort((a, b) => a.user.name.localeCompare(b.user.name))
    const tenant = tenants.value.find((item) => item.id === selectedTenantId.value)
    if (tenant) {
      tenant.memberCount += 1
    }

    newMember.value = {
      name: '',
      email: '',
      roleId: roles.value[0]?.id ?? '',
      title: '',
    }

    success('User created.', {
      description: `Temporary password: ${response.temporaryPassword}`,
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to create account user.' })
  } finally {
    creatingMember.value = false
  }
}

async function removeRole(role: TenantRole) {
  if (!selectedTenantId.value) return
  if (!window.confirm(`Delete the role "${role.name}"?`)) return

  deletingRoleId.value = role.id
  try {
    await deleteTenantRole(selectedTenantId.value, role.id)
    roles.value = roles.value.filter((item) => item.id !== role.id)
    const { [role.id]: _removed, ...remainingDrafts } = rolePermissionDrafts.value
    rolePermissionDrafts.value = remainingDrafts
    success('Role deleted.', {
      description: `${role.name} has been removed from this tenant.`,
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to delete role.' })
  } finally {
    deletingRoleId.value = null
  }
}

async function saveAllPermissionChanges() {
  if (!selectedTenantId.value || !hasAnyPendingPermissionChanges.value) return

  const pendingRoles = [...rolesWithPendingPermissionChanges.value]

  try {
    for (const role of pendingRoles) {
      savingRoleId.value = role.id
      const updatedRole = await syncTenantRolePermissions(selectedTenantId.value, role.id, roleDraftPermissions(role))
      roles.value = roles.value.map((item) => (item.id === role.id ? updatedRole : item))
      rolePermissionDrafts.value = {
        ...rolePermissionDrafts.value,
        [role.id]: [...updatedRole.permissions],
      }
    }

    success('Permissions updated.', {
      description: `${pendingRoles.length} role${pendingRoles.length === 1 ? '' : 's'} saved successfully.`,
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to save permission changes.' })
  } finally {
    savingRoleId.value = null
  }
}

function discardAllPermissionChanges() {
  const nextDrafts: Record<string, string[]> = {}
  for (const role of roles.value) {
    nextDrafts[role.id] = [...role.permissions]
  }
  rolePermissionDrafts.value = nextDrafts
}

async function saveMemberAssignments() {
  if (!selectedTenantId.value || !hasPendingMemberRoleChanges.value) return

  savingMemberAssignments.value = true
  try {
    for (const member of members.value) {
      const nextRoleId = memberRoleDrafts.value[member.id] ?? member.role.id
      if (nextRoleId === member.role.id) continue

      const updatedMember = await updateTenantMemberRole(selectedTenantId.value, member.id, nextRoleId)
      members.value = members.value.map((item) => (item.id === member.id ? updatedMember : item))
      memberRoleDrafts.value = {
        ...memberRoleDrafts.value,
        [member.id]: updatedMember.role.id,
      }
    }

    success('User roles updated.', {
      description: 'Account user assignments were saved.',
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to save user role assignments.' })
  } finally {
    savingMemberAssignments.value = false
  }
}

function discardMemberAssignments() {
  const nextDrafts: Record<string, string> = {}
  for (const member of members.value) {
    nextDrafts[member.id] = member.role.id
  }
  memberRoleDrafts.value = nextDrafts
}

function goToPermissions(roleId?: string) {
  router.push({
    name: 'tenant-permission-assignment',
    query: roleId ? { role: roleId } : undefined,
  })
}

function goToRoles() {
  router.push({ name: 'tenant-role-management' })
}

async function scrollFocusedRoleIntoView() {
  if (!focusedRoleId.value || props.mode !== 'permissions') return
  await nextTick()
  document.getElementById(`role-row-${focusedRoleId.value}`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
}

function syncFocusedRoleFromRoute() {
  focusedRoleId.value = typeof route.query.role === 'string' ? route.query.role : ''
}

onMounted(async () => {
  syncFocusedRoleFromRoute()
  await loadTenants()
})

watch(
  () => route.query.role,
  () => {
    syncFocusedRoleFromRoute()
    void scrollFocusedRoleIntoView()
  },
)

watch(selectedTenantId, async (next, previous) => {
  if (next && next !== previous) {
    await loadTenantRbac()
  }
})
</script>

<template>
  <MainLayout :role="currentRole" :title="pageTitle" :active-item="activeItem" :nav-items="navItems">
    <div class="space-y-5">
      <section class="rounded-2xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-4 border-b border-border px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
          <div class="space-y-2">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                <ShieldCheck class="h-5 w-5" />
              </div>
              <div>
                <h2 class="text-xl font-semibold tracking-tight text-foreground">{{ pageTitle }}</h2>
                <p class="text-sm text-muted-foreground">{{ pageSubtitle }}</p>
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <Button size="sm" :variant="props.mode === 'roles' ? 'default' : 'outline'" @click="goToRoles">
                Roles
              </Button>
              <Button size="sm" :variant="props.mode === 'permissions' ? 'default' : 'outline'" @click="goToPermissions()">
                Permissions
              </Button>
            </div>
          </div>

          <div class="flex w-full flex-col gap-3 sm:max-w-md">
            <div v-if="showAccountSelector">
              <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Account</label>
              <Select v-model="selectedTenantId" class="bg-card">
                <option value="" disabled>Select an account</option>
                <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                  {{ tenant.name }} · {{ tenant.type }}
                </option>
              </Select>
            </div>
            <div v-else-if="selectedTenant" class="rounded-xl border border-border bg-muted px-3 py-2 text-sm text-muted-foreground">
              <span class="font-medium text-foreground">{{ selectedTenant.name }}</span>
              <span class="text-muted-foreground"> · {{ selectedTenant.memberCount }} users · {{ roles.length }} roles</span>
            </div>
            <div class="relative">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                v-model="search"
                class="pl-9"
                :placeholder="props.mode === 'permissions' ? 'Search roles or permissions...' : 'Search roles...'"
              />
            </div>
          </div>
        </div>

        <div class="p-5 sm:p-6">
          <div v-if="!canManage" class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900">
            Your account does not have access to RBAC management.
          </div>

          <div v-else-if="loading && !roles.length && !isSystemAdmin" class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground">
            Loading access workspace...
          </div>

          <div v-else-if="!selectedTenant && !isSystemAdmin" class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground">
            No manageable account was found for this user.
          </div>

          <div v-else class="space-y-5">
            <Card v-if="isSystemAdmin" class="border-border shadow-none">
              <CardHeader class="pb-2">
                <h3 class="text-base font-semibold text-foreground">Create Account</h3>
                <p class="text-sm text-muted-foreground">Provision a company, municipal hall, or school with an admin user.</p>
              </CardHeader>
              <CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-muted-foreground">Type</label>
                  <Select v-model="newAccount.type" class="bg-card">
                    <option value="company">Company</option>
                    <option value="municipal_hall">Municipal Hall</option>
                    <option value="school">School</option>
                  </Select>
                </div>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-muted-foreground">Account name</label>
                  <Input
                    v-model="newAccount.name"
                    :placeholder="
                      newAccount.type === 'school'
                        ? 'School name'
                        : newAccount.type === 'municipal_hall'
                          ? 'Official municipal / LGU office name'
                          : 'Company name'
                    "
                  />
                </div>
                <template v-if="newAccount.type === 'municipal_hall'">
                  <div>
                    <label class="mb-1.5 block text-sm font-medium text-muted-foreground">LGU type</label>
                    <Select v-model="newAccount.lguType" class="bg-card">
                      <option value="municipality">Municipality</option>
                      <option value="city">City</option>
                      <option value="other_office">Other office</option>
                    </Select>
                  </div>
                  <div>
                    <label class="mb-1.5 block text-sm font-medium text-muted-foreground">City / municipality (Cavite)</label>
                    <Input v-model="newAccount.locationArea" placeholder="e.g. Naic" />
                  </div>
                </template>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-muted-foreground">Admin name</label>
                  <Input v-model="newAccount.adminName" placeholder="Admin full name" />
                </div>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-muted-foreground">Admin email</label>
                  <Input v-model="newAccount.email" type="email" placeholder="admin@example.com" />
                </div>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-muted-foreground">Plan</label>
                  <Select v-model="newAccount.plan" class="bg-card">
                    <option value="free">Free</option>
                    <option value="standard">Standard</option>
                    <option value="premium">Premium</option>
                  </Select>
                </div>
                <div class="flex items-end">
                  <Button
                    class="w-full gap-2"
                    :disabled="
                      creatingAccount ||
                      !newAccount.name.trim() ||
                      !newAccount.adminName.trim() ||
                      !newAccount.email.trim() ||
                      (newAccount.type === 'municipal_hall' && !newAccount.locationArea.trim())
                    "
                    @click="createAccount"
                  >
                    <Plus class="h-4 w-4" />
                    {{ creatingAccount ? 'Creating...' : 'Create Account' }}
                  </Button>
                </div>
              </CardContent>
            </Card>

            <template v-if="selectedTenant && props.mode === 'roles'">
              <div class="grid gap-5 xl:grid-cols-[320px_1fr]">
                <div class="space-y-4 rounded-2xl border border-border bg-muted/70 p-4">
                  <div>
                    <h3 class="text-base font-semibold text-foreground">Roles</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Scoped to this account only.</p>
                  </div>

                  <div class="space-y-3 rounded-xl border border-border bg-card p-3">
                    <Input v-model="newRole.name" placeholder="Role name" />
                    <Input v-model="newRole.slug" placeholder="Slug (optional)" />
                    <textarea
                      v-model="newRole.description"
                      rows="2"
                      class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      placeholder="Short description (optional)"
                    />
                    <Button class="w-full gap-2" size="sm" :disabled="creatingRole || !newRole.name.trim()" @click="createRole">
                      <Plus class="h-4 w-4" />
                      {{ creatingRole ? 'Creating...' : 'Create role' }}
                    </Button>
                  </div>

                  <div class="space-y-2">
                    <div
                      v-for="role in filteredRoles"
                      :key="role.id"
                      class="rounded-xl border border-border bg-card p-3"
                    >
                      <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                          <p class="truncate font-medium text-foreground">{{ role.name }}</p>
                          <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ role.memberCount }} users · {{ role.permissions.length }} permissions
                          </p>
                        </div>
                        <button
                          type="button"
                          class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-red-950/40 hover:text-red-300 disabled:opacity-40"
                          :disabled="deletingRoleId === role.id || role.memberCount > 0"
                          :title="role.memberCount > 0 ? 'Reassign users before deleting' : 'Delete role'"
                          @click="removeRole(role)"
                        >
                          <Trash2 class="h-4 w-4" />
                        </button>
                      </div>
                      <Button class="mt-3 w-full" size="sm" variant="outline" @click="goToPermissions(role.id)">
                        Edit permissions
                      </Button>
                    </div>
                    <p v-if="!filteredRoles.length" class="px-1 py-6 text-center text-sm text-muted-foreground">
                      No roles matched your search.
                    </p>
                  </div>
                </div>

                <div class="space-y-4">
                  <div class="rounded-2xl border border-border bg-card p-4">
                    <div class="mb-3 flex items-center gap-2">
                      <Users class="h-4 w-4 text-muted-foreground" />
                      <h3 class="text-base font-semibold text-foreground">Members</h3>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                      <Input v-model="newMember.name" placeholder="Full name" />
                      <Input v-model="newMember.email" type="email" placeholder="Email" />
                      <Select v-model="newMember.roleId" class="bg-card">
                        <option value="" disabled>Select role</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">
                          {{ role.name }}
                        </option>
                      </Select>
                      <Button
                        class="gap-2"
                        :disabled="creatingMember || !newMember.name.trim() || !newMember.email.trim() || !newMember.roleId"
                        @click="createMember"
                      >
                        <Plus class="h-4 w-4" />
                        {{ creatingMember ? 'Creating...' : 'Add user' }}
                      </Button>
                    </div>
                  </div>

                  <div class="isolate overflow-hidden rounded-2xl border border-border bg-card">
                    <div class="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                      <div>
                        <p class="font-medium text-foreground">Role assignments</p>
                        <p class="text-sm text-muted-foreground">Change a member’s role, then save.</p>
                      </div>
                      <div class="flex gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          :disabled="savingMemberAssignments || !hasPendingMemberRoleChanges"
                          @click="discardMemberAssignments"
                        >
                          Discard
                        </Button>
                        <Button
                          size="sm"
                          :disabled="savingMemberAssignments || !hasPendingMemberRoleChanges"
                          @click="saveMemberAssignments"
                        >
                          {{ savingMemberAssignments ? 'Saving...' : 'Save' }}
                        </Button>
                      </div>
                    </div>

                    <div v-if="members.length" class="overflow-x-auto">
                      <table class="min-w-full text-sm">
                        <thead class="bg-muted text-left text-xs uppercase tracking-wide text-muted-foreground">
                          <tr>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                          <tr v-for="member in members" :key="member.id">
                            <td class="px-4 py-3">
                              <p class="font-medium text-foreground">{{ member.user.name }}</p>
                              <p class="text-xs text-muted-foreground">{{ member.user.email }}</p>
                            </td>
                            <td class="px-4 py-3">
                              <Select
                                :model-value="memberRoleDrafts[member.id] ?? member.role.id"
                                class="min-w-[180px] bg-card"
                                @update:model-value="memberRoleDrafts = { ...memberRoleDrafts, [member.id]: $event }"
                              >
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                  {{ role.name }}
                                </option>
                              </Select>
                              <p
                                v-if="(memberRoleDrafts[member.id] ?? member.role.id) !== member.role.id"
                                class="mt-1 text-xs font-medium text-amber-700"
                              >
                                Unsaved
                              </p>
                            </td>
                            <td class="px-4 py-3">
                              <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                {{ member.status }}
                              </span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else class="px-4 py-10 text-center text-sm text-muted-foreground">
                      No users in this account yet.
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <template v-else-if="selectedTenant && props.mode === 'permissions'">
              <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-muted px-4 py-3">
                <p class="text-sm text-muted-foreground">
                  <span v-if="hasAnyPendingPermissionChanges" class="font-medium text-amber-700">
                    {{ rolesWithPendingPermissionChanges.length }} role{{ rolesWithPendingPermissionChanges.length === 1 ? '' : 's' }} with unsaved changes.
                  </span>
                  <span v-else>Toggle cells, then save once.</span>
                </p>
                <div class="flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    :disabled="!!savingRoleId || !hasAnyPendingPermissionChanges"
                    @click="discardAllPermissionChanges"
                  >
                    Discard
                  </Button>
                  <Button
                    size="sm"
                    :disabled="!!savingRoleId || !hasAnyPendingPermissionChanges"
                    @click="saveAllPermissionChanges"
                  >
                    {{ savingRoleId ? 'Saving...' : 'Save changes' }}
                  </Button>
                </div>
              </div>

              <div class="isolate overflow-hidden rounded-2xl border border-border bg-card">
                <div class="max-h-[min(70vh,720px)] overflow-auto">
                  <table class="w-max min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                      <tr>
                        <th
                          class="sticky left-0 top-0 z-30 min-w-[180px] border-b border-r border-border bg-muted px-3 py-3 text-left font-semibold text-muted-foreground"
                        >
                          Role
                        </th>
                        <template v-for="category in permissionCategories" :key="category.id">
                          <th
                            :colspan="category.collapsed ? 1 : category.permissions.length"
                            class="sticky top-0 z-20 border-b border-border bg-muted px-2 py-2 text-left"
                          >
                            <button
                              type="button"
                              class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground hover:bg-muted"
                              @click="toggleCategory(category.id)"
                            >
                              <ChevronDown
                                class="h-3.5 w-3.5 transition"
                                :class="category.collapsed ? '-rotate-90' : ''"
                              />
                              {{ category.label }}
                              <span class="font-normal text-muted-foreground">({{ category.permissions.length }})</span>
                            </button>
                          </th>
                        </template>
                      </tr>
                      <tr>
                        <th class="sticky left-0 top-[45px] z-30 border-b border-r border-border bg-card px-3 py-2" />
                        <template v-for="category in permissionCategories" :key="`${category.id}-labels`">
                          <template v-if="!category.collapsed">
                            <th
                              v-for="permission in category.permissions"
                              :key="permission.key"
                              class="sticky top-[45px] z-20 min-w-[120px] max-w-[140px] border-b border-border bg-card px-2 py-2 text-left align-bottom"
                              :title="permission.description || permission.key"
                            >
                              <p class="text-xs font-medium capitalize leading-4 text-foreground">
                                {{ permissionLabel(permission) }}
                              </p>
                            </th>
                          </template>
                          <th
                            v-else
                            class="sticky top-[45px] z-20 min-w-[88px] border-b border-border bg-card px-2 py-2 text-xs text-muted-foreground"
                          >
                            Hidden
                          </th>
                        </template>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="role in filteredRoles"
                        :id="`role-row-${role.id}`"
                        :key="role.id"
                        class="group"
                        :class="focusedRoleId === role.id ? 'bg-accent' : 'hover:bg-muted/80'"
                      >
                        <td
                          class="sticky left-0 z-10 border-b border-r border-border bg-card px-3 py-3 align-middle group-hover:bg-muted"
                          :class="focusedRoleId === role.id ? 'bg-accent' : ''"
                        >
                          <p class="font-medium text-foreground">{{ role.name }}</p>
                          <p v-if="hasPendingPermissionChanges(role)" class="mt-1 text-xs font-medium text-amber-700">
                            Unsaved
                          </p>
                        </td>
                        <template v-for="category in permissionCategories" :key="`${role.id}-${category.id}`">
                          <template v-if="!category.collapsed">
                            <td
                              v-for="permission in category.permissions"
                              :key="`${role.id}-${permission.key}`"
                              class="border-b border-border px-2 py-3 text-center align-middle"
                              :title="permission.description || permission.key"
                            >
                              <Switch
                                :model-value="roleHasPermission(role, permission.key)"
                                :disabled="savingRoleId === role.id"
                                @update:model-value="togglePermissionDraft(role, permission.key, $event)"
                              />
                            </td>
                          </template>
                          <td v-else class="border-b border-border px-2 py-3 text-center text-xs text-muted-foreground">
                            —
                          </td>
                        </template>
                      </tr>
                      <tr v-if="!filteredRoles.length">
                        <td :colspan="1 + filteredPermissions.length" class="px-4 py-10 text-center text-sm text-muted-foreground">
                          No roles matched your search.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>

            <div
              v-else-if="isSystemAdmin && !selectedTenant"
              class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground"
            >
              Create an account above, then select it to manage roles and permissions.
            </div>
          </div>
        </div>
      </section>
    </div>
  </MainLayout>
</template>
