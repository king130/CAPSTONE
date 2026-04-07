<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Eye, EyeOff, Upload } from 'lucide-vue-next'
import TomSelect from 'tom-select'
import 'tom-select/dist/css/tom-select.css'
import Swal from 'sweetalert2'
import { z } from 'zod'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Avatar from '@/components/ui/avatar/Avatar.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormField from '@/components/ui/form/FormField.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import FormMessage from '@/components/ui/form/FormMessage.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import { caviteBarangaysByLocation, caviteLocationGroups, courseGroups } from '@/config/courseCatalog'
import { useToast } from '@/composables/useToast'
import { buildProfileAvatarUrl, uploadProfileAvatar } from '@/services/profileMedia'
import {
  deactivateCurrentAccount,
  fetchSettings,
  saveAdminSystemSettings,
  saveNotificationPreferences,
  savePasswordSettings,
  saveProfileSettings,
  supportsAccountDeactivation,
  type AdminSystemSettings,
  type NotificationPreferences,
  type PasswordForm,
  type ProfileSettings,
  type SettingsPayload,
} from '@/services/settings'
import type { UserRole } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

type SettingsTab = 'profile' | 'security' | 'notifications' | 'system'

interface SettingsField {
  key: string
  label: string
  options?: string[]
}

interface NotificationPreferenceOption {
  key: string
  label: string
  description: string
}

interface PasswordVisibilityState {
  currentPassword: boolean
  newPassword: boolean
  confirmPassword: boolean
}

interface SettingsPanelProps {
  embedded?: boolean
}

const props = withDefaults(defineProps<SettingsPanelProps>(), {
  embedded: false,
})

const authStore = useAuthStore()
const { success, error } = useToast()

const loading = ref(true)
const activeTab = ref<SettingsTab>('profile')
const savingProfile = ref(false)
const savingPassword = ref(false)
const savingNotifications = ref(false)
const savingSystem = ref(false)
const deactivating = ref(false)
const deactivateDialogOpen = ref(false)
const maintenanceDialogOpen = ref(false)
const pendingMaintenanceValue = ref(false)
const avatarPreviewUrl = ref('')
const avatarFile = ref<File | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const notificationPreferences = ref<NotificationPreferences>({})
const systemSettings = ref<AdminSystemSettings>({
  requiredHours: '',
  schoolYear: '',
  semester: '',
  allowRegistrations: true,
  maintenanceMode: false,
})
const passwordVisibility = ref<PasswordVisibilityState>({
  currentPassword: false,
  newPassword: false,
  confirmPassword: false,
})
const organizationCourses = ref<string[]>([])
const pendingOrganizationCourses = ref<string[]>([])
const organizationCourseSelectRef = ref<HTMLSelectElement | null>(null)
let organizationCourseTomSelect: TomSelect | null = null

const currentRole = computed<UserRole>(() => authStore.user?.role ?? null)
const currentProfile = computed(() => (authStore.user?.profile as Record<string, unknown> | undefined) ?? {})
const currentEmail = computed(() => authStore.user?.email ?? '')
const currentAvatar = computed(() => {
  if (avatarPreviewUrl.value) return avatarPreviewUrl.value
  if (authStore.user?.uid && currentProfile.value.avatarPath) {
    return buildProfileAvatarUrl(authStore.user.uid, authStore.user.updatedAt)
  }
  return ''
})
const userInitials = computed(() => {
  const source = authStore.user?.displayName || authStore.user?.email || 'User'
  return source
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})
const locationBarangayOptions = computed(() => caviteBarangaysByLocation[String(profileValues.addressCity ?? '')] ?? [])

const availableTabs = computed<Array<{ key: SettingsTab; label: string }>>(() => {
  const tabs: Array<{ key: SettingsTab; label: string }> = [
    { key: 'profile', label: 'Profile Settings' },
    { key: 'security', label: 'Account & Security' },
    { key: 'notifications', label: 'Notifications Preferences' },
  ]

  if (currentRole.value === 'admin') {
    tabs.push({ key: 'system', label: 'System Settings' })
  }

  return tabs
})

const profileSchema = toTypedSchema(
  z.object({
    fullName: z.string().min(1, 'Full name is required.'),
    phone: z.string().min(1, 'Phone number is required.'),
    course: z.string().optional(),
    yearLevel: z.string().optional(),
    schoolName: z.string().optional(),
    schoolAddress: z.string().optional(),
    contactPerson: z.string().optional(),
    schoolType: z.string().optional(),
    companyName: z.string().optional(),
    industry: z.string().optional(),
    address: z.string().optional(),
    addressCity: z.string().optional(),
    addressBarangay: z.string().optional(),
    addressZipCode: z.string().optional(),
    acceptedCourses: z.string().optional(),
    officialSchoolEmail: z.string().optional(),
    website: z.string().optional(),
    department: z.string().optional(),
    position: z.string().optional(),
  }),
)

