<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Swal from 'sweetalert2'
import { useAuthStore } from '@/stores/auth'
import {
  createOrganizationMember,
  getOrganizationAccess,
  updateOrganizationMember,
  type OrganizationAccessMember,
  type OrganizationAccessRole,
} from '@/services/organizationAccess'

const props = defineProps<{
  organizationType: 'school' | 'company'
}>()

const authStore = useAuthStore()
const loading = ref(false)
const creating = ref(false)
const savingMemberId = ref<string | null>(null)
const organizationName = ref('')
const members = ref<OrganizationAccessMember[]>([])
const roles = ref<OrganizationAccessRole[]>([])
const search = ref('')
const permissionSearch = ref('')
const memberDrafts = ref<
  Record<
    string,
    {
      roleId: string
      status: 'active' | 'inactive' | 'pending'
      grantPermissions: string[]
      denyPermissions: string[]
    }
  >
>({})
const newMember = ref({
  name: '',
  email: '',
  roleId: '',
  title: '',
})

const activeMembership = computed(() => authStore.user?.memberships?.[0] ?? null)
const accountOverage = computed(() => {
  const key = props.organizationType === 'school' ? 'school.coordinators' : 'company.accounts'
  return authStore.user?.subscription?.overages?.items?.find((item) => item.key === key) ?? null
})
const canManageAccess = computed(() => {
  const perms = activeMembership.value?.permissions ?? []
  return perms.includes('org.manage_roles') || perms.includes('org.manage_members')
})
const assignableRoles = computed(() => {
  if (props.organizationType === 'school') {
    return roles.value.filter((role) => role.slug === 'intern')
  }

  return roles.value
})

const availablePermissions = computed(() => {
  const keys = new Set<string>()
  for (const role of roles.value) {
    for (const permission of role.permissions) keys.add(permission)
  }
  return Array.from(keys).sort()
})

const filteredMembers = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return members.value
  return members.value.filter((member) => {
    const name = member.user.name.toLowerCase()
    const email = member.user.email.toLowerCase()
    const roleName = member.role.name.toLowerCase()
    return name.includes(query) || email.includes(query) || roleName.includes(query)
  })
})

const filteredPermissions = computed(() => {
  const query = permissionSearch.value.trim().toLowerCase()
  if (!query) return availablePermissions.value
  return availablePermissions.value.filter((permission) => {
    const label = permissionLabel(permission).toLowerCase()
    return permission.toLowerCase().includes(query) || label.includes(query)
  })
})

async function loadAccess() {
  if (!canManageAccess.value) return
  loading.value = true
  try {
    const data = await getOrganizationAccess()
    organizationName.value = data.organization.name
    roles.value = data.roles
    members.value = data.members
    memberDrafts.value = {}
    if (props.organizationType === 'school') {
      newMember.value.roleId = data.roles.find((role) => role.slug === 'intern')?.id ?? ''
    }
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Unable to Load Access Settings',
      text: error instanceof Error ? error.message : 'Could not load organization access data.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    loading.value = false
  }
}

