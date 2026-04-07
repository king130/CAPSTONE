<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Building2, Plus, School, ShieldCheck, Trash2, Users } from 'lucide-vue-next'

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
import { membershipForActiveOrganization } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  mode: 'roles' | 'permissions'
}>()

const authStore = useAuthStore()
const router = useRouter()
const { success, error } = useToast()

const loading = ref(false)
const creatingRole = ref(false)
const creatingAccount = ref(false)
const deletingRoleId = ref<string | null>(null)
const savingRoleId = ref<string | null>(null)
const savingMemberAssignments = ref(false)
const tenants = ref<TenantSummary[]>([])
const roles = ref<TenantRole[]>([])
const members = ref<TenantMember[]>([])
const permissions = ref<TenantPermission[]>([])
const selectedTenantId = ref('')
const search = ref('')
const rolePermissionDrafts = ref<Record<string, string[]>>({})
const memberRoleDrafts = ref<Record<string, string>>({})
const newRole = ref({
  name: '',
  slug: '',
  description: '',
})
const newAccount = ref({
  type: 'company' as 'company' | 'school',
  name: '',
  adminName: '',
  email: '',
  plan: 'free',
  billingCycle: 'monthly',
})
const newMember = ref({
  name: '',
  email: '',
  roleId: '',
  title: '',
  password: '',
  passwordConfirmation: '',
})

const currentRole = computed<LayoutRole>(() => {
  const role = authStore.user?.role
  if (role === 'admin' || role === 'company' || role === 'school' || role === 'student') {
    return role
  }

  return 'company'
})

const pageTitle = computed(() => (props.mode === 'roles' ? 'Role Management' : 'Permission Assignment'))
const activeItem = computed(() => (props.mode === 'roles' ? 'tenant-role-management' : 'tenant-permission-assignment'))
const selectedTenant = computed(() => tenants.value.find((tenant) => tenant.id === selectedTenantId.value) ?? null)
const isSystemAdmin = computed(() =>
  authStore.user?.role === 'admin' ||
  authStore.user?.platformRole?.slug === 'system_admin' ||
  authStore.user?.platformRole?.permissions?.includes('platform.manage_users') === true,
)
const canAccessTenantRbacPage = computed(() => {
  if (isSystemAdmin.value) {
    return true
  }

  if (authStore.user?.isOrganizationOwner) {
    return true
  }

  const permissions = membershipForActiveOrganization(authStore.user)?.permissions ?? []
  return (
    permissions.includes('manage_roles') ||
    permissions.includes('manage_permissions') ||
    permissions.includes('org.manage_roles') ||
    permissions.includes('manage_users') ||
    permissions.includes('org.manage_members')
  )
})

/** Edit permission matrix (toggles): role admins only — not manage_users / org.manage_members alone. */
const canEditPermissionMatrix = computed(() => {
  if (isSystemAdmin.value) {
    return true
  }

  if (authStore.user?.isOrganizationOwner) {
    return true
  }

  const permissions = membershipForActiveOrganization(authStore.user)?.permissions ?? []
  return (
    permissions.includes('manage_roles') ||
    permissions.includes('manage_permissions') ||
    permissions.includes('org.manage_roles')
  )
})

const navItems = computed<LayoutNavItem[]>(() => {
  const base = defaultNavItems[currentRole.value]
  if (canEditPermissionMatrix.value) {
    return base
  }
  return base.filter((item) => item.key !== 'tenant-permission-assignment')
})

const filteredRoles = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return roles.value

  return roles.value.filter((role) => {
    const haystack = [role.name, role.slug, role.description || '', ...role.permissions].join(' ').toLowerCase()
    return haystack.includes(query)
  })
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

function permissionLabel(permission: TenantPermission) {
  return permission.key.replace(/_/g, ' ')
}

function permissionDescription(permission: TenantPermission) {
  return permission.description || `Allows users with this role to ${permissionLabel(permission)}.`
}

function roleHasPermission(role: TenantRole, permissionKey: string) {
  const draft = rolePermissionDrafts.value[role.id]
  const source = draft ?? role.permissions
  return source.includes(permissionKey)
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
  rolePermissionDrafts.value = {
    ...rolePermissionDrafts.value,
    [role.id]: enabled
      ? Array.from(new Set([...current, permissionKey]))
      : current.filter((item) => item !== permissionKey),
  }
}