const passwordSchema = toTypedSchema(
  z
    .object({
      currentPassword: z.string().min(1, 'Current password is required.'),
      newPassword: z.string().min(8, 'New password must be at least 8 characters.'),
      confirmPassword: z.string().min(1, 'Please confirm the new password.'),
    })
    .superRefine((value, ctx) => {
      if (value.newPassword !== value.confirmPassword) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['confirmPassword'],
          message: 'Passwords do not match.',
        })
      }
    }),
)

const {
  handleSubmit: handleProfileSubmit,
  errors: profileErrors,
  setFieldValue: setProfileFieldValue,
  setValues: setProfileValues,
  values: profileValues,
} = useForm<ProfileSettings>({
  validationSchema: profileSchema,
  initialValues: {
    fullName: '',
    phone: '',
    course: '',
    yearLevel: '',
    schoolName: '',
    schoolAddress: '',
    contactPerson: '',
    schoolType: '',
    companyName: '',
    industry: '',
    address: '',
    addressCity: '',
    addressBarangay: '',
    addressZipCode: '',
    acceptedCourses: '',
    officialSchoolEmail: '',
    website: '',
    department: '',
    position: '',
  },
})

const {
  handleSubmit: handlePasswordSubmit,
  errors: passwordErrors,
  resetForm: resetPasswordForm,
  setFieldValue: setPasswordFieldValue,
} = useForm<PasswordForm>({
  validationSchema: passwordSchema,
  initialValues: {
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  },
})

const roleSpecificFields = computed<SettingsField[]>(() => {
  if (currentRole.value === 'student') {
    return [
      { key: 'course', label: 'Course' },
      { key: 'yearLevel', label: 'Year Level', options: ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'] },
      { key: 'schoolName', label: 'School Name' },
    ]
  }

  if (currentRole.value === 'school') {
    return [
      { key: 'schoolName', label: 'School Name' },
      { key: 'schoolAddress', label: 'School Address' },
      { key: 'contactPerson', label: 'Contact Person' },
      { key: 'schoolType', label: 'School Type' },
      { key: 'officialSchoolEmail', label: 'School Email Domain' },
    ]
  }

  if (currentRole.value === 'company') {
    return [
      { key: 'companyName', label: 'Company Name' },
      { key: 'industry', label: 'Industry' },
      { key: 'website', label: 'Website' },
    ]
  }

  if (currentRole.value === 'admin') {
    return [
      { key: 'department', label: 'Department' },
      { key: 'position', label: 'Position' },
    ]
  }

  return []
})

const notificationOptions = computed<NotificationPreferenceOption[]>(() => {
  if (currentRole.value === 'student') {
    return [
      { key: 'application', label: 'Application status update', description: 'Get notified when your application moves to a new status.' },
      { key: 'endorsement', label: 'Endorsement approved', description: 'Receive updates when your school processes endorsements.' },
      { key: 'hours', label: 'OJT hours approved/rejected', description: 'See when submitted hours are reviewed.' },
      { key: 'system', label: 'New message from company', description: 'Stay updated when a company sends you a message.' },
    ]
  }

  if (currentRole.value === 'school') {
    return [
      { key: 'registration', label: 'New intern registered under school', description: 'Know when a student joins under your institution.' },
      { key: 'endorsement', label: 'Endorsement request', description: 'Receive alerts for incoming endorsement approvals.' },
      { key: 'hours', label: 'OJT milestone reached by intern', description: 'Track students hitting important OJT milestones.' },
    ]
  }

  if (currentRole.value === 'company') {
    return [
      { key: 'application', label: 'New intern applicant', description: 'Get notified when a student applies to your posted jobs.' },
      { key: 'hours', label: 'OJT hours log submitted by intern', description: 'Review submitted internship hour logs quickly.' },
      { key: 'endorsement', label: 'Intern accepted confirmation', description: 'Receive confirmation when an applicant is finalized.' },
    ]
  }

  return [
    { key: 'registration', label: 'New registration', description: 'Monitor incoming student, school, and company registrations.' },
    { key: 'system', label: 'Flagged activity', description: 'See security or moderation events that require review.' },
    { key: 'application', label: 'System alerts', description: 'Stay informed about platform-level issues and service events.' },
  ]
})

function buildFallbackSettings(): SettingsPayload {
  const profile = currentProfile.value

  return {
    profile: {
      fullName: authStore.user?.displayName ?? '',
      phone: String(profile.contactNumber ?? profile.companyContactNumber ?? profile.schoolContactNumber ?? ''),
      course: String(profile.course ?? ''),
      yearLevel: String(profile.yearLevel ?? ''),
      schoolName: String(profile.schoolName ?? ''),
      schoolAddress: String(profile.schoolAddress ?? ''),
      contactPerson: String(profile.contactPersonName ?? ''),
      schoolType: String(profile.institutionType ?? ''),
      companyName: String(profile.companyName ?? ''),
      industry: String(profile.industryType ?? ''),
      address: String(profile.companyAddress ?? ''),
      addressCity: String(profile.cityMunicipality ?? ''),
      addressBarangay: String(profile.barangay ?? ''),
      addressZipCode: String(profile.zipCode ?? ''),
      acceptedCourses: JSON.stringify(Array.isArray(profile.courses) ? profile.courses : []),
      officialSchoolEmail: String(profile.officialSchoolEmail ?? ''),
      website: String(profile.website ?? ''),
      department: String(profile.department ?? ''),
      position: String(profile.position ?? ''),
    },
    notificationPreferences: Object.fromEntries(notificationOptions.value.map((option) => [option.key, true])),
    systemSettings: {
      requiredHours: '500',
      schoolYear: '',
      semester: '',
      allowRegistrations: true,
      maintenanceMode: false,
    },
  }
}

function applySettings(payload: SettingsPayload) {
  setProfileValues({
    fullName: payload.profile.fullName ?? '',
    phone: payload.profile.phone ?? '',
    course: payload.profile.course ?? '',
    yearLevel: payload.profile.yearLevel ?? '',
    schoolName: payload.profile.schoolName ?? '',
    schoolAddress: payload.profile.schoolAddress ?? '',
    contactPerson: payload.profile.contactPerson ?? '',
    schoolType: payload.profile.schoolType ?? '',
    companyName: payload.profile.companyName ?? '',
    industry: payload.profile.industry ?? '',
    address: payload.profile.address ?? '',
    addressCity: payload.profile.addressCity ?? '',
    addressBarangay: payload.profile.addressBarangay ?? '',
    addressZipCode: payload.profile.addressZipCode ?? '',
    acceptedCourses: payload.profile.acceptedCourses ?? '',
    officialSchoolEmail: payload.profile.officialSchoolEmail ?? '',
    website: payload.profile.website ?? '',
    department: payload.profile.department ?? '',
    position: payload.profile.position ?? '',
  })

  try {
    const parsedCourses = JSON.parse(String((payload.profile as Record<string, unknown>).acceptedCourses ?? '[]'))
    organizationCourses.value = Array.isArray(parsedCourses)
      ? parsedCourses.map((course) => String(course).trim()).filter(Boolean)
      : []
  } catch {
    organizationCourses.value = []
  }

  notificationPreferences.value = Object.fromEntries(
    notificationOptions.value.map((option) => [option.key, payload.notificationPreferences[option.key] ?? true]),
  )

  if (payload.systemSettings) {
    systemSettings.value = {
      requiredHours: payload.systemSettings.requiredHours ?? '',
      schoolYear: payload.systemSettings.schoolYear ?? '',
      semester: payload.systemSettings.semester ?? '',
      allowRegistrations: payload.systemSettings.allowRegistrations ?? true,
      maintenanceMode: payload.systemSettings.maintenanceMode ?? false,
    }
  }
}

async function loadSettingsData() {
  loading.value = true
  try {
    const payload = await fetchSettings()
    applySettings(payload)
  } catch {
    applySettings(buildFallbackSettings())
  } finally {
    loading.value = false
  }
}

function openFilePicker() {
  fileInputRef.value?.click()
}

function onAvatarChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  avatarFile.value = file
  const reader = new FileReader()
  reader.onload = () => {
    avatarPreviewUrl.value = typeof reader.result === 'string' ? reader.result : ''
  }
  reader.readAsDataURL(file)
}

