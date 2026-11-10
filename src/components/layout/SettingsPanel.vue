<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Check, Moon, Sun, Upload } from 'lucide-vue-next'
import TomSelect from 'tom-select'
import 'tom-select/dist/css/tom-select.css'
import Swal from '@/services/swal'
import { z } from 'zod'

import OrganizationPublicProfileSection from '@/components/settings/OrganizationPublicProfileSection.vue'
import SkillChips from '@/components/skills/SkillChips.vue'
import SkillsWizard from '@/components/skills/SkillsWizard.vue'
import {
  fetchStudentSkills,
  saveStudentSkills,
  type SelectedSkill,
} from '@/services/skills'
import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Avatar from '@/components/ui/avatar/Avatar.vue'
import Badge from '@/components/ui/badge/Badge.vue'
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
import PasswordInput from '@/components/ui/password-input/PasswordInput.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import { caviteBarangaysByLocation, caviteLocationGroups, courseGroups } from '@/config/courseCatalog'
import { useToast } from '@/composables/useToast'
import { buildProfileAvatarUrl, uploadProfileAvatar } from '@/services/profileMedia'
import {
  fetchOrgOjtSettings,
  fetchSettings,
  saveAdminSystemSettings,
  saveNotificationPreferences,
  saveOrgOjtSettings,
  savePasswordSettings,
  saveProfileSettings,
  type AdminSystemSettings,
  type NotificationPreferences,
  type PasswordForm,
  type ProfileSettings,
  type SettingsPayload,
} from '@/services/settings'
import { listAgreements } from '@/services/contracts'
import { subscribeCompanyInternships } from '@/services/internships'
import { normalizeJobStatus } from '@/utils/jobStatus'
import { hasPermission } from '@/services/permissions'
import {
  ALL_YEAR_LEVEL_OPTIONS,
  DEFAULT_ELIGIBLE_YEAR_LEVELS,
  createCourseRequirement,
  listCourseRequirements,
  listFlaggedCourseStudents,
  updateCourseRequirement,
  type CourseRequirement,
  type CourseRequirementMeta,
  type FlaggedStudent,
} from '@/services/schoolPrograms'
import {
  SKILL_CATALOG_CATEGORIES,
  recommendedSkillCategoriesForCourse,
} from '@/utils/courseSkillCategoryDefaults'
import type { UserRole } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import { normalizeStudentNumber, studentNumberErrorMessage } from '@/utils/studentNumber'
import { meetsPasswordPolicy } from '@/utils/passwordPolicy'
import { useTheme } from '@/composables/useTheme'

const SCHOOL_TYPE_OPTIONS = ['University', 'College', 'Technical/Vocational school', 'Other'] as const

const FLAGGED_REASON_LABELS: Record<string, string> = {
  missing_course: 'Course not set on student record',
  course_not_configured: 'Course not set up yet',
  no_eligible_year_levels: 'Course has no eligible year levels',
  missing_or_invalid_year_level: 'Year level missing or invalid',
  year_not_eligible_for_course: 'Year level not eligible for this course',
}

type SettingsTab = 'account' | 'organization' | 'courses' | 'public' | 'notifications' | 'system'

interface SettingsField {
  key: string
  label: string
  options?: string[]
  readonly?: boolean
}

interface NotificationPreferenceOption {
  key: string
  label: string
  description: string
}

interface SettingsPanelProps {
  embedded?: boolean
}

const props = withDefaults(defineProps<SettingsPanelProps>(), {
  embedded: false,
})

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const { colorMode } = useTheme()
const { success, error } = useToast()

const loading = ref(true)
const activeTab = ref<SettingsTab>('account')

function applyTabFromRoute() {
  const raw = String(route.query.tab || '').trim().toLowerCase()
  if (!raw) return
  const allowed: SettingsTab[] = ['account', 'organization', 'courses', 'public', 'notifications', 'system']
  if ((allowed as string[]).includes(raw)) {
    activeTab.value = raw as SettingsTab
  }
}
const savedFlash = ref('')
const hasPublishedJob = ref(false)
let unsubscribeCompanyJobs: (() => void) | null = null
let savedFlashTimer: ReturnType<typeof setTimeout> | null = null
const savingProfile = ref(false)
const savingPassword = ref(false)
const savingNotifications = ref(false)
const savingSystem = ref(false)
const savingOrgOjt = ref(false)
const maintenanceDialogOpen = ref(false)
const pendingMaintenanceValue = ref(false)
const avatarPreviewUrl = ref('')
const avatarFile = ref<File | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const accountPhotoSectionRef = ref<HTMLElement | null>(null)
const publicProfileSectionRef = ref<{
  isDirty: { value: boolean } | boolean
  saveIfDirty: () => Promise<void>
  discardChanges: () => void
} | null>(null)
const publicProfileDirty = ref(false)
const accountBaseline = ref('')
const organizationBaseline = ref('')
const coursesBaseline = ref('')
const notificationBaseline = ref('')
const notificationPreferences = ref<NotificationPreferences>({})
const orgRequiredHours = ref('500')
const hasActiveAgreement = ref(false)
const courseHourErrors = ref<Record<string, string>>({})
const systemSettings = ref<AdminSystemSettings>({
  schoolYear: '',
  semester: '',
  allowRegistrations: true,
  maintenanceMode: false,
})
const profileSkills = ref<SelectedSkill[]>([])
const skillsWizardOpen = ref(false)
const savingSkills = ref(false)
const organizationCourses = ref<string[]>([])
const pendingOrganizationCourses = ref<string[]>([])
const organizationCourseSelectRef = ref<HTMLSelectElement | null>(null)
let organizationCourseTomSelect: TomSelect | null = null

const currentRole = computed<UserRole>(() => authStore.user?.role ?? null)
const organizationType = computed(() => authStore.user?.activeOrganization?.type ?? '')
const canManageOrgOjtHours = computed(
  () =>
    currentRole.value === 'school' &&
    (organizationType.value === 'school' || organizationType.value === ''),
)
const canEditSchoolOrg = computed(
  () =>
    currentRole.value === 'school' &&
    (hasPermission('org.manage_members') || hasPermission('manage_users')),
)
const courseRequirements = ref<CourseRequirement[]>([])
const courseRequirementMeta = ref<CourseRequirementMeta | null>(null)
const flaggedCourseStudents = ref<FlaggedStudent[]>([])
const loadingCourseRequirements = ref(false)
const savingCourseRequirement = ref(false)
const courseRequirementError = ref('')
const courseHourDrafts = ref<Record<string, string>>({})
const courseYearDrafts = ref<Record<string, string[]>>({})
const courseSkillCategoryDrafts = ref<Record<string, string[]>>({})
const skillCategoryOptions = computed(() => {
  const fromApi = courseRequirementMeta.value?.skillCatalogCategories
  return fromApi?.length ? fromApi : [...SKILL_CATALOG_CATEGORIES]
})
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
  const tabs: Array<{ key: SettingsTab; label: string }> = [{ key: 'account', label: 'My Account' }]
  if (currentRole.value === 'school' || currentRole.value === 'company') {
    tabs.push({ key: 'organization', label: 'Organization' })
    tabs.push({
      key: 'courses',
      label: currentRole.value === 'school' ? 'Courses & Hours' : 'Accepted Courses',
    })
    tabs.push({ key: 'public', label: 'Public Page' })
  }
  tabs.push({ key: 'notifications', label: 'Notifications' })
  if (currentRole.value === 'admin') {
    tabs.push({ key: 'system', label: 'System Settings' })
  }
  return tabs
})

const activeOrganizationId = computed(() => authStore.user?.activeOrganization?.id ?? '')

const emailHelperText = computed(() => {
  if (currentRole.value === 'student') return 'Managed by your school.'
  if (
    canEditSchoolOrg.value ||
    hasPermission('org.manage_profile') ||
    hasPermission('manage_profile') ||
    hasPermission('org.manage_members') ||
    hasPermission('manage_users')
  ) {
    return 'To change your email, contact support.'
  }
  return 'Contact admin to change email.'
})