function resetPermissionDraft(role: TenantRole) {
  rolePermissionDrafts.value = {
    ...rolePermissionDrafts.value,
    [role.id]: [...role.permissions],
  }
}

async function loadTenants() {
  if (!canAccessTenantRbacPage.value) return

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
    permissions.value = Array.isArray(payload.permissions) ? payload.permissions : []
    newMember.value.roleId = payload.roles[0]?.id ?? ''
    syncRoleDrafts(payload.roles)
    syncMemberDrafts(payload.members)
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

  creatingAccount.value = true
  try {
    const response = await createManagedAccount({
      type: newAccount.value.type,
      name: newAccount.value.name.trim(),
      adminName: newAccount.value.adminName.trim(),
      email: newAccount.value.email.trim(),
      plan: newAccount.value.plan,
      billingCycle: newAccount.value.billingCycle,
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

function validateNewMemberPassword(): string | null {
  const p = newMember.value.password.trim()
  const c = newMember.value.passwordConfirmation.trim()
  if (!p && !c) return null
  if (p.length < 8 || c.length < 8) {
    return 'Password must be at least 8 characters.'
  }
  if (p !== c) {
    return 'Password and confirmation do not match.'
  }
  return null
}

async function createMember() {
  if (!selectedTenantId.value) return
  if (!newMember.value.name.trim() || !newMember.value.email.trim() || !newMember.value.roleId) return

  const pwdErr = validateNewMemberPassword()
  if (pwdErr) {
    error(pwdErr)
    return
  }

  creatingAccount.value = true
  try {
    const useSetPassword = Boolean(newMember.value.password.trim())
    const response = await createTenantMember(selectedTenantId.value, {
      name: newMember.value.name.trim(),
      email: newMember.value.email.trim(),
      roleId: newMember.value.roleId,
      title: newMember.value.title.trim() || undefined,
      password: useSetPassword ? newMember.value.password : undefined,
      passwordConfirmation: useSetPassword ? newMember.value.passwordConfirmation : undefined,
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
      password: '',
      passwordConfirmation: '',
    }

    success('Account created.', {
      description: response.temporaryPassword
        ? `Temporary password: ${response.temporaryPassword}`
        : 'The user can sign in with the password you set.',
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to create account user.' })
  } finally {
    creatingAccount.value = false
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

onMounted(async () => {
  await loadTenants()
  if (
    props.mode === 'permissions' &&
    canAccessTenantRbacPage.value &&
    !canEditPermissionMatrix.value
  ) {
    await router.replace({ name: 'tenant-role-management' })
  }
})

watch(selectedTenantId, async (next, previous) => {
  if (next && next !== previous) {
    await loadTenantRbac()
  }
})

watch(
  () => props.mode,
  async (m) => {
    if (m === 'permissions' && canAccessTenantRbacPage.value && !canEditPermissionMatrix.value) {
      await router.replace({ name: 'tenant-role-management' })
    }
  },
)
</script>

<template>
  <MainLayout :role="currentRole" :title="pageTitle" :active-item="activeItem" :nav-items="navItems">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-3">
              <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                  <ShieldCheck class="h-5 w-5" />
                </div>
                <div>
                  <h2 class="text-2xl font-semibold text-slate-950">{{ pageTitle }}</h2>
                  <p class="text-sm text-slate-600">
                    Manage account-specific roles and permission toggles for companies and schools.
                  </p>
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button :variant="props.mode === 'roles' ? 'default' : 'outline'" @click="goToRoles">
                  Role Management
                </Button>
                <Button
                  v-if="canEditPermissionMatrix"
                  :variant="props.mode === 'permissions' ? 'default' : 'outline'"
                  @click="goToPermissions()"
                >
                  Permission Assignment
                </Button>
              </div>
            </div>

            <div class="grid gap-3 sm:min-w-[320px]">
              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Account</label>
                <Select v-model="selectedTenantId" :class="'bg-white'">
                  <option value="" disabled>Select an account</option>
                  <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                    {{ tenant.name }} · {{ tenant.type }}
                  </option>
                </Select>
              </div>
              <Input v-model="search" placeholder="Search roles or permissions..." />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="!canAccessTenantRbacPage" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            Your account does not have access to account RBAC management.
          </div>

          <div v-else-if="loading && !roles.length && !isSystemAdmin" class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
            Loading RBAC workspace...
          </div>

          <div v-else-if="!selectedTenant && !isSystemAdmin" class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
            No manageable account was found for this user.
          </div>

          <div v-else class="space-y-6">
            <div v-if="isSystemAdmin" class="grid gap-6 xl:grid-cols-[420px,1fr]">
              <Card class="border-border/70 shadow-none">
                <CardHeader>
                  <h3 class="text-lg font-semibold text-slate-950">Create Account</h3>
                  <p class="text-sm text-slate-600">
                    Create a company or school account with its own admin, subscription, and RBAC roles.
                  </p>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Account type</label>
                    <Select v-model="newAccount.type" :class="'bg-white'">
                      <option value="company">Company</option>
                      <option value="school">School</option>
                    </Select>
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Account name</label>
                    <Input v-model="newAccount.name" :placeholder="newAccount.type === 'school' ? 'School name' : 'Company name'" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Primary admin</label>
                    <Input v-model="newAccount.adminName" placeholder="Admin full name" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Admin email</label>
                    <Input v-model="newAccount.email" type="email" placeholder="admin@example.com" />
                  </div>
                  <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label class="mb-2 block text-sm font-medium text-slate-700">Plan</label>
                      <Select v-model="newAccount.plan" :class="'bg-white'">
                        <option value="free">Free</option>
                        <option value="standard">Standard</option>
                        <option value="premium">Premium</option>
                      </Select>
                    </div>
                    <div>
                      <label class="mb-2 block text-sm font-medium text-slate-700">Billing</label>
                      <Select v-model="newAccount.billingCycle" :class="'bg-white'">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                      </Select>
                    </div>
                  </div>
                  <Button
                    class="w-full gap-2"
                    :disabled="creatingAccount || !newAccount.name.trim() || !newAccount.adminName.trim() || !newAccount.email.trim()"
                    @click="createAccount"
                  >
                    <Plus class="h-4 w-4" />
                    {{ creatingAccount ? 'Creating...' : 'Create Account' }}
                  </Button>
                </CardContent>
              </Card>

              <Card class="border-border/70 shadow-none">
                <CardHeader>
                  <h3 class="text-lg font-semibold text-slate-950">What Happens Next</h3>
                  <p class="text-sm text-slate-600">
                    This uses the same account provisioning path as subscription-based organization signup.
                  </p>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                  <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Admin user</p>
                    <p class="mt-2 text-sm text-slate-600">A primary admin account is created with a temporary password.</p>
                  </div>
                  <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Subscription</p>
                    <p class="mt-2 text-sm text-slate-600">Plan and billing cycle are created immediately for the account.</p>
                  </div>
                  <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Scoped roles</p>
                    <p class="mt-2 text-sm text-slate-600">Default account roles are seeded and ready for permission assignment.</p>
                  </div>
                </CardContent>
              </Card>
            </div>

            <div v-if="selectedTenant" class="grid gap-4 md:grid-cols-3">
              <Card class="border-border/70 shadow-none">
                <CardContent class="p-5">
                  <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                      <component :is="tenantIcon(selectedTenant.type)" class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="text-sm text-slate-500">Account</p>
                      <p class="text-lg font-semibold text-slate-950">{{ selectedTenant.name }}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
              <Card class="border-border/70 shadow-none">
                <CardContent class="p-5">
                  <p class="text-sm text-slate-500">Users</p>
                  <div class="mt-3 flex items-center gap-3">
                    <Users class="h-5 w-5 text-sky-700" />
                    <p class="text-2xl font-semibold text-slate-950">{{ selectedTenant.memberCount }}</p>
                  </div>
                </CardContent>
              </Card>
              <Card class="border-border/70 shadow-none">
                <CardContent class="p-5">
                  <p class="text-sm text-slate-500">Roles</p>
                  <div class="mt-3 flex items-center gap-3">
                    <ShieldCheck class="h-5 w-5 text-emerald-700" />
                    <p class="text-2xl font-semibold text-slate-950">{{ roles.length }}</p>
                  </div>
                </CardContent>
              </Card>
            </div>

            <div v-else-if="isSystemAdmin" class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
              Create your first account above, then select it to manage roles and permissions.
            </div>

            <div v-if="selectedTenant && props.mode === 'roles'" class="grid gap-6 xl:grid-cols-[360px,1fr]">
              <Card class="border-border/70 shadow-none">
                <CardHeader>
                  <h3 class="text-lg font-semibold text-slate-950">Create Account Role</h3>
                  <p class="text-sm text-slate-600">
                    Roles are scoped to <strong>{{ selectedTenant.name }}</strong> only.
                  </p>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Role name</label>
                    <Input v-model="newRole.name" placeholder="Manager, HR, Registrar..." />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Slug</label>
                    <Input v-model="newRole.slug" placeholder="Optional slug" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Description</label>
                    <textarea
                      v-model="newRole.description"
                      rows="4"
                      class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      placeholder="What is this role responsible for?"
                    />
                  </div>
                  <Button class="w-full gap-2" :disabled="creatingRole || !newRole.name.trim()" @click="createRole">
                    <Plus class="h-4 w-4" />
                    {{ creatingRole ? 'Creating...' : 'Create Role' }}
                  </Button>
                </CardContent>
              </Card>

              <div class="space-y-4">
                <Card
                  v-for="role in filteredRoles"
                  :key="role.id"
                  class="border-border/70 shadow-none"
                >
                  <CardContent class="flex flex-col gap-4 p-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                      <div>
                        <p class="text-lg font-semibold text-slate-950">{{ role.name }}</p>
                        <p class="text-sm text-slate-500">{{ role.slug }}</p>
                      </div>
                      <p class="text-sm text-slate-600">{{ role.description || 'No description added yet.' }}</p>
                      <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                          {{ role.memberCount }} assigned users
                        </span>
                        <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-800">
                          {{ role.permissions.length }} permissions
                        </span>
                      </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                      <Button variant="outline" @click="goToPermissions(role.id)">
                        Edit Permissions
                      </Button>
                      <Button
                        variant="outline"
                        class="gap-2 text-red-600 hover:text-red-600"
                        :disabled="deletingRoleId === role.id || role.memberCount > 0"
                        @click="removeRole(role)"
                      >
                        <Trash2 class="h-4 w-4" />
                        {{ deletingRoleId === role.id ? 'Deleting...' : 'Delete' }}
                      </Button>
                    </div>
                  </CardContent>
                </Card>

                <Card v-if="!filteredRoles.length" class="border-dashed border-border/70 shadow-none">
                  <CardContent class="py-10 text-center text-sm text-slate-500">
                    No roles matched your current search.
                  </CardContent>
                </Card>
              </div>
            </div>

            <Card v-if="selectedTenant && props.mode === 'roles'" class="border-border/70 shadow-none">
              <CardHeader>
                <h3 class="text-lg font-semibold text-slate-950">Create User Account</h3>
                <p class="text-sm text-slate-600">
                  Create a user directly inside <strong>{{ selectedTenant.name }}</strong> and assign the starting role immediately.
                </p>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Full name</label>
                    <Input v-model="newMember.name" placeholder="Full name" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Email</label>
                    <Input v-model="newMember.email" type="email" placeholder="user@example.com" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Role</label>
                    <Select v-model="newMember.roleId" :class="'bg-white'">
                      <option value="" disabled>Select role</option>
                      <option v-for="role in roles" :key="role.id" :value="role.id">
                        {{ role.name }}
                      </option>
                    </Select>
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Title</label>
                    <Input v-model="newMember.title" placeholder="Optional title" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                    <Input
                      v-model="newMember.password"
                      type="password"
                      autocomplete="new-password"
                      placeholder="Leave blank for auto-generated temp password"
                    />
                    <p class="mt-1 text-xs text-slate-500">Min. 8 characters if you set a password.</p>
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Confirm password</label>
                    <Input
                      v-model="newMember.passwordConfirmation"
                      type="password"
                      autocomplete="new-password"
                      placeholder="Match password above"
                    />
                  </div>
                </div>
                <div class="flex justify-end">
                  <Button
                    class="gap-2"
                    :disabled="creatingAccount || !newMember.name.trim() || !newMember.email.trim() || !newMember.roleId"
                    @click="createMember"
                  >
                    <Plus class="h-4 w-4" />
                    {{ creatingAccount ? 'Creating...' : 'Create User Account' }}
                  </Button>
                </div>
              </CardContent>
            </Card>

            <Card v-if="selectedTenant && props.mode === 'roles'" class="border-border/70 shadow-none">
              <CardHeader>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <h3 class="text-lg font-semibold text-slate-950">Account Users</h3>
                    <p class="text-sm text-slate-600">
                      Assign users in this account to roles, then save the changes.
                    </p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <Button
                      variant="outline"
                      :disabled="savingMemberAssignments || !hasPendingMemberRoleChanges"
                      @click="discardMemberAssignments"
                    >
                      Discard
                    </Button>
                    <Button
                      :disabled="savingMemberAssignments || !hasPendingMemberRoleChanges"
                      @click="saveMemberAssignments"
                    >
                      {{ savingMemberAssignments ? 'Saving...' : 'Save User Roles' }}
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div v-if="members.length" class="overflow-x-auto">
                  <table class="min-w-[720px] w-full text-sm">
                    <thead class="bg-slate-50">
                      <tr class="border-b border-slate-200">
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">User</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Current Role</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Assign Role</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="member in members" :key="member.id" class="border-b border-slate-100 last:border-b-0">
                        <td class="px-4 py-4">
                          <p class="font-semibold text-slate-950">{{ member.user.name }}</p>
                          <p class="mt-1 text-xs text-slate-500">{{ member.user.email }}</p>
                        </td>
                        <td class="px-4 py-4 text-slate-700">{{ member.role.name }}</td>
                        <td class="px-4 py-4">
                          <Select
                            :model-value="memberRoleDrafts[member.id] ?? member.role.id"
                            :class="'bg-white min-w-[220px]'"
                            @update:model-value="memberRoleDrafts = { ...memberRoleDrafts, [member.id]: $event }"
                          >
                            <option v-for="role in roles" :key="role.id" :value="role.id">
                              {{ role.name }}
                            </option>
                          </Select>
                          <p
                            v-if="(memberRoleDrafts[member.id] ?? member.role.id) !== member.role.id"
                            class="mt-2 text-xs font-medium text-amber-700"
                          >
                            Unsaved role change
                          </p>
                        </td>
                        <td class="px-4 py-4">
                          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                            {{ member.status }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
                  No users have been added to this account yet.
                </div>
              </CardContent>
            </Card>

            <div v-if="selectedTenant && props.mode === 'permissions'" class="space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-sm text-slate-600">
                  Toggle permissions across roles, then save everything in one action.
                </p>
                <div class="flex flex-wrap gap-2">
                  <Button
                    variant="outline"
                    :disabled="!canEditPermissionMatrix || !!savingRoleId || !hasAnyPendingPermissionChanges"
                    @click="discardAllPermissionChanges"
                  >
                    Discard All
                  </Button>
                  <Button
                    :disabled="!canEditPermissionMatrix || !!savingRoleId || !hasAnyPendingPermissionChanges"
                    @click="saveAllPermissionChanges"
                  >
                    {{ savingRoleId ? 'Saving...' : 'Save Changes' }}
                  </Button>
                </div>
              </div>

              <div class="overflow-hidden rounded-2xl border border-border bg-white">
              <div class="overflow-x-auto">
                <table class="min-w-[960px] w-full text-sm">
                  <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                      <th class="px-4 py-4 text-left font-semibold text-slate-700">Role</th>
                      <th
                        v-for="permission in permissions"
                        :key="permission.key"
                        class="min-w-[180px] px-4 py-4 text-left font-semibold text-slate-700"
                      >
                        <div class="space-y-1">
                          <p class="capitalize text-slate-900">{{ permissionLabel(permission) }}</p>
                          <p class="text-xs font-normal normal-case text-slate-500">{{ permission.key }}</p>
                        </div>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="role in filteredRoles" :key="role.id" class="border-b border-slate-100 last:border-b-0">
                      <td class="px-4 py-4 align-top">
                        <p class="font-semibold text-slate-950">{{ role.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ role.description || role.slug }}</p>
                        <p v-if="hasPendingPermissionChanges(role)" class="mt-3 text-xs font-medium text-amber-700">
                          Unsaved changes
                        </p>
                      </td>
                      <td
                        v-for="permission in permissions"
                        :key="`${role.id}-${permission.key}`"
                        class="px-4 py-4 align-top"
                      >
                        <div class="space-y-3">
                          <Switch
                            :model-value="roleHasPermission(role, permission.key)"
                            :disabled="!canEditPermissionMatrix || savingRoleId === role.id"
                            @update:model-value="togglePermissionDraft(role, permission.key, $event)"
                          />
                          <p class="text-xs leading-5 text-slate-500">
                            {{ permissionDescription(permission) }}
                          </p>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </MainLayout>
</template>