function mapProfilePayload(): ProfileSettings {
  const payload: ProfileSettings = {
    fullName: profileValues.fullName ?? '',
    phone: profileValues.phone ?? '',
  }

  for (const field of roleSpecificFields.value) {
    payload[field.key] = profileValues[field.key] ?? ''
  }

  if (currentRole.value === 'company') {
    payload.address = profileValues.address ?? ''
    payload.addressCity = profileValues.addressCity ?? ''
    payload.addressBarangay = profileValues.addressBarangay ?? ''
    payload.addressZipCode = profileValues.addressZipCode ?? ''
  }

  if (currentRole.value === 'school') {
    payload.schoolName = profileValues.schoolName ?? ''
    payload.schoolAddress = profileValues.schoolAddress ?? ''
    payload.address = profileValues.address ?? ''
    payload.addressCity = profileValues.addressCity ?? ''
    payload.addressBarangay = profileValues.addressBarangay ?? ''
    payload.addressZipCode = profileValues.addressZipCode ?? ''
    payload.officialSchoolEmail = profileValues.officialSchoolEmail ?? ''
  }

  return payload
}

function removeOrganizationCourse(courseToRemove: string) {
  organizationCourses.value = organizationCourses.value.filter((course) => course !== courseToRemove)
}

function addOrganizationCourseFromPicker() {
  if (!organizationCourseTomSelect) return

  const typedValue = organizationCourseTomSelect.control_input.value.trim()
  let selections = [...pendingOrganizationCourses.value]

  if (typedValue) {
    const normalizedTypedValue = typedValue.toLowerCase()
    const existingOption = organizationCourseTomSelect.options[typedValue]
      ? typedValue
      : Object.keys(organizationCourseTomSelect.options).find(
          (optionValue) => optionValue.trim().toLowerCase() === normalizedTypedValue,
        )

    if (existingOption) {
      selections = Array.from(new Set([...selections, existingOption]))
    } else {
      const createdValue = typedValue
      organizationCourseTomSelect.addOption({ value: createdValue, text: createdValue })
      selections = Array.from(new Set([...selections, createdValue]))
    }
  }

  if (!selections.length) {
    organizationCourseTomSelect.focus()
    organizationCourseTomSelect.open()
    return
  }

  organizationCourses.value = Array.from(new Set([...organizationCourses.value, ...selections]))
  pendingOrganizationCourses.value = []
  organizationCourseTomSelect.clear(true)
  organizationCourseTomSelect.clearOptions()
  for (const group of courseGroups) {
    if (!organizationCourseTomSelect.optgroups[group.label]) {
      organizationCourseTomSelect.addOptionGroup(group.label, { label: group.label, value: group.label })
    }
    for (const option of group.options) {
      organizationCourseTomSelect.addOption({ value: option, text: option, optgroup: group.label })
    }
  }
  for (const course of organizationCourses.value) {
    if (!organizationCourseTomSelect.options[course]) {
      organizationCourseTomSelect.addOption({ value: course, text: course })
    }
  }
  organizationCourseTomSelect.refreshOptions(false)
  organizationCourseTomSelect.setTextboxValue('')
  organizationCourseTomSelect.blur()
}