const organizationLocationHelper = computed(() => {
  if (currentRole.value === 'school') return 'Used for student accounts and the directory.'
  if (organizationType.value === 'municipal_hall') {
    return 'Schools use your location to find and filter municipal halls.'
  }
  return 'Schools use your location to find and filter companies.'
})

const canUpdatePassword = computed(() => {
  const current = String(passwordValues.currentPassword ?? '').trim()
  const next = String(passwordValues.newPassword ?? '')
  const confirm = String(passwordValues.confirmPassword ?? '')
  return current.length > 0 && meetsPasswordPolicy(next) && next === confirm
})

const passwordConfirmFeedback = computed(() => {
  const confirm = String(passwordValues.confirmPassword ?? '')
  if (!confirm) return null
  const next = String(passwordValues.newPassword ?? '')
  return next === confirm ? 'match' : 'mismatch'
})

const profileSchema = toTypedSchema(
  z.object({
    fullName: z.string().min(1, 'Full name is required.'),
    phone: z.string().min(1, 'Phone number is required.'),
    studentNumber: z.string().optional(),
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
      newPassword: z.string().min(1, 'New password is required.'),
      confirmPassword: z.string().min(1, 'Please confirm the new password.'),
    })
    .superRefine((value, ctx) => {
      if (!meetsPasswordPolicy(value.newPassword)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['newPassword'],
          message: 'Password must be at least 8 characters and include a letter and a number.',
        })
      }
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
    studentNumber: '',
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
  values: passwordValues,
} = useForm<PasswordForm>({
  validationSchema: passwordSchema,
  initialValues: {
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  },
})

const schoolTypeSelectOptions = computed(() => {
  const current = String(profileValues.schoolType ?? '').trim()
  if (current && !(SCHOOL_TYPE_OPTIONS as readonly string[]).includes(current)) {
    return [current, ...SCHOOL_TYPE_OPTIONS]
  }
  return [...SCHOOL_TYPE_OPTIONS]
})

const passwordRequirementChecks = computed(() => {
  const password = String(passwordValues.newPassword ?? '')
  return [
    { id: 'length', label: 'At least 8 characters', ok: password.length >= 8 },
    { id: 'letter', label: 'Includes a letter', ok: /[A-Za-z]/.test(password) },
    { id: 'number', label: 'Includes a number', ok: /[0-9]/.test(password) },
  ]
})

const studentNumberLocked = computed(() => {
  if (currentRole.value !== 'student') return true
  return Boolean(String(currentProfile.value.studentNumber ?? currentProfile.value.studentId ?? '').trim())
})

