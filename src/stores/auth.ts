import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  fetchUserProfile,
  loginUser,
  logoutUser,
  registerUser,
  type UserProfile,
  type RegisterPayload,
} from '@/services/auth'
import { ensurePublicProfile } from '@/services/profilesPublic'
import { getToken, setToken } from '@/services/http'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<UserProfile | null>(null)
  const initializing = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const blockedReason = ref<string | null>(null)

  async function init() {
    if (initializing.value) return

    initializing.value = true
    error.value = null
    blockedReason.value = null

    try {
      if (!getToken()) {
        user.value = null
        return
      }

      const profile = await fetchUserProfile('')
      if (!profile) {
        setToken(null)
        user.value = null
        return
      }

      if (profile.isActive === false) {
        blockedReason.value = 'Account Disabled'
        user.value = null
        await logoutUser()
        return
      }

      user.value = profile
      await maybeSyncPublicProfile(profile)
    } finally {
      initializing.value = false
    }
  }

  async function maybeSyncPublicProfile(profile: UserProfile) {
    if (profile.role === 'school' || profile.role === 'company') {
      const p = profile.profile as Record<string, unknown> | undefined
      const orgName =
        profile.role === 'school'
          ? (p?.institutionName as string | undefined)
          : (p?.companyName as string | undefined)
      const courses = (p?.courses as string[] | undefined) || []
      await ensurePublicProfile(profile.uid, {
        displayName: profile.displayName || 'User',
        role: profile.role,
        orgName: orgName || profile.displayName || profile.email?.split('@')[0],
        email: profile.email,
        courses,
      }).catch(() => {})
    }
  }

  async function register(payload: RegisterPayload) {
    loading.value = true
    error.value = null
    blockedReason.value = null
    try {
      const profile = await registerUser(payload)
      user.value = profile
      await maybeSyncPublicProfile(profile)
      return profile
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Registration failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function login(email: string, password: string) {
    loading.value = true
    error.value = null
    blockedReason.value = null
    try {
      const profile = await loginUser(email, password)

      if (profile?.isActive === false) {
        blockedReason.value = 'Account Disabled'
        await logoutUser()
        user.value = null
        throw new Error('Account Disabled')
      }

      user.value = profile
      await maybeSyncPublicProfile(profile)
      return profile
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Login failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await logoutUser()
    user.value = null
    blockedReason.value = null
  }

  function setUserProfile(profile: UserProfile | null) {
    user.value = profile
  }

  async function refreshUser() {
    if (!getToken()) {
      user.value = null
      return null
    }

    const profile = await fetchUserProfile('')
    user.value = profile
    if (profile) {
      await maybeSyncPublicProfile(profile)
    }
    return profile
  }

  return {
    user,
    loading,
    error,
    blockedReason,
    initializing,
    init,
    register,
    login,
    logout,
    refreshUser,
    setUserProfile,
  }
})
