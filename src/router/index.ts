import { createRouter, createWebHistory } from 'vue-router'
import { AUTH_DISABLED } from '@/config/auth'
import Landing from '@/views/Landing.vue'
import Login from '@/views/Login.vue'
import RegisterSimple from '@/views/RegisterSimple.vue'
import ChangePassword from '@/views/ChangePassword.vue'
import RoleSelection from '@/views/RoleSelection.vue'
import Profile from '@/views/Profile.vue'
import FindInternships from '@/views/FindInternships.vue'
import Dashboard from '@/views/company/Company.vue'
import School from '@/views/school/School.vue'
import Intern from '@/views/intern/Intern.vue'
import AccountDisabled from '@/views/AccountDisabled.vue'
import Guest from '@/views/Guest.vue'
import Subscription from '@/views/Subscription.vue'
import AdminDashboard from '@/views/admin/AdminDashboard.vue'
import UserManagement from '@/views/admin/UserManagement.vue'
import TemporaryAccounts from '@/views/admin/TemporaryAccounts.vue'
import SystemOverview from '@/views/admin/SystemOverview.vue'
import SubscriptionPricing from '@/views/admin/SubscriptionPricing.vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/services/auth'

/**
 * Get dashboard route based on user role
 */
function getRoleDashboard(role: UserRole): string {
  switch (role) {
    case 'admin':
      return '/admin/overview'
    case 'company':
      return '/dashboard'
    case 'school':
      return '/school'
    case 'student':
      return '/intern'
    case 'guest':
    case null:
      return '/guest'
    default:
      return '/guest'
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }

    if (to.hash) {
      return {
        el: to.hash,
        behavior: 'smooth',
      }
    }

    return { top: 0 }
  },
  routes: [
    // Public routes
    { path: '/', name: 'landing', component: Landing },
    { path: '/login', name: 'login', component: Login },
    { path: '/register', name: 'register', component: RegisterSimple },
    { path: '/change-password', name: 'change-password', component: ChangePassword, meta: { requiresAuth: true, allowGuest: true } },
    { path: '/find-internships', name: 'find-internships', component: FindInternships, meta: { excludeRoles: ['school'] } },
    { path: '/account-disabled', name: 'account-disabled', component: AccountDisabled },
    
    // Guest routes (logged in but no role)
    { path: '/guest', name: 'guest', component: Guest, meta: { requiresAuth: true, allowGuest: true } },
    { path: '/subscription/:role?', name: 'subscription', component: Subscription, meta: { requiresAuth: true, allowGuest: true } },
    { path: '/role-selection', name: 'role-selection', component: RoleSelection, meta: { requiresAuth: true, allowGuest: true } },
    { path: '/profile', name: 'profile', component: Profile, meta: { requiresAuth: true, allowGuest: true } },
    
    // Role-specific dashboards
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true, requiresRole: 'company' } },
    { path: '/school', name: 'school', component: School, meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/intern', name: 'intern', component: Intern, meta: { requiresAuth: true, requiresRole: 'student' } },
    
    // Admin routes (use main /login - admin redirects to /admin/overview)
    { path: '/admin/login', redirect: '/login' },
    {
      path: '/admin',
      component: AdminDashboard,
      meta: { requiresAuth: true, requiresRole: 'admin' },
      children: [
        { path: '', redirect: '/admin/overview' },
        { path: 'overview', name: 'admin-overview', component: SystemOverview, meta: { requiresAuth: true, requiresRole: 'admin' } },
        { path: 'users', name: 'admin-users', component: UserManagement, meta: { requiresAuth: true, requiresRole: 'admin' } },
        { path: 'temporary', name: 'admin-temporary', component: TemporaryAccounts, meta: { requiresAuth: true, requiresRole: 'admin' } },
        { path: 'pricing', name: 'admin-pricing', component: SubscriptionPricing, meta: { requiresAuth: true, requiresRole: 'admin' } },
      ],
    },
  ],
})

router.beforeEach((to) => {
  // Auth disabled: allow all routes without checks
  if (AUTH_DISABLED) {
    return true
  }

  const authStore = useAuthStore()
  
  // CRITICAL: Block routing if auth is still initializing
  if (authStore.initializing) {
    console.warn('⚠️ Blocking navigation - auth still initializing')
    return false
  }
  
  const isAuthenticated = !!authStore.user
  const userRole = authStore.user?.role
  const mustChangePassword = authStore.user?.mustChangePassword === true
  console.log('🛡️ Router guard:', to.path, '| User:', authStore.user?.email || 'null', '| Role:', userRole || 'null')

  // Check for blocked/disabled accounts
  if (authStore.blockedReason && to.name !== 'account-disabled') {
    return { name: 'account-disabled' }
  }

  // AUTHENTICATED USERS
  if (isAuthenticated) {
    if (mustChangePassword && to.name !== 'change-password') {
      return { name: 'change-password' }
    }

    if (!mustChangePassword && to.name === 'change-password') {
      if (!userRole || userRole === 'guest' || userRole === null) {
        return { path: '/find-internships' }
      }
      return { path: getRoleDashboard(userRole) }
    }

    // Redirect authenticated users away from login/register
    if (to.name === 'login' || to.name === 'register') {
      if (!userRole || userRole === 'guest' || userRole === null) {
        return { path: '/guest' }
      }
      return { path: getRoleDashboard(userRole) }
    }

    // Redirect from landing page
    if (to.name === 'landing') {
      if (!userRole || userRole === 'guest' || userRole === null) {
        return { path: '/guest' }
      }
      return { path: getRoleDashboard(userRole) }
    }

    // Check if route excludes certain roles
    if (to.meta.excludeRoles && userRole) {
      const excludedRoles = to.meta.excludeRoles as string[]
      if (excludedRoles.includes(userRole)) {
        console.log(`🚫 Access denied: ${userRole} is excluded from this route`)
        return { path: getRoleDashboard(userRole) }
      }
    }

    // Check if route requires specific role
    if (to.meta.requiresRole) {
      const requiredRole = to.meta.requiresRole as string
      
      // If user has no role, redirect to guest page
      if (!userRole || userRole === 'guest' || userRole === null) {
        return { path: '/guest' }
      }
      
      // If user has wrong role, redirect to their dashboard
      if (userRole !== requiredRole) {
        console.log(`🚫 Access denied: ${requiredRole} required, user is ${userRole}`)
        return { path: getRoleDashboard(userRole) }
      }
    }

    // Allow access to routes that permit guests
    if (to.meta.allowGuest) {
      return true
    }
  }

  // UNAUTHENTICATED USERS
  if (!isAuthenticated) {
    // Redirect to login if route requires auth
    if (to.meta.requiresAuth) {
      return { name: 'login' }
    }
  }

  return true
})

export default router
