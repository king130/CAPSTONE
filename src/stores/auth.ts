import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  fetchUserProfile,
  loginUser,
  logoutUser,
  registerUser,
  subscribeToAuthState,
  subscribeToUserProfile,
  type UserProfile,
  type RegisterPayload,
} from '@/services/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<UserProfile | null>(null)
  const initializing = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const blockedReason = ref<string | null>(null)
  const unsubscribeProfile = ref<null | (() => void)>(null)

  function clearProfileSubscription() {
    if (unsubscribeProfile.value) {
      unsubscribeProfile.value()
      unsubscribeProfile.value = null
    }
  }

  async function init() {
    if (initializing.value) return
    initializing.value = true
    subscribeToAuthState(async (firebaseUser) => {
      clearProfileSubscription()
      if (!firebaseUser) {
        user.value = null
        initializing.value = false
        return
      }
      unsubscribeProfile.value = subscribeToUserProfile(firebaseUser.uid, async (profile) => {
        if (!profile) {
          user.value = null
          blockedReason.value = null
          initializing.value = false
          return
        }
        if (profile.isActive === false) {
          blockedReason.value = 'Account Disabled'
          user.value = null
          clearProfileSubscription()
          await logoutUser()
          initializing.value = false
          return
        }
        user.value = profile
        blockedReason.value = null
        initializing.value = false
      })
    })
  }

  async function register(payload: RegisterPayload) {
    loading.value = true
    error.value = null
    blockedReason.value = null
    try {
      const profile = await registerUser(payload)
      user.value = profile
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
      const firebaseUser = await loginUser(email, password)
      const profile = await fetchUserProfile(firebaseUser.uid)
      
      if (!profile) {
        await logoutUser()
        throw new Error('User profile not found in database. Firestore rules may not be deployed.')
      }
      
      if (profile?.isActive === false) {
        blockedReason.value = 'Account Disabled'
        await logoutUser()
        throw new Error('Account Disabled')
      }
      user.value = profile
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
    clearProfileSubscription()
  }

  return {
    user,
    loading,
    error,
    blockedReason,
    init,
    register,
    login,
    logout,
  }
})