function syncTomSelectValue() {
  if (!organizationCourseTomSelect) return
  organizationCourseTomSelect.setValue(pendingOrganizationCourses.value, true)
}

function destroyOrganizationCourseTomSelect() {
  organizationCourseTomSelect?.destroy()
  organizationCourseTomSelect = null
}

async function initializeOrganizationCourseTomSelect() {
  if (currentRole.value !== 'school' && currentRole.value !== 'company') {
    destroyOrganizationCourseTomSelect()
    return
  }

  if (activeTab.value !== 'profile' || loading.value) {
    destroyOrganizationCourseTomSelect()
    return
  }

  await nextTick()

  const element = organizationCourseSelectRef.value
  if (!element) return

  destroyOrganizationCourseTomSelect()

  organizationCourseTomSelect = new TomSelect(element, {
    plugins: ['remove_button'],
    create: true,
    persist: false,
    maxOptions: 200,
    closeAfterSelect: false,
    hideSelected: false,
    placeholder: currentRole.value === 'school'
      ? 'Select one or more school courses'
      : 'Select one or more accepted courses',
    render: {
      optgroup_header(data, escape) {
        return `<div class="ts-program-header">${escape(data.label)}</div>`
      },
      option(data, escape) {
        return `<div class="ts-course-option">${escape(data.text)}</div>`
      },
      item(data, escape) {
        return `<div>${escape(data.text)}</div>`
      },
      option_create(data, escape) {
        return `<div class="create">Add custom course: <strong>${escape(data.input)}</strong></div>`
      },
    },
    onChange(value) {
      const values = Array.isArray(value) ? value : String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)

      pendingOrganizationCourses.value = values
    },
    onItemAdd() {
      organizationCourseTomSelect?.setTextboxValue('')
      organizationCourseTomSelect?.refreshOptions(false)
    },
    createFilter(input) {
      return input.trim().length > 0
    },
  })

  syncTomSelectValue()
}

watch(
  () => profileValues.addressCity,
  (city) => {
    if (!city) {
      setProfileFieldValue('addressBarangay', '')
      return
    }

    if (!locationBarangayOptions.value.includes(String(profileValues.addressBarangay ?? ''))) {
      setProfileFieldValue('addressBarangay', '')
    }
  },
)

watch([currentRole, activeTab, loading], () => {
  void initializeOrganizationCourseTomSelect()
})

watch(
  pendingOrganizationCourses,
  () => {
    syncTomSelectValue()
  },
  { deep: true },
)

const saveProfile = handleProfileSubmit(async () => {
  const confirmation = await Swal.fire({
    icon: 'question',
    title: 'Save these changes?',
    text: 'Your updated settings will be applied to your account.',
    showCancelButton: true,
    confirmButtonText: 'Save Changes',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })

  if (!confirmation.isConfirmed) return

  savingProfile.value = true
  try {
    if (avatarFile.value) {
      const avatarProfile = await uploadProfileAvatar(avatarFile.value)
      authStore.setUserProfile(avatarProfile)
      avatarFile.value = null
    }

    const payload = mapProfilePayload()
    payload.acceptedCourses = JSON.stringify(organizationCourses.value)
    await saveProfileSettings(payload)
    const refreshedUser = await authStore.refreshUser()
    if (refreshedUser) {
      authStore.setUserProfile(refreshedUser)
    }
    success('Profile updated successfully')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update profile.',
    })
  } finally {
    savingProfile.value = false
  }
})

const updatePassword = handlePasswordSubmit(async (values) => {
  savingPassword.value = true
  try {
    await savePasswordSettings(values)
    resetPasswordForm()
    success('Password updated successfully')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update password.',
    })
  } finally {
    savingPassword.value = false
  }
})

async function saveNotifications() {
  savingNotifications.value = true
  try {
    await saveNotificationPreferences(notificationPreferences.value)
    success('Notification preferences saved.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to save notification preferences.',
    })
  } finally {
    savingNotifications.value = false
  }
}

