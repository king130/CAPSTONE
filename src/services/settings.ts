import {
  fetchUserProfile,
  updateCurrentUserPassword,
  updateCurrentUserProfile,
  type UserProfile,
} from '@/services/auth'

export interface ProfileSettings {
  fullName: string
  phone: string
  acceptedCourses?: string
  officialSchoolEmail?: string
  [key: string]: string | undefined
}

export interface PasswordForm {
  currentPassword: string
  newPassword: string
  confirmPassword: string
}

export interface NotificationPreferences {
  [notificationType: string]: boolean
}

export interface AdminSystemSettings {
  requiredHours: string
  schoolYear: string
  semester: string
  allowRegistrations: boolean
  maintenanceMode: boolean
}

export interface SettingsPayload {
  profile: ProfileSettings
  notificationPreferences: NotificationPreferences
  systemSettings?: AdminSystemSettings
}

const NOTIFICATION_PREFERENCES_KEY = 'capstone.notification-preferences'
const ADMIN_SYSTEM_SETTINGS_KEY = 'capstone.admin-system-settings'

export const supportsAccountDeactivation = false

function readStorageValue<T>(key: string, fallback: T): T {
  if (typeof window === 'undefined') return fallback

  const raw = window.localStorage.getItem(key)
  if (!raw) return fallback

  try {
    return JSON.parse(raw) as T
  } catch {
    return fallback
  }
}

function writeStorageValue<T>(key: string, value: T) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(key, JSON.stringify(value))
}

function mapUserToProfileSettings(user: UserProfile): ProfileSettings {
  const profile = (user.profile as Record<string, unknown> | undefined) ?? {}

  return {
    fullName: user.displayName ?? '',
    phone: String(profile.contactNumber ?? profile.companyContactNumber ?? profile.schoolContactNumber ?? ''),
    course: String(profile.course ?? ''),
    yearLevel: String(profile.yearLevel ?? ''),
    schoolName: String(profile.schoolName ?? profile.institutionName ?? ''),
    schoolAddress: String(profile.schoolAddress ?? ''),
    contactPerson: String(profile.contactPersonName ?? ''),
    schoolType: String(profile.institutionType ?? ''),
    companyName: String(profile.companyName ?? ''),
    industry: String(profile.industryType ?? ''),
    address: String(profile.companyAddress ?? profile.schoolAddress ?? ''),
    addressCity: String(profile.cityMunicipality ?? ''),
    addressBarangay: String(profile.barangay ?? ''),
    addressZipCode: String(profile.zipCode ?? ''),
    acceptedCourses: JSON.stringify(Array.isArray(profile.courses) ? profile.courses : []),
    officialSchoolEmail: String(profile.officialSchoolEmail ?? ''),
    website: String(profile.website ?? ''),
    department: String(profile.department ?? ''),
    position: String(profile.position ?? ''),
  }
}

function defaultSystemSettings(): AdminSystemSettings {
  return {
    requiredHours: '500',
    schoolYear: '',
    semester: '',
    allowRegistrations: true,
    maintenanceMode: false,
  }
}

function notificationPreferenceDefaults(): NotificationPreferences {
  return {
    application: true,
    endorsement: true,
    hours: true,
    system: true,
    registration: true,
  }
}

function mapProfileSettingsToPayload(profile: ProfileSettings): {
  displayName: string
  profile: Record<string, unknown>
  profileSetupComplete: boolean
} {
  const normalizedSchoolName = profile.schoolName || profile.fullName

  return {
    displayName: profile.fullName,
    profileSetupComplete: true,
    profile: {
      contactNumber: profile.phone,
      course: profile.course || undefined,
      yearLevel: profile.yearLevel || undefined,
      schoolName: profile.schoolName || undefined,
      institutionName: normalizedSchoolName || undefined,
      schoolAddress: profile.schoolAddress || undefined,
      contactPersonName: profile.contactPerson || undefined,
      institutionType: profile.schoolType || undefined,
      companyName: profile.companyName || undefined,
      industryType: profile.industry || undefined,
      companyAddress: profile.address || undefined,
      province: profile.addressCity ? 'Cavite' : undefined,
      cityMunicipality: profile.addressCity || undefined,
      barangay: profile.addressBarangay || undefined,
      zipCode: profile.addressZipCode || undefined,
      courses: (() => {
        try {
          const parsed = JSON.parse(profile.acceptedCourses || '[]')
          return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string' && item.trim().length > 0) : undefined
        } catch {
          return undefined
        }
      })(),
      officialSchoolEmail: profile.officialSchoolEmail || undefined,
      website: profile.website || undefined,
      department: profile.department || undefined,
      position: profile.position || undefined,
      schoolContactNumber: profile.phone || undefined,
      companyContactNumber: profile.phone || undefined,
    },
  }
}

export async function fetchSettings(): Promise<SettingsPayload> {
  const user = await fetchUserProfile('')
  if (!user) {
    throw new Error('Unable to load user settings.')
  }

  return {
    profile: mapUserToProfileSettings(user),
    notificationPreferences: {
      ...notificationPreferenceDefaults(),
      ...readStorageValue<NotificationPreferences>(NOTIFICATION_PREFERENCES_KEY, {}),
    },
    systemSettings: readStorageValue<AdminSystemSettings>(
      ADMIN_SYSTEM_SETTINGS_KEY,
      defaultSystemSettings(),
    ),
  }
}

export async function saveProfileSettings(profile: ProfileSettings): Promise<void> {
  await updateCurrentUserProfile(mapProfileSettingsToPayload(profile))
}

export async function savePasswordSettings(passwordForm: PasswordForm): Promise<void> {
  await updateCurrentUserPassword(passwordForm.newPassword)
}

export async function saveNotificationPreferences(preferences: NotificationPreferences): Promise<void> {
  writeStorageValue(NOTIFICATION_PREFERENCES_KEY, preferences)
}

export async function saveAdminSystemSettings(settings: AdminSystemSettings): Promise<void> {
  writeStorageValue(ADMIN_SYSTEM_SETTINGS_KEY, settings)
}

export async function deactivateCurrentAccount(): Promise<void> {
  throw new Error('Account deactivation is not available in this build.')
}