async function createMember() {
  if (!newMember.value.name.trim() || !newMember.value.email.trim() || !newMember.value.roleId) {
    await Swal.fire({
      icon: 'warning',
      title: 'Missing Details',
      text: 'Enter a name, email, and assigned role first.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  const confirmation = await Swal.fire({
    icon: 'question',
    title: 'Create this staff account?',
    html: `
      <div style="text-align:left;display:grid;gap:8px;">
        <div><strong>Name:</strong> ${newMember.value.name.trim()}</div>
        <div><strong>Email:</strong> ${newMember.value.email.trim()}</div>
        <div><strong>Role:</strong> ${roleName(newMember.value.roleId)}</div>
        <div><strong>Title:</strong> ${newMember.value.title.trim() || 'Not set'}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Create Account',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })

  if (!confirmation.isConfirmed) return

  creating.value = true
  try {
    const res = await createOrganizationMember({
      name: newMember.value.name.trim(),
      email: newMember.value.email.trim(),
      roleId: newMember.value.roleId,
      title: newMember.value.title.trim() || null,
    })

    members.value = [...members.value, res.member].sort((a, b) => a.user.name.localeCompare(b.user.name))
    newMember.value = { name: '', email: '', roleId: '', title: '' }
    await authStore.refreshUser()

    await Swal.fire({
      icon: 'success',
      title: 'Member Account Created',
      html: `<strong>Temporary password:</strong> ${res.temporaryPassword}<br><br>The new user will be forced to change it on first login.`,
      confirmButtonColor: '#2563eb',
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Could Not Create Account',
      text: error instanceof Error ? error.message : 'Member creation failed.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    creating.value = false
  }
}

function roleName(roleId: string) {
  return roles.value.find((role) => role.id === roleId)?.name ?? 'Unknown Role'
}

function ensureDraft(member: OrganizationAccessMember) {
  if (!memberDrafts.value[member.id]) {
    memberDrafts.value[member.id] = {
      roleId: member.role.id,
      status: member.status,
      grantPermissions: [...(member.permissionsOverride.grant ?? [])],
      denyPermissions: [...(member.permissionsOverride.deny ?? [])],
    }
  }
  return memberDrafts.value[member.id]!
}

async function updateRole(member: OrganizationAccessMember, roleId: string) {
  if (!roleId) return
  ensureDraft(member).roleId = roleId
}

function togglePermission(member: OrganizationAccessMember, permission: string, enabled: boolean) {
  const draft = ensureDraft(member)
  const currentGrant = draft.grantPermissions
  const currentDeny = draft.denyPermissions

  draft.grantPermissions = enabled
    ? Array.from(new Set([...currentGrant, permission]))
    : currentGrant.filter((item) => item !== permission)

  draft.denyPermissions = enabled
    ? currentDeny.filter((item) => item !== permission)
    : Array.from(new Set([...currentDeny, permission]))
}

function currentRoleId(member: OrganizationAccessMember) {
  return memberDrafts.value[member.id]?.roleId ?? member.role.id
}

function toggleStatus(member: OrganizationAccessMember) {
  const draft = ensureDraft(member)
  draft.status = draft.status === 'active' ? 'inactive' : 'active'
}

function isPermissionEnabled(member: OrganizationAccessMember, permission: string) {
  const draft = memberDrafts.value[member.id]
  if (!draft) {
    return member.effectivePermissions.includes(permission)
  }

  if (draft.denyPermissions.includes(permission)) return false
  if (draft.grantPermissions.includes(permission)) return true
  return member.effectivePermissions.includes(permission)
}

function hasPendingChanges(member: OrganizationAccessMember) {
  const draft = memberDrafts.value[member.id]
  if (!draft) return false

  const originalGrant = [...(member.permissionsOverride.grant ?? [])].sort().join('|')
  const draftGrant = [...draft.grantPermissions].sort().join('|')
  const originalDeny = [...(member.permissionsOverride.deny ?? [])].sort().join('|')
  const draftDeny = [...draft.denyPermissions].sort().join('|')

  return (
    draft.roleId !== member.role.id ||
    draft.status !== member.status ||
    draftGrant !== originalGrant ||
    draftDeny !== originalDeny
  )
}

function discardChanges(member: OrganizationAccessMember) {
  if (!memberDrafts.value[member.id]) return
  memberDrafts.value = {
    ...memberDrafts.value,
    [member.id]: {
      roleId: member.role.id,
      status: member.status,
      grantPermissions: [...(member.permissionsOverride.grant ?? [])],
      denyPermissions: [...(member.permissionsOverride.deny ?? [])],
    },
  }
}

async function saveMemberChanges(member: OrganizationAccessMember) {
  const draft = memberDrafts.value[member.id]
  if (!draft || !hasPendingChanges(member)) return

  const changedItems: string[] = []
  if (draft.roleId !== member.role.id) {
    changedItems.push(`<div><strong>Role:</strong> ${member.role.name} -> ${roleName(draft.roleId)}</div>`)
  }
  if (draft.status !== member.status) {
    changedItems.push(`<div><strong>Status:</strong> ${member.status} -> ${draft.status}</div>`)
  }
  if ([...draft.grantPermissions].sort().join('|') !== [...(member.permissionsOverride.grant ?? [])].sort().join('|')) {
    changedItems.push(`<div><strong>Granted permissions:</strong> updated</div>`)
  }
  if ([...draft.denyPermissions].sort().join('|') !== [...(member.permissionsOverride.deny ?? [])].sort().join('|')) {
    changedItems.push(`<div><strong>Denied permissions:</strong> updated</div>`)
  }

  const confirmation = await Swal.fire({
    icon: 'question',
    title: 'Apply access changes?',
    html: `
      <div style="text-align:left;display:grid;gap:8px;">
        <div><strong>Member:</strong> ${member.user.name}</div>
        ${changedItems.join('')}
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Apply Changes',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })

  if (!confirmation.isConfirmed) return

  savingMemberId.value = member.id
  try {
    const updated = await updateOrganizationMember(member.id, {
      roleId: draft.roleId,
      status: draft.status,
      grantPermissions: draft.grantPermissions,
      denyPermissions: draft.denyPermissions,
    })
    members.value = members.value.map((item) => (item.id === member.id ? updated : item))
    await authStore.refreshUser()
    memberDrafts.value = {
      ...memberDrafts.value,
      [member.id]: {
        roleId: updated.role.id,
        status: updated.status,
        grantPermissions: [...(updated.permissionsOverride.grant ?? [])],
        denyPermissions: [...(updated.permissionsOverride.deny ?? [])],
      },
    }
    await Swal.fire({
      icon: 'success',
      title: 'Changes Saved',
      text: `${member.user.name}'s access settings were updated.`,
      timer: 1200,
      showConfirmButton: false,
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Save Failed',
      text: error instanceof Error ? error.message : 'Could not save member changes.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingMemberId.value = null
  }
}

function permissionLabel(permission: string) {
  return permission.replace(/^org\./, '').replace(/\./g, ' ')
}

function memberTitle(member: OrganizationAccessMember) {
  return member.title?.trim() || 'No title assigned'
}

function currentStatus(member: OrganizationAccessMember) {
  return memberDrafts.value[member.id]?.status ?? member.status
}

function currentRoleName(member: OrganizationAccessMember) {
  return roleName(currentRoleId(member))
}

function permissionCount(member: OrganizationAccessMember) {
  return availablePermissions.value.filter((permission) => isPermissionEnabled(member, permission)).length
}

onMounted(() => {
  loadAccess()
})
</script>

<template>
  <section class="access-manager">
    <div class="section-card">
      <div class="section-head">
        <div>
          <h2 class="section-title">Role Access Control</h2>
          <p class="section-subtitle">
            Manage {{ organizationType }} accounts for {{ organizationName || 'your organization' }} and toggle what each role can access.
          </p>
        </div>
        <div class="toolbar-filters">
          <input v-model="search" type="text" class="search-input" placeholder="Search member or role" />
          <input v-model="permissionSearch" type="text" class="search-input" placeholder="Filter permissions" />
        </div>
      </div>

      <div v-if="canManageAccess" class="create-card">
        <div v-if="accountOverage" class="limit-warning">
          {{ accountOverage.message }}
        </div>
        <div class="create-head">
          <div>
            <div class="create-title">{{ organizationType === 'school' ? 'Add intern account' : 'Add team member' }}</div>
            <div class="create-copy">
              {{ organizationType === 'school' ? 'Schools can create intern accounts only from this workspace.' : 'Create a staff account and assign its starting role.' }}
            </div>
          </div>
        </div>
        <div class="create-grid">
          <input v-model="newMember.name" type="text" class="field-input" placeholder="Full name" />
          <input v-model="newMember.email" type="email" class="field-input" placeholder="Email address" />
          <select v-model="newMember.roleId" class="field-input">
            <option value="">{{ organizationType === 'school' ? 'Select intern role' : 'Select role' }}</option>
            <option v-for="role in assignableRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
          </select>
          <input
            v-model="newMember.title"
            type="text"
            class="field-input"
            :placeholder="organizationType === 'school' ? 'Course / section (optional)' : 'Job title / position'"
          />
        </div>
        <div class="create-actions">
          <button type="button" class="create-btn" :disabled="creating || loading" @click="createMember">
            {{ creating ? 'Creating...' : organizationType === 'school' ? 'Create Intern Account' : 'Create Staff Account' }}
          </button>
        </div>
      </div>

      <div v-if="!canManageAccess" class="empty-state">
        Your account does not have permission to manage role access.
      </div>

      <div v-else-if="loading" class="empty-state">
        Loading access controls...
      </div>

      <div v-else class="matrix-wrap">
        <div v-if="filteredMembers.length === 0" class="empty-state">
          No organization members matched your search.
        </div>
        <div v-else-if="filteredPermissions.length === 0" class="empty-state">
          No permissions matched your filter.
        </div>
        <table v-else class="access-table">
          <thead>
            <tr>
              <th class="sticky-col member-col">Member</th>
              <th class="role-col">Role</th>
              <th class="status-col">Status</th>
              <th v-for="permission in filteredPermissions" :key="permission" class="permission-col">
                <div class="permission-head">
                  <span class="permission-name">{{ permissionLabel(permission) }}</span>
                  <small class="permission-key">{{ permission }}</small>
                </div>
              </th>
              <th class="action-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in filteredMembers" :key="member.id">
              <td class="sticky-col member-cell">
                <div class="member-name">{{ member.user.name }}</div>
                <div class="member-email">{{ member.user.email }}</div>
                <div class="member-meta">
                  <span class="member-title">{{ memberTitle(member) }}</span>
                  <span class="member-count">{{ permissionCount(member) }} permissions</span>
                  <span v-if="hasPendingChanges(member)" class="member-dirty">Unsaved</span>
                </div>
              </td>
              <td>
                <select
                  class="field-input table-select"
                  :value="currentRoleId(member)"
                  :disabled="savingMemberId === member.id"
                  @change="updateRole(member, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                </select>
              </td>
              <td>
                <button
                  type="button"
                  class="status-btn"
                  :class="{ inactive: currentStatus(member) !== 'active' }"
                  :disabled="savingMemberId === member.id"
                  @click="toggleStatus(member)"
                >
                  {{ currentStatus(member) === 'active' ? 'Active' : 'Limited' }}
                </button>
              </td>
              <td v-for="permission in filteredPermissions" :key="`${member.id}-${permission}`" class="permission-cell">
                <input
                  type="checkbox"
                  :checked="isPermissionEnabled(member, permission)"
                  :disabled="savingMemberId === member.id"
                  @change="togglePermission(member, permission, ($event.target as HTMLInputElement).checked)"
                />
              </td>
              <td>
                <div class="member-save-row">
                  <button
                    type="button"
                    class="secondary-btn"
                    :disabled="savingMemberId === member.id || !hasPendingChanges(member)"
                    @click="discardChanges(member)"
                  >
                    Discard
                  </button>
                  <button
                    type="button"
                    class="primary-btn"
                    :disabled="savingMemberId === member.id || !hasPendingChanges(member)"
                    @click="saveMemberChanges(member)"
                  >
                    {{ savingMemberId === member.id ? 'Saving...' : 'Save' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.access-manager {
  padding: 24px;
}

.section-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.section-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
  margin-bottom: 20px;
}

.toolbar-filters {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.create-card {
  margin-bottom: 20px;
  padding: 16px;
  border: 1px solid #dbeafe;
  border-radius: 16px;
  background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
}

.create-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 12px;
}

.limit-warning {
  margin-bottom: 12px;
  border: 1px solid #fcd34d;
  border-radius: 12px;
  background: #fffbeb;
  color: #92400e;
  padding: 12px 14px;
  font-size: 0.92rem;
  font-weight: 600;
}

.create-title {
  font-weight: 800;
  color: #0f172a;
}

.create-copy {
  margin-top: 2px;
  font-size: 0.9rem;
  color: #64748b;
}

.create-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.create-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}

.create-btn {
  border: none;
  background: #2563eb;
  color: #fff;
  border-radius: 12px;
  padding: 10px 16px;
  font-weight: 800;
  cursor: pointer;
}

.create-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.section-title {
  margin: 0 0 6px;
  font-size: 1.35rem;
  color: #0f172a;
}

.section-subtitle {
  margin: 0;
  color: #64748b;
}

.search-input,
.field-input {
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 10px 12px;
  font: inherit;
}

.search-input {
  min-width: 240px;
}

.matrix-wrap {
  overflow-x: auto;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
}

.access-table {
  width: 100%;
  min-width: 960px;
  border-collapse: separate;
  border-spacing: 0;
  background: #fff;
}

.access-table th,
.access-table td {
  padding: 12px 10px;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: top;
}

.access-table th {
  background: #f8fafc;
  color: #334155;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  text-align: left;
}

.access-table tbody tr:last-child td {
  border-bottom: none;
}

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 1;
  background: #fff;
}

.access-table th.sticky-col {
  z-index: 2;
  background: #f8fafc;
}

.member-col {
  min-width: 240px;
}

.role-col {
  min-width: 180px;
}

.status-col {
  min-width: 110px;
}

.permission-col {
  min-width: 140px;
}

.action-col {
  min-width: 170px;
}

.permission-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.member-name {
  font-weight: 800;
  color: #0f172a;
}

.member-email {
  font-size: 0.9rem;
  color: #64748b;
}

.member-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.member-count,
.member-title,
.member-dirty {
  font-size: 0.78rem;
  border-radius: 999px;
  padding: 4px 8px;
}

.member-title {
  background: #f1f5f9;
  color: #475569;
}

.member-count {
  background: #ecfccb;
  color: #3f6212;
  font-weight: 700;
}

.member-dirty {
  background: #fef3c7;
  color: #92400e;
  font-weight: 700;
}

.status-btn {
  border: 1px solid #bbf7d0;
  background: #dcfce7;
  color: #166534;
  border-radius: 999px;
  padding: 8px 12px;
  font-weight: 700;
  cursor: pointer;
}

.status-btn.inactive {
  border-color: #fed7aa;
  background: #ffedd5;
  color: #9a3412;
}

.status-btn:disabled,
.create-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.table-select {
  min-width: 160px;
}

.permission-cell {
  text-align: center;
}

.permission-cell input {
  width: 16px;
  height: 16px;
}

.member-save-row {
  display: flex;
  justify-content: flex-start;
  gap: 10px;
  flex-wrap: wrap;
}

.primary-btn,
.secondary-btn {
  border-radius: 10px;
  padding: 10px 14px;
  font-weight: 700;
  cursor: pointer;
}

.primary-btn {
  border: none;
  background: #2563eb;
  color: #fff;
}

.secondary-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}

.permission-name {
  color: #0f172a;
  font-weight: 700;
  text-transform: capitalize;
}

.permission-key {
  color: #64748b;
  overflow-wrap: anywhere;
  text-transform: none;
  letter-spacing: 0;
}

.empty-state {
  padding: 32px 12px;
  text-align: center;
  color: #64748b;
}

@media (max-width: 768px) {
  .access-manager {
    padding: 16px;
  }

  .section-head {
    flex-direction: column;
  }

  .toolbar-filters {
    width: 100%;
    justify-content: stretch;
  }

  .search-input {
    width: 100%;
    min-width: 0;
  }

  .create-grid {
    grid-template-columns: 1fr;
  }

  .member-save-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