async function saveSystem() {
  savingSystem.value = true
  try {
    await saveAdminSystemSettings(systemSettings.value)
    success('System settings saved.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to save system settings.',
    })
  } finally {
    savingSystem.value = false
  }
}

async function confirmDeactivate() {
  deactivating.value = true
  try {
    await deactivateCurrentAccount()
    await authStore.logout()
    success('Account deactivated.')
    window.location.assign('/login')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to deactivate account.',
    })
  } finally {
    deactivating.value = false
    deactivateDialogOpen.value = false
  }
}

function onMaintenanceToggle(nextValue: boolean) {
  if (nextValue) {
    pendingMaintenanceValue.value = nextValue
    maintenanceDialogOpen.value = true
    return
  }

  systemSettings.value.maintenanceMode = false
}

function confirmMaintenanceMode() {
  systemSettings.value.maintenanceMode = pendingMaintenanceValue.value
  maintenanceDialogOpen.value = false
}

function togglePasswordVisibility(field: keyof PasswordVisibilityState) {
  passwordVisibility.value[field] = !passwordVisibility.value[field]
}

onMounted(() => {
  void loadSettingsData()
})

onBeforeUnmount(() => {
  destroyOrganizationCourseTomSelect()
})
</script>

<template>
  <section class="space-y-6">
    <div v-if="!props.embedded" class="space-y-2">
      <p class="text-sm font-semibold uppercase tracking-[0.3em] text-sky-700">Settings</p>
      <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Manage your account and workspace preferences</h2>
      <p class="max-w-2xl text-sm leading-6 text-slate-600">
        Update your profile, account security, notification preferences, and role-specific configuration.
      </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
      <Card class="border-border/80 shadow-sm">
        <CardContent class="p-4">
          <div class="space-y-2">
            <Button
              v-for="tab in availableTabs"
              :key="tab.key"
              variant="ghost"
              class="w-full justify-start"
              :class="activeTab === tab.key ? 'bg-accent text-accent-foreground' : 'text-muted-foreground'"
              @click="activeTab = tab.key"
            >
              {{ tab.label }}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80 shadow-sm">
        <CardContent class="p-6">
          <template v-if="loading">
            <div class="space-y-4">
              <Skeleton class="h-8 w-48" />
              <Skeleton class="h-24 w-full rounded-2xl" />
              <Skeleton class="h-12 w-full" />
              <Skeleton class="h-12 w-full" />
              <Skeleton class="h-12 w-full" />
              <Skeleton class="h-12 w-40" />
            </div>
          </template>

          <div v-else-if="activeTab === 'profile'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-slate-950">Profile Settings</h3>
              <p class="mt-2 text-sm text-slate-600">Keep your profile accurate so the right people can contact you and review your information.</p>
            </div>

            <div class="flex flex-col gap-4 rounded-2xl border border-border bg-slate-50 p-5 sm:flex-row sm:items-center">
              <Avatar :src="currentAvatar" :fallback="userInitials" :alt="profileValues.fullName" class="h-20 w-20" />
              <div class="space-y-2">
                <p class="text-sm font-medium text-slate-950">Profile photo</p>
                <p class="text-sm text-slate-500">Upload a clear avatar so your account is easy to recognize.</p>
                <div class="flex gap-3">
                  <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="onAvatarChange"
                  />
                  <Button variant="outline" class="gap-2" @click="openFilePicker">
                    <Upload class="h-4 w-4" />
                    Upload
                  </Button>
                </div>
              </div>
            </div>

            <form class="space-y-5" @submit="saveProfile">
              <FormField v-slot="{ componentField, errorMessage: fieldError }" name="fullName">
                <FormItem>
                  <FormLabel for="settingsFullName">Full Name</FormLabel>
                  <FormControl>
                    <Input
                      id="settingsFullName"
                      v-bind="componentField"
                      :model-value="profileValues.fullName"
                      @update:modelValue="setProfileFieldValue('fullName', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || profileErrors.fullName" />
                </FormItem>
              </FormField>

              <FormItem>
                <FormLabel for="settingsEmail">Email</FormLabel>
                <FormControl>
                  <Input id="settingsEmail" :model-value="currentEmail" type="email" readonly />
                </FormControl>
                <p class="text-sm text-slate-500">Contact admin to change email.</p>
              </FormItem>

              <FormField v-slot="{ componentField, errorMessage: fieldError }" name="phone">
                <FormItem>
                  <FormLabel for="settingsPhone">Phone Number</FormLabel>
                  <FormControl>
                    <Input
                      id="settingsPhone"
                      v-bind="componentField"
                      :model-value="profileValues.phone"
                      @update:modelValue="setProfileFieldValue('phone', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || profileErrors.phone" />
                </FormItem>
              </FormField>

              <FormField
                v-for="field in roleSpecificFields"
                :key="field.key"
                v-slot="{ componentField, errorMessage: fieldError }"
                :name="field.key"
              >
                <FormItem>
                  <FormLabel :for="field.key">{{ field.label }}</FormLabel>
                  <FormControl>
                    <Select
                      v-if="field.options"
                      :id="field.key"
                      v-bind="componentField"
                      :model-value="profileValues[field.key]"
                      @update:modelValue="setProfileFieldValue(field.key, $event)"
                    >
                      <option value="">Select {{ field.label.toLowerCase() }}</option>
                      <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                    </Select>
                    <Input
                      v-else
                      :id="field.key"
                      v-bind="componentField"
                      :model-value="profileValues[field.key]"
                      @update:modelValue="setProfileFieldValue(field.key, $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || profileErrors[field.key]" />
                </FormItem>
              </FormField>

              <template v-if="currentRole === 'company' || currentRole === 'school'">
                <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                  <div class="space-y-1">
                    <h4 class="text-base font-semibold text-slate-950">
                      {{ currentRole === 'school' ? 'School Courses' : 'Accepted Courses' }}
                    </h4>
                    <p class="text-sm text-slate-600">
                      {{ currentRole === 'school'
                        ? 'Add the courses or programs your school offers for student account creation and matching.'
                        : 'Add the courses or programs your company accepts for internships.' }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                      <select ref="organizationCourseSelectRef" multiple class="organization-course-select">
                        <optgroup v-for="group in courseGroups" :key="group.label" :label="group.label">
                          <option v-for="option in group.options" :key="option" :value="option">{{ option }}</option>
                        </optgroup>
                      </select>
                      <Button type="button" variant="outline" @mousedown.prevent="addOrganizationCourseFromPicker">
                        Add Course
                      </Button>
                    </div>
                    <p class="text-sm text-slate-500">
                      Program headers are grouped above the courses. Search and select multiple courses at once, or type a custom one and click `Add Course`.
                    </p>
                  </div>
                  <div v-if="organizationCourses.length" class="flex flex-wrap gap-2">
                    <span
                      v-for="course in organizationCourses"
                      :key="course"
                      class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700"
                    >
                      {{ course }}
                      <button type="button" class="text-slate-500 hover:text-slate-900" @click="removeOrganizationCourse(course)">
                        x
                      </button>
                    </span>
                  </div>
                  <p v-else class="text-sm text-slate-500">
                    {{ currentRole === 'school' ? 'No school courses added yet.' : 'No accepted courses added yet.' }}
                  </p>
                </div>

                <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                  <div class="space-y-1">
                    <h4 class="text-base font-semibold text-slate-950">
                      {{ currentRole === 'school' ? 'School Address' : 'Company Address' }}
                    </h4>
                    <p class="text-sm text-slate-600">
                      {{ currentRole === 'school'
                        ? 'Use the Cavite location fields so student accounts and directory data stay consistent.'
                        : 'Use the Cavite location fields so schools can filter your company correctly.' }}
                    </p>
                  </div>
                  <FormItem v-if="currentRole === 'school'">
                    <FormLabel for="schoolEmailDomain">School Email Domain</FormLabel>
                    <FormControl>
                      <Input
                        id="schoolEmailDomain"
                        :model-value="profileValues.officialSchoolEmail"
                        placeholder="@school.edu.ph"
                        @update:modelValue="setProfileFieldValue('officialSchoolEmail', $event)"
                      />
                    </FormControl>
                    <p class="text-sm text-slate-500">
                      Used when auto-generating student account emails. You can enter `@ncst.edu.ph` or a full school email.
                    </p>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="companyAddress">Street Address / Building / Landmark</FormLabel>
                    <FormControl>
                      <Input
                        id="companyAddress"
                        :model-value="currentRole === 'school' ? profileValues.schoolAddress : profileValues.address"
                        @update:modelValue="setProfileFieldValue(currentRole === 'school' ? 'schoolAddress' : 'address', $event)"
                      />
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel>Province</FormLabel>
                    <FormControl>
                      <Input model-value="Cavite" readonly />
                    </FormControl>
                  </FormItem>
                  <div class="grid gap-4 md:grid-cols-2">
                    <FormItem>
                      <FormLabel for="companyAddressCity">City / Municipality</FormLabel>
                      <FormControl>
                        <Select
                          id="companyAddressCity"
                          :model-value="profileValues.addressCity"
                          @update:modelValue="setProfileFieldValue('addressCity', $event)"
                        >
                          <option value="">Select a city or municipality</option>
                          <optgroup v-for="group in caviteLocationGroups" :key="group.label" :label="group.label">
                            <option v-for="option in group.options" :key="option" :value="option">{{ option }}</option>
                          </optgroup>
                        </Select>
                      </FormControl>
                    </FormItem>
                    <FormItem>
                      <FormLabel for="companyAddressBarangay">Barangay</FormLabel>
                      <FormControl>
                        <Select
                          id="companyAddressBarangay"
                          :model-value="profileValues.addressBarangay"
                          :disabled="!profileValues.addressCity"
                          @update:modelValue="setProfileFieldValue('addressBarangay', $event)"
                        >
                          <option value="">{{ locationBarangayOptions.length ? 'Select a barangay' : 'Select a city or municipality first' }}</option>
                          <option v-for="option in locationBarangayOptions" :key="option" :value="option">{{ option }}</option>
                        </Select>
                      </FormControl>
                    </FormItem>
                  </div>
                  <FormItem>
                    <FormLabel for="companyAddressZipCode">Zip Code</FormLabel>
                    <FormControl>
                      <Input
                        id="companyAddressZipCode"
                        :model-value="profileValues.addressZipCode"
                        @update:modelValue="setProfileFieldValue('addressZipCode', $event)"
                      />
                    </FormControl>
                  </FormItem>
                </div>
              </template>

              <Button type="submit" class="w-full md:w-auto" :disabled="savingProfile">
                {{ savingProfile ? 'Saving...' : 'Save Changes' }}
              </Button>
            </form>
          </div>

          <div v-else-if="activeTab === 'security'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-slate-950">Account & Security</h3>
              <p class="mt-2 text-sm text-slate-600">Update your password and manage high-impact account actions.</p>
            </div>

            <Card class="border-border/80 shadow-none">
              <CardHeader>
                <h4 class="text-lg font-semibold text-slate-950">Change Password</h4>
                <p class="text-sm text-slate-600">Use a strong password with at least 8 characters.</p>
              </CardHeader>
              <CardContent>
                <form class="space-y-5" @submit="updatePassword">
                  <FormField v-slot="{ componentField, errorMessage: fieldError }" name="currentPassword">
                    <FormItem>
                      <FormLabel for="currentPassword">Current Password</FormLabel>
                      <FormControl>
                        <div class="relative">
                          <Input
                            id="currentPassword"
                            v-bind="componentField"
                            :type="passwordVisibility.currentPassword ? 'text' : 'password'"
                            @update:modelValue="setPasswordFieldValue('currentPassword', $event)"
                          />
                          <Button
                            variant="ghost"
                            size="icon"
                            class="absolute right-1 top-1 h-8 w-8"
                            @click.prevent="togglePasswordVisibility('currentPassword')"
                          >
                            <Eye v-if="passwordVisibility.currentPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                          </Button>
                        </div>
                      </FormControl>
                      <FormMessage :message="fieldError || passwordErrors.currentPassword" />
                    </FormItem>
                  </FormField>

                  <FormField v-slot="{ componentField, errorMessage: fieldError }" name="newPassword">
                    <FormItem>
                      <FormLabel for="newPassword">New Password</FormLabel>
                      <FormControl>
                        <div class="relative">
                          <Input
                            id="newPassword"
                            v-bind="componentField"
                            :type="passwordVisibility.newPassword ? 'text' : 'password'"
                            @update:modelValue="setPasswordFieldValue('newPassword', $event)"
                          />
                          <Button
                            variant="ghost"
                            size="icon"
                            class="absolute right-1 top-1 h-8 w-8"
                            @click.prevent="togglePasswordVisibility('newPassword')"
                          >
                            <Eye v-if="passwordVisibility.newPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                          </Button>
                        </div>
                      </FormControl>
                      <FormMessage :message="fieldError || passwordErrors.newPassword" />
                    </FormItem>
                  </FormField>

                  <FormField v-slot="{ componentField, errorMessage: fieldError }" name="confirmPassword">
                    <FormItem>
                      <FormLabel for="confirmPassword">Confirm New Password</FormLabel>
                      <FormControl>
                        <div class="relative">
                          <Input
                            id="confirmPassword"
                            v-bind="componentField"
                            :type="passwordVisibility.confirmPassword ? 'text' : 'password'"
                            @update:modelValue="setPasswordFieldValue('confirmPassword', $event)"
                          />
                          <Button
                            variant="ghost"
                            size="icon"
                            class="absolute right-1 top-1 h-8 w-8"
                            @click.prevent="togglePasswordVisibility('confirmPassword')"
                          >
                            <Eye v-if="passwordVisibility.confirmPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                          </Button>
                        </div>
                      </FormControl>
                      <FormMessage :message="fieldError || passwordErrors.confirmPassword" />
                    </FormItem>
                  </FormField>

                  <Button type="submit" :disabled="savingPassword">
                    {{ savingPassword ? 'Updating...' : 'Update Password' }}
                  </Button>
                </form>
              </CardContent>
            </Card>

            <Card class="border-red-200 shadow-none">
              <CardHeader>
                <h4 class="text-lg font-semibold text-red-700">Danger Zone</h4>
                <p class="text-sm text-slate-600">Deactivate your account if you no longer need access to the platform.</p>
              </CardHeader>
              <CardContent>
                <Button
                  variant="destructive"
                  :disabled="deactivating || !supportsAccountDeactivation"
                  @click="deactivateDialogOpen = true"
                >
                  {{ deactivating ? 'Deactivating...' : 'Deactivate Account' }}
                </Button>
                <p class="mt-3 text-sm text-slate-500">
                  Account deactivation is not enabled on the current backend, so this action is unavailable for now.
                </p>
              </CardContent>
            </Card>
          </div>

          <div v-else-if="activeTab === 'notifications'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-slate-950">Notifications Preferences</h3>
              <p class="mt-2 text-sm text-slate-600">Choose which updates you want to receive for your role.</p>
            </div>

            <div class="space-y-4">
              <Card v-for="option in notificationOptions" :key="option.key" class="border-border/80 shadow-none">
                <CardContent class="flex items-start justify-between gap-4 p-5">
                  <div class="space-y-1">
                    <p class="font-medium text-slate-950">{{ option.label }}</p>
                    <p class="text-sm text-slate-600">{{ option.description }}</p>
                  </div>
                  <Switch v-model="notificationPreferences[option.key]" />
                </CardContent>
              </Card>
            </div>

            <Button :disabled="savingNotifications" @click="saveNotifications">
              {{ savingNotifications ? 'Saving...' : 'Save Preferences' }}
            </Button>
          </div>

          <div v-else class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-slate-950">System Settings</h3>
              <p class="mt-2 text-sm text-slate-600">Control platform-wide defaults and registration behavior.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
              <FormItem>
                <FormLabel for="requiredHours">OJT Required Hours</FormLabel>
                <FormControl>
                  <Input id="requiredHours" v-model="systemSettings.requiredHours" type="number" min="0" />
                </FormControl>
              </FormItem>

              <FormItem>
                <FormLabel for="schoolYear">School Year</FormLabel>
                <FormControl>
                  <Select id="schoolYear" v-model="systemSettings.schoolYear">
                    <option value="">Select school year</option>
                    <option value="2025-2026">2025-2026</option>
                    <option value="2026-2027">2026-2027</option>
                    <option value="2027-2028">2027-2028</option>
                  </Select>
                </FormControl>
              </FormItem>

              <FormItem>
                <FormLabel for="semester">Semester</FormLabel>
                <FormControl>
                  <Select id="semester" v-model="systemSettings.semester">
                    <option value="">Select semester</option>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Midyear">Midyear</option>
                  </Select>
                </FormControl>
              </FormItem>
            </div>

            <div class="space-y-4 rounded-2xl border border-border bg-slate-50 p-5">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="font-medium text-slate-950">Allow Registrations</p>
                  <p class="text-sm text-slate-600">Toggle whether new users can create accounts.</p>
                </div>
                <Switch v-model="systemSettings.allowRegistrations" />
              </div>

              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="font-medium text-slate-950">Maintenance Mode</p>
                  <p class="text-sm text-slate-600">Require confirmation before turning on platform maintenance.</p>
                </div>
                <Switch :model-value="systemSettings.maintenanceMode" @update:modelValue="onMaintenanceToggle" />
              </div>
            </div>

            <Button :disabled="savingSystem" @click="saveSystem">
              {{ savingSystem ? 'Saving...' : 'Save System Settings' }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <AlertDialog
      :open="deactivateDialogOpen"
      title="Deactivate account?"
      description="This will deactivate your account and sign you out immediately."
      action-label="Deactivate"
      action-variant="destructive"
      @update:open="deactivateDialogOpen = $event"
      @action="confirmDeactivate"
    />

    <AlertDialog
      :open="maintenanceDialogOpen"
      title="Enable maintenance mode?"
      description="This may restrict access for users while maintenance is active."
      action-label="Enable"
      action-variant="destructive"
      @update:open="maintenanceDialogOpen = $event"
      @action="confirmMaintenanceMode"
    />
  </section>
</template>

<style scoped>
.organization-course-select {
  width: 100%;
}

:deep(.ts-wrapper) {
  width: 100%;
}

:deep(.ts-control) {
  min-height: 46px;
  border-radius: 1rem;
  border: 1px solid rgb(226 232 240);
  background: rgb(255 255 255);
  padding: 0.5rem 0.75rem;
  box-shadow: none;
}

:deep(.ts-wrapper.focus .ts-control) {
  border-color: rgb(125 211 252);
  box-shadow: 0 0 0 4px rgb(14 165 233 / 0.12);
}

:deep(.ts-control > input) {
  font-size: 0.95rem;
}

:deep(.ts-control .item) {
  border-radius: 9999px;
  background: rgb(224 242 254);
  color: rgb(3 105 161);
  padding: 0.3rem 0.65rem;
}

:deep(.ts-dropdown) {
  border-radius: 1rem;
  border: 1px solid rgb(226 232 240);
  box-shadow: 0 18px 40px -24px rgb(15 23 42 / 0.35);
  overflow: hidden;
}

:deep(.ts-dropdown .option),
:deep(.ts-dropdown .optgroup-header) {
  padding: 0.75rem 0.9rem;
}

:deep(.ts-program-header) {
  padding: 0.8rem 0.95rem 0.45rem;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgb(15 23 42);
  background: rgb(248 250 252);
  border-bottom: 1px solid rgb(226 232 240);
}

:deep(.ts-course-option) {
  font-size: 0.95rem;
  color: rgb(51 65 85);
}

:deep(.ts-dropdown .active) {
  background: rgb(240 249 255);
  color: rgb(3 105 161);
}
</style>