const roleSpecificFields = computed<SettingsField[]>(() => {
  if (currentRole.value === 'student') {
    return [
      { key: 'course', label: 'Course', readonly: true },
      { key: 'yearLevel', label: 'Year Level', options: ['3rd Year', '4th Year'], readonly: true },
      { key: 'schoolName', label: 'School Name', readonly: true },
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

const organizationRoleFields = computed<SettingsField[]>(() => {
  if (currentRole.value === 'school') {
    if (!canEditSchoolOrg.value) {
      return [{ key: 'schoolName', label: 'School Name', readonly: true }]
    }
    return [
      { key: 'schoolName', label: 'School Name' },
      { key: 'contactPerson', label: 'Contact Person' },
      {
        key: 'schoolType',
        label: 'School Type',
        options: schoolTypeSelectOptions.value,
      },
      { key: 'website', label: 'Website' },
    ]
  }

  if (currentRole.value === 'company') {
    const isMunicipal = organizationType.value === 'municipal_hall'
    const canEditOrg = hasPermission('org.manage_profile') || hasPermission('manage_profile')
    return [
      {
        key: 'companyName',
        label: isMunicipal ? 'Official Office Name' : 'Company Name',
        readonly: !canEditOrg,
      },
      {
        key: 'industry',
        label: isMunicipal ? 'Office Classification' : 'Industry',
        readonly: !canEditOrg,
      },
      { key: 'website', label: 'Website', readonly: !canEditOrg },
    ]
  }

  return []
})

const profilePayloadRoleFields = computed<SettingsField[]>(() => {
  if (currentRole.value === 'school' || currentRole.value === 'company') {
    return organizationRoleFields.value
  }
  return roleSpecificFields.value
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
      { key: 'registration', label: 'Organization verification update', description: 'Get notified when your school verification status changes.' },
      { key: 'endorsement', label: 'Endorsement request', description: "Get notified when a student's application needs your endorsement." },
      { key: 'hours', label: 'Hours agreement updates', description: 'Get notified when a placement hours agreement needs attention.' },
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
      studentNumber: String(profile.studentNumber ?? profile.studentId ?? ''),
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
    studentNumber: payload.profile.studentNumber ?? '',
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
  notificationBaseline.value = JSON.stringify(notificationPreferences.value)
  captureProfileBaseline()

  if (payload.systemSettings) {
    systemSettings.value = {
      schoolYear: payload.systemSettings.schoolYear ?? '',
      semester: payload.systemSettings.semester ?? '',
      allowRegistrations: payload.systemSettings.allowRegistrations ?? true,
      maintenanceMode: payload.systemSettings.maintenanceMode ?? false,
    }
  }
}

function accountSnapshot() {
  const base: Record<string, unknown> = {
    fullName: profileValues.fullName ?? '',
    phone: profileValues.phone ?? '',
    avatarPending: Boolean(avatarFile.value),
  }
  if (currentRole.value === 'student') {
    base.studentNumber = profileValues.studentNumber ?? ''
    base.course = profileValues.course ?? ''
    base.yearLevel = profileValues.yearLevel ?? ''
    base.schoolName = profileValues.schoolName ?? ''
  }
  if (currentRole.value === 'admin') {
    base.department = profileValues.department ?? ''
    base.position = profileValues.position ?? ''
  }
  return JSON.stringify(base)
}

function organizationSnapshot() {
  if (currentRole.value !== 'school' && currentRole.value !== 'company') return ''
  return JSON.stringify({
    contactPerson: profileValues.contactPerson ?? '',
    schoolType: profileValues.schoolType ?? '',
    schoolName: profileValues.schoolName ?? '',
    companyName: profileValues.companyName ?? '',
    industry: profileValues.industry ?? '',
    website: profileValues.website ?? '',
    schoolAddress: profileValues.schoolAddress ?? '',
    address: profileValues.address ?? '',
    addressCity: profileValues.addressCity ?? '',
    addressBarangay: profileValues.addressBarangay ?? '',
    addressZipCode: profileValues.addressZipCode ?? '',
    officialSchoolEmail: profileValues.officialSchoolEmail ?? '',
  })
}

function coursesSnapshot() {
  return JSON.stringify([...organizationCourses.value].sort())
}

function captureProfileBaseline() {
  accountBaseline.value = accountSnapshot()
  organizationBaseline.value = organizationSnapshot()
  coursesBaseline.value = coursesSnapshot()
}

const accountDirty = computed(() => {
  if (loading.value || !accountBaseline.value) return false
  return accountSnapshot() !== accountBaseline.value
})

const organizationDirty = computed(() => {
  if (loading.value || !organizationBaseline.value) return false
  if (currentRole.value !== 'school' && currentRole.value !== 'company') return false
  return organizationSnapshot() !== organizationBaseline.value
})

const coursesDirty = computed(() => {
  if (loading.value || !coursesBaseline.value) return false
  if (currentRole.value !== 'school' && currentRole.value !== 'company') return false
  return coursesSnapshot() !== coursesBaseline.value
})

const currentTabDirty = computed(() => {
  switch (activeTab.value) {
    case 'account':
      return accountDirty.value
    case 'organization':
      return organizationDirty.value
    case 'courses':
      return coursesDirty.value
    case 'public':
      return publicProfileDirty.value
    case 'notifications':
      return notificationsDirty.value
    default:
      return false
  }
})

const stickySaveVisible = computed(() => currentTabDirty.value)

function flashSaved(message = 'Saved') {
  savedFlash.value = message
  if (savedFlashTimer) clearTimeout(savedFlashTimer)
  savedFlashTimer = setTimeout(() => {
    savedFlash.value = ''
    savedFlashTimer = null
  }, 2000)
}

const notificationsDirty = computed(() => {
  if (!notificationBaseline.value) return false
  return JSON.stringify(notificationPreferences.value) !== notificationBaseline.value
})

function flaggedReasonLabel(code: string) {
  return FLAGGED_REASON_LABELS[code] || code.split('_').join(' ')
}

function isCourseConfigured(courseName: string) {
  const requirement = requirementForCourse(courseName)
  if (!requirement) return false
  if (typeof requirement.configComplete === 'boolean') return requirement.configComplete
  return Number(requirement.requiredHours) >= 1 && requirement.eligibleYearLevels.length > 0
}

function workspaceHomePath() {
  if (currentRole.value === 'school') return '/school'
  if (currentRole.value === 'company') return '/dashboard'
  if (currentRole.value === 'admin') return '/admin/overview'
  if (currentRole.value === 'student') return '/intern'
  return '/'
}

const subscriptionOk = computed(() => {
  const subscriptionStatus = String(authStore.user?.subscriptionAccess?.status ?? '')
  return ['trial', 'active', 'free', 'pending_payment'].includes(subscriptionStatus)
})

const setupChecklist = computed(() => {
  const subscriptionDone = subscriptionOk.value

  if (currentRole.value === 'school') {
    const profileComplete = Boolean(
      String(profileValues.schoolName ?? '').trim() &&
        String(profileValues.schoolAddress ?? profileValues.address ?? '').trim() &&
        String(profileValues.phone ?? '').trim() &&
        String(profileValues.addressCity ?? '').trim(),
    )
    const hasCourse = organizationCourses.value.length > 0
    const everyCourseConfigured =
      hasCourse && organizationCourses.value.every((course) => isCourseConfigured(course))
    const emailDomainSet = Boolean(String(profileValues.officialSchoolEmail ?? '').trim())

    return [
      {
        id: 'profile',
        label: 'Profile complete',
        done: profileComplete,
        action: () => {
          activeTab.value = 'organization'
        },
      },
      {
        id: 'courses',
        label: 'At least one course added',
        done: hasCourse,
        action: () => {
          activeTab.value = 'courses'
        },
      },
      {
        id: 'configured',
        label: 'Every course configured',
        done: everyCourseConfigured,
        action: () => {
          activeTab.value = 'courses'
        },
      },
      {
        id: 'email',
        label: 'Email domain set',
        done: emailDomainSet,
        action: () => {
          activeTab.value = 'organization'
        },
      },
      {
        id: 'agreement',
        label: 'Active agreement exists',
        done: hasActiveAgreement.value,
        action: () => {
          void router.push({ name: 'agreements' })
        },
      },
      {
        id: 'subscription',
        label: 'Subscription active',
        done: subscriptionDone,
        action: () => {
          void router.push({ name: 'organization-subscription' })
        },
      },
    ]
  }

  if (currentRole.value === 'company') {
    const street = String(profileValues.address ?? '').trim()
    const profileComplete = Boolean(
      String(profileValues.companyName ?? '').trim() &&
        street &&
        String(profileValues.phone ?? '').trim() &&
        String(profileValues.addressCity ?? '').trim(),
    )
    const citySet = Boolean(String(profileValues.addressCity ?? '').trim())
    const hasCourse = organizationCourses.value.length > 0

    return [
      {
        id: 'profile',
        label: 'Profile complete',
        done: profileComplete,
        action: () => {
          activeTab.value = 'organization'
        },
      },
      {
        id: 'city',
        label: 'City set',
        done: citySet,
        action: () => {
          activeTab.value = 'organization'
        },
      },
      {
        id: 'courses',
        label: 'At least one accepted course',
        done: hasCourse,
        action: () => {
          activeTab.value = 'courses'
        },
      },
      {
        id: 'agreement',
        label: 'Active agreement',
        done: hasActiveAgreement.value,
        action: () => {
          void router.push({ name: 'agreements' })
        },
      },
      {
        id: 'published-job',
        label: 'At least one published job',
        done: hasPublishedJob.value,
        action: () => {
          void router.push(workspaceHomePath())
        },
      },
      {
        id: 'subscription',
        label: 'Subscription active',
        done: subscriptionDone,
        action: () => {
          void router.push({ name: 'organization-subscription' })
        },
      },
    ]
  }

  return []
})

const showSetupChecklist = computed(
  () => !loading.value && (currentRole.value === 'school' || currentRole.value === 'company') && setupChecklist.value.length > 0,
)

async function loadSetupAgreementFlag() {
  if (currentRole.value !== 'school' && currentRole.value !== 'company') {
    hasActiveAgreement.value = false
    return
  }
  try {
    const agreements = await listAgreements()
    hasActiveAgreement.value = agreements.some(
      (item) => item.status === 'active' || item.status === 'pending_amendment',
    )
  } catch {
    hasActiveAgreement.value = false
  }
}

function openAccountPhotoSection() {
  activeTab.value = 'account'
  void nextTick(() => {
    accountPhotoSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    openFilePicker()
  })
}

function onPublicProfileDirty(dirty: boolean) {
  publicProfileDirty.value = dirty
}

async function discardStickyChanges() {
  if (activeTab.value === 'notifications') {
    try {
      notificationPreferences.value = JSON.parse(notificationBaseline.value || '{}')
    } catch {
      await loadSettingsData()
    }
    return
  }
  if (activeTab.value === 'public') {
    publicProfileSectionRef.value?.discardChanges()
    return
  }
  await loadSettingsData()
  avatarFile.value = null
  avatarPreviewUrl.value = ''
}

async function saveStickyChanges() {
  if (activeTab.value === 'notifications') {
    await saveNotifications()
    return
  }
  if (activeTab.value === 'public') {
    await publicProfileSectionRef.value?.saveIfDirty()
    flashSaved()
    return
  }
  await saveProfile()
}

async function loadCourseRequirements() {
  if (currentRole.value !== 'school') return
  loadingCourseRequirements.value = true
  courseRequirementError.value = ''
  try {
    const { requirements, meta } = await listCourseRequirements()
    courseRequirements.value = requirements
    courseRequirementMeta.value = meta
    applyCourseDrafts()
    flaggedCourseStudents.value = meta?.flaggedStudentCount
      ? await listFlaggedCourseStudents().catch(() => [])
      : []
  } catch (caughtError) {
    courseRequirementError.value =
      caughtError instanceof Error ? caughtError.message : 'Unable to load course OJT requirements.'
    applyCourseDrafts()
  } finally {
    loadingCourseRequirements.value = false
  }
}

function requirementForCourse(courseName: string) {
  const key = courseName.trim().toLowerCase()
  return courseRequirements.value.find((item) => item.courseName.trim().toLowerCase() === key) ?? null
}

function defaultSkillCategoriesForCourse(courseName: string): string[] {
  const requirement = requirementForCourse(courseName)
  if (requirement?.recommendedSkillCategories?.length) {
    return [...requirement.recommendedSkillCategories]
  }
  return recommendedSkillCategoriesForCourse(courseName)
}

function applyCourseDrafts() {
  const hours: Record<string, string> = {}
  const years: Record<string, string[]> = {}
  const skillCategories: Record<string, string[]> = {}
  for (const course of organizationCourses.value) {
    const requirement = requirementForCourse(course)
    hours[course] = requirement ? String(requirement.requiredHours) : ''
    years[course] = requirement ? [...requirement.eligibleYearLevels] : [...DEFAULT_ELIGIBLE_YEAR_LEVELS]
    skillCategories[course] = defaultSkillCategoriesForCourse(course)
  }
  courseHourDrafts.value = hours
  courseYearDrafts.value = years
  courseSkillCategoryDrafts.value = skillCategories
}

watch(organizationCourses, (courses) => {
  if (currentRole.value !== 'school') return
  const hours = { ...courseHourDrafts.value }
  const years = { ...courseYearDrafts.value }
  const skillCategories = { ...courseSkillCategoryDrafts.value }
  let changed = false
  for (const course of courses) {
    if (hours[course] !== undefined) continue
    const requirement = requirementForCourse(course)
    hours[course] = requirement ? String(requirement.requiredHours) : ''
    years[course] = requirement ? [...requirement.eligibleYearLevels] : [...DEFAULT_ELIGIBLE_YEAR_LEVELS]
    skillCategories[course] = defaultSkillCategoriesForCourse(course)
    changed = true
  }
  if (!changed) return
  courseHourDrafts.value = hours
  courseYearDrafts.value = years
  courseSkillCategoryDrafts.value = skillCategories
})

function courseHoursAreValid(value: string) {
  const hours = Number(value)
  return Number.isInteger(hours) && hours >= 1 && hours <= 10000
}

function toggleCourseYearDraft(courseName: string, level: string) {
  const current = courseYearDrafts.value[courseName] ?? []
  courseYearDrafts.value = {
    ...courseYearDrafts.value,
    [courseName]: current.includes(level)
      ? current.filter((item) => item !== level)
      : [...current, level],
  }
}

function toggleCourseSkillCategoryDraft(courseName: string, category: string) {
  const current = courseSkillCategoryDrafts.value[courseName] ?? []
  courseSkillCategoryDrafts.value = {
    ...courseSkillCategoryDrafts.value,
    [courseName]: current.includes(category)
      ? current.filter((item) => item !== category)
      : [...current, category],
  }
}

async function saveCourseRequirementHours(courseName: string) {
  const draft = courseHourDrafts.value[courseName] ?? ''
  const years = courseYearDrafts.value[courseName] ?? []
  if (!courseHoursAreValid(draft)) {
    courseHourErrors.value = {
      ...courseHourErrors.value,
      [courseName]: 'Enter a whole number of at least 1 hour.',
    }
    courseRequirementError.value = 'Required hours must be a whole number of at least 1.'
    return
  }
  if (years.length === 0) {
    courseHourErrors.value = { ...courseHourErrors.value, [courseName]: '' }
    courseRequirementError.value = 'Select at least one eligible year level.'
    return
  }

  const skillCategories = courseSkillCategoryDrafts.value[courseName] ?? []

  savingCourseRequirement.value = true
  courseRequirementError.value = ''
  courseHourErrors.value = { ...courseHourErrors.value, [courseName]: '' }
  try {
    const existing = requirementForCourse(courseName)
    const updated = existing
      ? await updateCourseRequirement(existing.id, courseName, Number(draft), years, skillCategories)
      : await createCourseRequirement(courseName, Number(draft), years, skillCategories)
    courseRequirements.value = [
      ...courseRequirements.value.filter((item) => item.id !== updated.id && item.courseName.trim().toLowerCase() !== courseName.trim().toLowerCase()),
      updated,
    ].sort((left, right) => left.courseName.localeCompare(right.courseName))
    applyCourseDrafts()
    await loadCourseRequirements()
  } catch (caughtError) {
    courseRequirementError.value =
      caughtError instanceof Error ? caughtError.message : 'Unable to update the course requirement.'
  } finally {
    savingCourseRequirement.value = false
  }
}

async function loadStudentSkills() {
  if (currentRole.value !== 'student') return
  try {
    const res = await fetchStudentSkills()
    profileSkills.value = res.data || []
  } catch {
    const embedded = authStore.user?.profile as Record<string, unknown> | undefined
    const raw = Array.isArray(embedded?.skills) ? (embedded?.skills as SelectedSkill[]) : []
    profileSkills.value = raw
  }
}

async function onSkillsSaved(next: SelectedSkill[]) {
  savingSkills.value = true
  try {
    const res = await saveStudentSkills(
      next.map((skill) => ({ id: skill.id, level: skill.level || 'beginner' })),
    )
    profileSkills.value = res.data || next
    await authStore.refreshUser()
    success('Skills saved', { description: 'Your skills are now on your profile.' })
  } catch (err) {
    error(err, { fallback: 'Could not save skills. Please try again.' })
  } finally {
    savingSkills.value = false
  }
}

async function loadSettingsData() {
  loading.value = true
  try {
    const payload = await fetchSettings()
    applySettings(payload)
    if (canManageOrgOjtHours.value) {
      const ojtSettings = await fetchOrgOjtSettings().catch(() => ({ requiredHours: 500 }))
      orgRequiredHours.value = String(ojtSettings.requiredHours || 500)
    }
    await Promise.all([loadCourseRequirements(), loadStudentSkills(), loadSetupAgreementFlag()])
  } catch {
    applySettings(buildFallbackSettings())
    await loadSetupAgreementFlag()
  } finally {
    loading.value = false
    captureProfileBaseline()
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

  // Students persist contact and may fill a missing student number once.
  if (currentRole.value === 'student') {
    if (!studentNumberLocked.value) {
      payload.studentNumber = normalizeStudentNumber(profileValues.studentNumber)
    }
    return payload
  }

  for (const field of profilePayloadRoleFields.value) {
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

async function removeOrganizationCourse(courseToRemove: string) {
  const assignedCount = flaggedCourseStudents.value.filter(
    (row) => String(row.course || '').trim().toLowerCase() === courseToRemove.trim().toLowerCase(),
  ).length
  const confirmation = await Swal.fire({
    icon: 'warning',
    title: 'Remove this course?',
    text:
      assignedCount > 0
        ? `${assignedCount} flagged student${assignedCount === 1 ? '' : 's'} currently list this course. Removing it does not change student records, but course setup for it will disappear from Configure.`
        : 'This course will be removed from your school list. You can add it again later.',
    showCancelButton: true,
    confirmButtonText: 'Remove course',
    cancelButtonText: 'Keep course',
    confirmButtonColor: '#dc2626',
  })
  if (!confirmation.isConfirmed) return
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
      if (organizationCourses.value.includes(option)) continue
      organizationCourseTomSelect.addOption({ value: option, text: option, optgroup: group.label })
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

  if (activeTab.value !== 'courses' || loading.value) {
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
    hideSelected: true,
    placeholder: currentRole.value === 'school'
      ? 'Select one or more school courses'
      : 'Select one or more accepted courses',
    render: {
      optgroup_header(data: { label?: string }, escape: (s: string) => string) {
        return `<div class="ts-program-header">${escape(data.label ?? '')}</div>`
      },
      option(data: { text?: string }, escape: (s: string) => string) {
        return `<div class="ts-course-option">${escape(data.text ?? '')}</div>`
      },
      item(data: { text?: string }, escape: (s: string) => string) {
        return `<div>${escape(data.text ?? '')}</div>`
      },
      option_create(data: { input?: string }, escape: (s: string) => string) {
        return `<div class="create">Add custom course: <strong>${escape(data.input ?? '')}</strong></div>`
      },
    },
    onChange(value: string | string[]) {
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
    createFilter(input: string) {
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
  if (currentRole.value === 'student' && !studentNumberLocked.value) {
    const numberError = studentNumberErrorMessage(profileValues.studentNumber, true)
    if (numberError) {
      setProfileFieldValue('studentNumber', profileValues.studentNumber ?? '')
      error(numberError)
      return
    }
  }

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
    await saveProfileSettings(payload, currentRole.value)
    const refreshedUser = await authStore.refreshUser()
    if (refreshedUser) {
      authStore.setUserProfile(refreshedUser)
    }
    success('Profile updated successfully')
    captureProfileBaseline()
    flashSaved()
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
    flashSaved()
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update password.',
    })
  } finally {
    savingPassword.value = false
  }
})

async function saveNotifications() {
  if (!notificationsDirty.value) return
  savingNotifications.value = true
  try {
    await saveNotificationPreferences(notificationPreferences.value)
    notificationBaseline.value = JSON.stringify(notificationPreferences.value)
    success('Notification preferences saved.')
    flashSaved()
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

async function saveOrgOjtHours() {
  savingOrgOjt.value = true
  try {
    const hours = Math.max(1, Math.floor(Number(orgRequiredHours.value) || 0))
    if (!Number.isFinite(hours) || hours < 1) {
      throw new Error('OJT required hours must be at least 1.')
    }
    const saved = await saveOrgOjtSettings({ requiredHours: hours })
    orgRequiredHours.value = String(saved.requiredHours)
    success('Default required hours saved.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to save default required hours.',
    })
  } finally {
    savingOrgOjt.value = false
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

function hasUnsavedProfileChanges() {
  return (
    accountDirty.value ||
    organizationDirty.value ||
    coursesDirty.value ||
    publicProfileDirty.value ||
    notificationsDirty.value
  )
}

function onBeforeUnload(event: BeforeUnloadEvent) {
  if (!hasUnsavedProfileChanges()) return
  event.preventDefault()
  event.returnValue = ''
}

onBeforeRouteLeave(async () => {
  if (!hasUnsavedProfileChanges()) return true
  const confirmation = await Swal.fire({
    icon: 'warning',
    title: 'Leave without saving?',
    text: 'You have unsaved profile changes. Leave this page and discard them?',
    showCancelButton: true,
    confirmButtonText: 'Leave',
    cancelButtonText: 'Stay',
    confirmButtonColor: '#dc2626',
  })
  return confirmation.isConfirmed
})

function subscribePublishedJobsFlag() {
  unsubscribeCompanyJobs?.()
  unsubscribeCompanyJobs = null
  hasPublishedJob.value = false
  if (currentRole.value !== 'company') return
  const uid = authStore.user?.uid
  // Subscription uses company uid; skip if session has no uid yet.
  if (!uid) return
  unsubscribeCompanyJobs = subscribeCompanyInternships(uid, (items) => {
    hasPublishedJob.value = items.some((job) => normalizeJobStatus(job.status) === 'active')
  })
}

watch(
  () => route.query.tab,
  () => applyTabFromRoute(),
)

onMounted(() => {
  applyTabFromRoute()
  window.addEventListener('beforeunload', onBeforeUnload)
  void loadSettingsData()
  subscribePublishedJobsFlag()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload)
  destroyOrganizationCourseTomSelect()
  unsubscribeCompanyJobs?.()
  if (savedFlashTimer) clearTimeout(savedFlashTimer)
})
</script>

<template>
  <section class="space-y-6 pb-24">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div v-if="!props.embedded" class="space-y-2">
        <h2 class="text-3xl font-semibold tracking-tight text-foreground">Settings</h2>
        <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
          Manage your account, organization, and notifications.
        </p>
      </div>
      <Button
        type="button"
        variant="outline"
        class="gap-2"
        @click="router.push(workspaceHomePath())"
      >
        <ArrowLeft class="h-4 w-4" />
        Back to workspace
      </Button>
    </div>

    <Card
      v-if="showSetupChecklist"
      class="border-border/80 shadow-sm"
    >
      <CardHeader class="space-y-1">
        <h3 class="text-lg font-semibold text-foreground">Setup checklist</h3>
        <p class="text-sm text-muted-foreground">
          {{
            currentRole === 'company'
              ? 'Finish these items so schools can find you and students can apply.'
              : 'Finish these items so students can apply and staff can manage OJT.'
          }}
        </p>
      </CardHeader>
      <CardContent class="grid gap-2 sm:grid-cols-2">
        <button
          v-for="item in setupChecklist"
          :key="item.id"
          type="button"
          class="flex items-center justify-between gap-3 rounded-xl border border-border px-3 py-2.5 text-left transition hover:bg-accent/50"
          @click="item.action()"
        >
          <span class="text-sm text-foreground">{{ item.label }}</span>
          <Badge :variant="item.done ? 'success' : 'warning'">
            {{ item.done ? 'Done' : 'Needed' }}
          </Badge>
        </button>
      </CardContent>
    </Card>

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

          <div v-else-if="activeTab === 'account'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-foreground">My Account</h3>
              <p class="mt-2 text-sm text-muted-foreground">Keep your profile accurate so the right people can contact you and review your information.</p>
            </div>

            <div
              ref="accountPhotoSectionRef"
              class="flex flex-col gap-4 rounded-2xl border border-border bg-muted p-5 sm:flex-row sm:items-center"
            >
              <Avatar :src="currentAvatar" :fallback="userInitials" :alt="profileValues.fullName" class="h-20 w-20" />
              <div class="space-y-2">
                <p class="text-sm font-medium text-foreground">Profile photo</p>
                <p class="text-sm text-muted-foreground">Upload a clear avatar so your account is easy to recognize.</p>
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

            <form class="w-full max-w-3xl space-y-5" @submit.prevent>
              <FormField v-slot="{ componentField, errorMessage: fieldError }" name="fullName">
                <FormItem>
                  <FormLabel for="settingsFullName">Full Name</FormLabel>
                  <FormControl>
                    <Input
                      id="settingsFullName"
                      v-bind="componentField"
                      :model-value="profileValues.fullName"
                      :readonly="currentRole === 'student'"
                      :disabled="currentRole === 'student'"
                      @update:modelValue="setProfileFieldValue('fullName', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || profileErrors.fullName" />
                  <p v-if="currentRole === 'student'" class="text-sm text-muted-foreground">
                    Managed by your school.
                  </p>
                </FormItem>
              </FormField>

              <FormItem>
                <FormLabel for="settingsEmail">Email</FormLabel>
                <FormControl>
                  <Input id="settingsEmail" :model-value="currentEmail" type="email" readonly disabled />
                </FormControl>
                <p class="text-sm text-muted-foreground">{{ emailHelperText }}</p>
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
                v-if="currentRole === 'student'"
                v-slot="{ componentField, errorMessage: fieldError }"
                name="studentNumber"
              >
                <FormItem>
                  <FormLabel for="settingsStudentNumber">
                    Student Number
                    <span v-if="!studentNumberLocked" class="text-red-600" aria-hidden="true">*</span>
                  </FormLabel>
                  <FormControl>
                    <Input
                      id="settingsStudentNumber"
                      v-bind="componentField"
                      :model-value="profileValues.studentNumber"
                      :readonly="studentNumberLocked"
                      :disabled="studentNumberLocked"
                      placeholder="2024-0001"
                      @update:modelValue="setProfileFieldValue('studentNumber', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || profileErrors.studentNumber" />
                  <p v-if="studentNumberLocked" class="text-sm text-muted-foreground">
                    Managed by your school. Ask them if it needs a correction.
                  </p>
                  <p v-else class="text-sm text-amber-700 dark:text-amber-300">
                    Your school has not set a student number yet. Please enter it now so your records stay complete.
                  </p>
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
                      :disabled="field.readonly"
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
                      :readonly="field.readonly"
                      :disabled="field.readonly"
                      @update:modelValue="setProfileFieldValue(field.key, $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || profileErrors[field.key]" />
                  <p v-if="field.readonly" class="text-sm text-muted-foreground">Managed by your school.</p>
                </FormItem>
              </FormField>
            </form>

            <div
              v-if="currentRole === 'student'"
              class="space-y-4 rounded-2xl border border-border bg-muted p-5"
            >
              <div class="space-y-1">
                <h4 class="text-base font-semibold text-foreground">My Skills</h4>
                <p class="text-sm text-muted-foreground">
                  Choose up to 15 skills from the shared list so companies can understand what you already know.
                </p>
              </div>
              <SkillChips
                :skills="profileSkills"
                mode="intern"
                empty-text="No skills yet. Add a few to help hosts find you."
              />
              <Button
                type="button"
                variant="outline"
                :disabled="savingSkills"
                @click="skillsWizardOpen = true"
              >
                {{ savingSkills ? 'Saving…' : profileSkills.length ? 'Edit skills' : 'Add skills' }}
              </Button>
            </div>

            <Card class="w-full max-w-3xl border-border/80 shadow-none">
              <CardHeader>
                <h4 class="text-lg font-semibold text-foreground">Appearance</h4>
                <p class="text-sm text-muted-foreground">Choose light or dark mode for the workspace.</p>
              </CardHeader>
              <CardContent>
                <div class="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    :variant="colorMode === 'light' ? 'default' : 'outline'"
                    class="gap-2"
                    @click="colorMode = 'light'"
                  >
                    <Sun class="h-4 w-4" />
                    Light
                  </Button>
                  <Button
                    type="button"
                    :variant="colorMode === 'dark' ? 'default' : 'outline'"
                    class="gap-2"
                    @click="colorMode = 'dark'"
                  >
                    <Moon class="h-4 w-4" />
                    Dark
                  </Button>
                </div>
              </CardContent>
            </Card>

            <Card class="w-full max-w-3xl border-border/80 shadow-none">
              <CardHeader>
                <h4 class="text-lg font-semibold text-foreground">Change Password</h4>
                <p class="text-sm text-muted-foreground">Change your password.</p>
              </CardHeader>
              <CardContent>
                <form class="space-y-5" @submit="updatePassword">
                  <FormField v-slot="{ componentField, errorMessage: fieldError }" name="currentPassword">
                    <FormItem>
                      <FormLabel for="currentPassword">Current Password</FormLabel>
                      <FormControl>
                        <PasswordInput
                          id="currentPassword"
                          autocomplete="current-password"
                          v-bind="componentField"
                          @update:modelValue="setPasswordFieldValue('currentPassword', $event)"
                        />
                      </FormControl>
                      <FormMessage :message="fieldError || passwordErrors.currentPassword" />
                    </FormItem>
                  </FormField>

                  <FormField v-slot="{ componentField, errorMessage: fieldError }" name="newPassword">
                    <FormItem>
                      <FormLabel for="newPassword">New Password</FormLabel>
                      <FormControl>
                        <PasswordInput
                          id="newPassword"
                          autocomplete="new-password"
                          v-bind="componentField"
                          @update:modelValue="setPasswordFieldValue('newPassword', $event)"
                        />
                      </FormControl>
                      <ul class="mt-2 space-y-1 text-sm">
                        <li
                          v-for="check in passwordRequirementChecks"
                          :key="check.id"
                          class="flex items-center gap-2"
                          :class="check.ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'"
                        >
                          <Check class="h-3.5 w-3.5" :class="check.ok ? 'opacity-100' : 'opacity-30'" />
                          {{ check.label }}
                        </li>
                      </ul>
                      <FormMessage :message="fieldError || passwordErrors.newPassword" />
                    </FormItem>
                  </FormField>

                  <FormField v-slot="{ componentField, errorMessage: fieldError }" name="confirmPassword">
                    <FormItem>
                      <FormLabel for="confirmPassword">Confirm New Password</FormLabel>
                      <FormControl>
                        <PasswordInput
                          id="confirmPassword"
                          autocomplete="new-password"
                          v-bind="componentField"
                          @update:modelValue="setPasswordFieldValue('confirmPassword', $event)"
                        />
                      </FormControl>
                      <p
                        v-if="passwordConfirmFeedback === 'match'"
                        class="text-sm text-emerald-700 dark:text-emerald-300"
                      >
                        Passwords match
                      </p>
                      <p
                        v-else-if="passwordConfirmFeedback === 'mismatch'"
                        class="text-sm text-destructive"
                      >
                        Passwords don't match
                      </p>
                      <FormMessage :message="fieldError || passwordErrors.confirmPassword" />
                    </FormItem>
                  </FormField>

                  <Button type="submit" :disabled="savingPassword || !canUpdatePassword">
                    {{ savingPassword ? 'Updating...' : 'Update Password' }}
                  </Button>
                </form>
              </CardContent>
            </Card>
          </div>

          <div v-else-if="activeTab === 'organization'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-foreground">Organization</h3>
              <p class="mt-2 text-sm text-muted-foreground">
                Update your organization details and registered Cavite address.
              </p>
            </div>

            <p class="text-sm text-muted-foreground">
              Profile photo for listings uses your account avatar.
              <button
                type="button"
                class="font-medium text-primary underline-offset-4 hover:underline"
                @click="openAccountPhotoSection"
              >
                Change in My Account
              </button>
            </p>

            <form class="space-y-5" @submit.prevent>
              <FormField
                v-for="field in organizationRoleFields"
                :key="field.key"
                v-slot="{ componentField, errorMessage: fieldError }"
                :name="field.key"
              >
                <FormItem>
                  <FormLabel :for="`org-${field.key}`">{{ field.label }}</FormLabel>
                  <FormControl>
                    <Select
                      v-if="field.options"
                      :id="`org-${field.key}`"
                      v-bind="componentField"
                      :model-value="profileValues[field.key]"
                      :disabled="field.readonly"
                      @update:modelValue="setProfileFieldValue(field.key, $event)"
                    >
                      <option value="">Select {{ field.label.toLowerCase() }}</option>
                      <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                    </Select>
                    <Input
                      v-else
                      :id="`org-${field.key}`"
                      v-bind="componentField"
                      :model-value="profileValues[field.key]"
                      :readonly="field.readonly"
                      :disabled="field.readonly"
                      @update:modelValue="setProfileFieldValue(field.key, $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || profileErrors[field.key]" />
                  <p v-if="field.readonly" class="text-sm text-muted-foreground">Managed by your school.</p>
                </FormItem>
              </FormField>

              <div
                v-if="currentRole === 'company' || canEditSchoolOrg"
                class="space-y-4 rounded-2xl border border-border bg-muted p-5"
              >
                <div class="space-y-1">
                  <h4 class="text-base font-semibold text-foreground">
                    {{
                      currentRole === 'school'
                        ? 'School Address'
                        : organizationType === 'municipal_hall'
                          ? 'Municipal Hall Address'
                          : 'Company Address'
                    }}
                  </h4>
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
                  <p class="text-sm text-muted-foreground">
                    Used when auto-generating student account emails. You can enter `@ncst.edu.ph` or a full school email.
                  </p>
                </FormItem>
                <FormItem>
                  <FormLabel for="orgStreetAddress">Street Address / Building / Landmark</FormLabel>
                  <FormControl>
                    <Input
                      id="orgStreetAddress"
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
                    <FormLabel for="orgAddressCity">
                      City / Municipality
                      <span class="text-red-600" aria-hidden="true">*</span>
                    </FormLabel>
                    <FormControl>
                      <Select
                        id="orgAddressCity"
                        :model-value="profileValues.addressCity"
                        @update:modelValue="setProfileFieldValue('addressCity', $event)"
                      >
                        <option value="">Select a city or municipality</option>
                        <optgroup v-for="group in caviteLocationGroups" :key="group.label" :label="group.label">
                          <option v-for="option in group.options" :key="option" :value="option">{{ option }}</option>
                        </optgroup>
                      </Select>
                    </FormControl>
                    <p class="text-sm text-muted-foreground">{{ organizationLocationHelper }}</p>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="orgAddressBarangay">
                      Barangay
                      <span class="text-red-600" aria-hidden="true">*</span>
                    </FormLabel>
                    <FormControl>
                      <Select
                        id="orgAddressBarangay"
                        :model-value="profileValues.addressBarangay"
                        :disabled="!profileValues.addressCity"
                        @update:modelValue="setProfileFieldValue('addressBarangay', $event)"
                      >
                        <option value="">{{ locationBarangayOptions.length ? 'Select a barangay' : 'Select a city or municipality first' }}</option>
                        <option v-for="option in locationBarangayOptions" :key="option" :value="option">{{ option }}</option>
                      </Select>
                    </FormControl>
                    <p class="text-sm text-muted-foreground">{{ organizationLocationHelper }}</p>
                  </FormItem>
                </div>
                <FormItem>
                  <FormLabel for="orgAddressZipCode">Zip Code</FormLabel>
                  <FormControl>
                    <Input
                      id="orgAddressZipCode"
                      :model-value="profileValues.addressZipCode"
                      @update:modelValue="setProfileFieldValue('addressZipCode', $event)"
                    />
                  </FormControl>
                </FormItem>
              </div>
            </form>
          </div>

          <div v-else-if="activeTab === 'courses'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-foreground">
                {{ currentRole === 'school' ? 'Courses & Hours' : 'Accepted Courses' }}
              </h3>
              <p class="mt-2 text-sm text-muted-foreground">
                {{ currentRole === 'school'
                  ? 'Manage school courses and OJT hour requirements.'
                  : 'Manage the courses your organization accepts for internships.' }}
              </p>
            </div>

            <div
              v-if="currentRole === 'school' && !canEditSchoolOrg"
              class="space-y-3 rounded-2xl border border-border bg-muted p-5"
            >
              <h4 class="text-base font-semibold text-foreground">School Courses</h4>
              <p class="text-sm text-muted-foreground">Visible for your teaching role. Only school admins can edit courses.</p>
              <div v-if="organizationCourses.length" class="flex flex-wrap gap-2">
                <span
                  v-for="course in organizationCourses"
                  :key="course"
                  class="inline-flex items-center rounded-full border border-border bg-card px-3 py-1.5 text-sm text-card-foreground"
                >
                  {{ course }}
                </span>
              </div>
              <p v-else class="text-sm text-muted-foreground">No school courses listed yet.</p>
            </div>

            <div
              v-else-if="currentRole === 'school' || currentRole === 'company'"
              class="space-y-4 rounded-2xl border border-border bg-muted p-5"
            >
              <div class="space-y-1">
                <h4 class="text-base font-semibold text-foreground">
                  {{ currentRole === 'school' ? 'School Courses' : 'Accepted Courses' }}
                </h4>
                <p class="text-sm text-muted-foreground">
                  {{ currentRole === 'school'
                    ? 'Add the courses or programs your school offers for student account creation and matching.'
                    : organizationType === 'municipal_hall'
                      ? 'Add the courses or programs your municipal hall accepts for internships.'
                      : 'Add the courses or programs your company accepts for internships.' }}
                </p>
              </div>
              <div class="space-y-2">
                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                  <select ref="organizationCourseSelectRef" multiple class="organization-course-select">
                    <optgroup v-for="group in courseGroups" :key="group.label" :label="group.label">
                      <option
                        v-for="option in group.options.filter((item) => !organizationCourses.includes(item))"
                        :key="option"
                        :value="option"
                      >{{ option }}</option>
                    </optgroup>
                  </select>
                  <Button type="button" variant="outline" @mousedown.prevent="addOrganizationCourseFromPicker">
                    Add Course
                  </Button>
                </div>
                <p class="text-sm text-muted-foreground">
                  Search a course, select it, then click Add Course. The list below shows only courses already added.
                </p>
              </div>
              <div v-if="organizationCourses.length" class="flex flex-wrap gap-2">
                <span
                  v-for="course in organizationCourses"
                  :key="course"
                  class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1.5 text-sm text-card-foreground"
                >
                  {{ course }}
                  <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeOrganizationCourse(course)">
                    ×
                  </button>
                </span>
              </div>
              <p v-else class="text-sm text-muted-foreground">
                {{ currentRole === 'school' ? 'No school courses added yet.' : 'No accepted courses added yet.' }}
              </p>
            </div>

                <div
                  v-if="currentRole === 'school'"
                  class="space-y-4 rounded-2xl border border-border bg-muted p-5"
                >
                  <div class="space-y-1">
                    <h4 class="text-base font-semibold text-foreground">Course OJT Requirements</h4>
                    <p class="text-sm text-muted-foreground">
                      Each course from Settings is listed here. Set required OJT hours and eligible year levels, then save.
                      Hours changes apply to future applications only.
                    </p>
                  </div>

                  <div
                    v-if="(courseRequirementMeta?.incompleteCourses?.length || 0) > 0 || (courseRequirementMeta?.flaggedStudentCount || 0) > 0"
                    class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-foreground"
                  >
                    <p class="font-semibold">Course setup needs attention</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                      <li v-for="item in courseRequirementMeta?.incompleteCourses || []" :key="item.id">
                        {{ item.courseName }} is incomplete
                        <span v-if="item.missingHours"> (hours)</span>
                        <span v-if="item.missingYearLevels"> (year levels)</span>.
                      </li>
                      <li v-if="(courseRequirementMeta?.flaggedStudentCount || 0) > 0">
                        {{ courseRequirementMeta?.flaggedStudentCount }} student{{
                          (courseRequirementMeta?.flaggedStudentCount || 0) === 1 ? '' : 's'
                        }}
                        {{ (courseRequirementMeta?.flaggedStudentCount || 0) === 1 ? 'has' : 'have' }}
                        a year level that does not match their course - review the list below. Records were not changed.
                      </li>
                    </ul>
                  </div>

                  <div
                    v-if="flaggedCourseStudents.length"
                    class="rounded-xl border border-border bg-background px-4 py-3 text-sm"
                  >
                    <p class="font-medium text-foreground">Flagged students (not modified)</p>
                    <ul class="mt-2 space-y-1 text-muted-foreground">
                      <li v-for="row in flaggedCourseStudents" :key="row.id">
                        {{ row.name || 'Student' }}
                        <span v-if="row.email">({{ row.email }})</span>
                        - {{ row.course || 'No course' }} / {{ row.yearLevel || 'No year' }}
                        <span class="text-xs">
                          ({{ row.reasons.map((reason) => flaggedReasonLabel(reason)).join('; ') }})
                        </span>
                      </li>
                    </ul>
                  </div>

                  <p v-if="courseRequirementError" class="text-sm font-medium text-destructive">
                    {{ courseRequirementError }}
                  </p>
                  <p v-if="loadingCourseRequirements" class="text-sm text-muted-foreground">
                    Loading course requirements...
                  </p>

                  <div
                    v-if="!loadingCourseRequirements && !organizationCourses.length"
                    class="rounded-xl border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
                  >
                    <p>
                      Add a course in
                      <button type="button" class="font-medium text-primary underline" @click="activeTab = 'courses'">
                        Courses &amp; Hours
                      </button>
                      first,
                    </p>
                  </div>

                  <div v-else-if="!loadingCourseRequirements && organizationCourses.length" class="space-y-4">
                    <article
                      v-for="course in organizationCourses"
                      :key="course"
                      class="rounded-xl border border-border bg-background p-4"
                    >
                      <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="space-y-2">
                          <p class="font-medium text-foreground">{{ course }}</p>
                          <Badge :variant="isCourseConfigured(course) ? 'success' : 'warning'">
                            {{ isCourseConfigured(course) ? 'Configured' : 'Needs setup' }}
                          </Badge>
                        </div>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          :disabled="savingCourseRequirement"
                          @click="saveCourseRequirementHours(course)"
                        >
                          Save course
                        </Button>
                      </div>
                      <div class="mt-3 grid gap-3 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-start">
                        <FormItem>
                          <FormLabel :for="`course-hours-${course}`">Required hours</FormLabel>
                          <FormControl>
                            <div class="flex items-center gap-2">
                              <Input
                                :id="`course-hours-${course}`"
                                v-model="courseHourDrafts[course]"
                                type="number"
                                min="1"
                                max="10000"
                                placeholder="e.g. 120"
                                @update:model-value="courseHourErrors = { ...courseHourErrors, [course]: '' }"
                              />
                              <span class="shrink-0 text-sm text-muted-foreground">hours</span>
                            </div>
                          </FormControl>
                          <p v-if="courseHourErrors[course]" class="text-sm text-destructive">
                            {{ courseHourErrors[course] }}
                          </p>
                        </FormItem>
                        <div>
                          <p class="mb-2 text-sm font-medium text-foreground">Eligible year levels</p>
                          <div class="flex flex-wrap gap-2">
                            <button
                              v-for="level in ALL_YEAR_LEVEL_OPTIONS"
                              :key="`${course}-${level}`"
                              type="button"
                              class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"
                              :class="
                                (courseYearDrafts[course] || []).includes(level)
                                  ? 'border-primary bg-primary text-primary-foreground'
                                  : 'border-border bg-muted text-muted-foreground'
                              "
                              @click="toggleCourseYearDraft(course, level)"
                            >
                              <Check
                                v-if="(courseYearDrafts[course] || []).includes(level)"
                                class="h-3 w-3"
                              />
                              {{ level }}
                            </button>
                          </div>
                        </div>
                      </div>
                      <div class="mt-4">
                        <p class="mb-2 text-sm font-medium text-foreground">Recommended skill areas</p>
                        <p class="mb-2 text-xs text-muted-foreground">
                          Shown first when students in this course pick skills. They can still browse all areas.
                        </p>
                        <div class="flex flex-wrap gap-2">
                          <button
                            v-for="category in skillCategoryOptions"
                            :key="`${course}-skill-${category}`"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"
                            :class="
                              (courseSkillCategoryDrafts[course] || []).includes(category)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-muted text-muted-foreground'
                            "
                            @click="toggleCourseSkillCategoryDraft(course, category)"
                          >
                            <Check
                              v-if="(courseSkillCategoryDrafts[course] || []).includes(category)"
                              class="h-3 w-3"
                            />
                            {{ category }}
                          </button>
                        </div>
                      </div>
                    </article>
                  </div>
                </div>


                <div v-if="canManageOrgOjtHours" class="space-y-4 rounded-2xl border border-border bg-muted p-5">
                  <div class="space-y-1">
                    <h4 class="text-base font-semibold text-foreground">Default required hours</h4>
                    <p class="text-sm text-muted-foreground">
                      Used when a course has no hours set. A confirmed hours agreement overrides this.
                    </p>
                  </div>
                  <FormItem>
                    <FormLabel for="orgRequiredHours">Default required hours</FormLabel>
                    <FormControl>
                      <div class="flex max-w-xs items-center gap-2">
                        <Input
                          id="orgRequiredHours"
                          v-model="orgRequiredHours"
                          type="number"
                          min="1"
                          max="10000"
                          placeholder="e.g. 500"
                        />
                        <span class="shrink-0 text-sm text-muted-foreground">hours</span>
                      </div>
                    </FormControl>
                  </FormItem>
                  <Button type="button" :disabled="savingOrgOjt" @click="saveOrgOjtHours">
                    {{ savingOrgOjt ? 'Saving...' : 'Save default hours' }}
                  </Button>
                </div>
          </div>

          <div v-else-if="activeTab === 'public'" class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-2xl font-semibold text-foreground">Public Page</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                  Content students and partners see on your public organization page.
                </p>
              </div>
              <RouterLink
                v-if="activeOrganizationId"
                :to="{ name: 'organization-public', params: { id: activeOrganizationId } }"
                class="text-sm font-medium text-primary underline-offset-4 hover:underline"
              >
                See how students see it
              </RouterLink>
            </div>

            <OrganizationPublicProfileSection
              ref="publicProfileSectionRef"
              @open-account-photo="openAccountPhotoSection"
              @dirty-change="onPublicProfileDirty"
            />
          </div>

          <div v-else-if="activeTab === 'notifications'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-foreground">Notifications</h3>
              <p class="mt-2 text-sm text-muted-foreground">Choose which updates you want to receive for your role.</p>
            </div>

            <div class="space-y-4">
              <Card v-for="option in notificationOptions" :key="option.key" class="border-border/80 shadow-none">
                <CardContent class="flex items-start justify-between gap-4 p-5">
                  <div class="space-y-1">
                    <p class="font-medium text-foreground">{{ option.label }}</p>
                    <p class="text-sm text-muted-foreground">{{ option.description }}</p>
                  </div>
                  <Switch v-model="notificationPreferences[option.key]" />
                </CardContent>
              </Card>
            </div>

            <p class="text-sm text-muted-foreground">Preferences are saved to your account.</p>

            <Button :disabled="savingNotifications || !notificationsDirty" @click="saveNotifications">
              {{ savingNotifications ? 'Saving...' : 'Save Preferences' }}
            </Button>
          </div>

          <div v-else-if="activeTab === 'system'" class="space-y-6">
            <div>
              <h3 class="text-2xl font-semibold text-foreground">System Settings</h3>
              <p class="mt-2 text-sm text-muted-foreground">Control platform-wide defaults and registration behavior.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
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

            <div class="space-y-4 rounded-2xl border border-border bg-muted p-5">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="font-medium text-foreground">Allow Registrations</p>
                  <p class="text-sm text-muted-foreground">Toggle whether new users can create accounts.</p>
                </div>
                <Switch v-model="systemSettings.allowRegistrations" />
              </div>

              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="font-medium text-foreground">Maintenance Mode</p>
                  <p class="text-sm text-muted-foreground">Require confirmation before turning on platform maintenance.</p>
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

    <SkillsWizard
      v-if="currentRole === 'student'"
      v-model="profileSkills"
      v-model:open="skillsWizardOpen"
      mode="intern"
      :limit="15"
      @done="onSkillsSaved"
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

    <div
      v-if="stickySaveVisible"
      class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 px-4 py-3 shadow-lg backdrop-blur"
    >
      <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
          <p class="text-sm font-medium text-foreground">You have unsaved changes</p>
          <span v-if="savedFlash" class="text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ savedFlash }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button type="button" variant="outline" :disabled="savingProfile" @click="discardStickyChanges">
            Discard
          </Button>
          <Button type="button" :disabled="savingProfile" @click="saveStickyChanges">
            {{ savingProfile ? 'Saving...' : 'Save' }}
          </Button>
        </div>
      </div>
    </div>
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
  border: 1px solid hsl(var(--border));
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  padding: 0.5rem 0.75rem;
  box-shadow: none;
}

:deep(.ts-wrapper.focus .ts-control) {
  border-color: hsl(var(--ring));
  box-shadow: 0 0 0 4px hsl(var(--ring) / 0.12);
}

:deep(.ts-control > input) {
  font-size: 0.95rem;
  color: hsl(var(--foreground));
}

:deep(.ts-control .item) {
  border-radius: 9999px;
  background: hsl(var(--accent));
  color: hsl(var(--accent-foreground));
  padding: 0.3rem 0.65rem;
}

:deep(.ts-dropdown) {
  border-radius: 1rem;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--popover));
  color: hsl(var(--popover-foreground));
  box-shadow: 0 18px 40px -24px hsl(var(--foreground) / 0.35);
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
  color: hsl(var(--foreground));
  background: hsl(var(--muted));
  border-bottom: 1px solid hsl(var(--border));
}

:deep(.ts-course-option) {
  font-size: 0.95rem;
  color: hsl(var(--muted-foreground));
}

:deep(.ts-dropdown .active) {
  background: hsl(var(--accent));
  color: hsl(var(--accent-foreground));
}
</style>
