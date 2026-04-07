import { createRouter, createWebHistory } from 'vue-router'
import { AUTH_DISABLED } from '@/config/auth'
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
    { path: '/', name: 'landing', component: () => import('@/views/Landing.vue') },
    { path: '/login', name: 'login', component: () => import('@/views/Login.vue') },
    { path: '/register', name: 'register', component: () => import('@/views/RegisterSimple.vue') },
    { path: '/account-setup', name: 'account-setup', component: () => import('@/views/AccountSetup.vue') },
    { path: '/change-password', name: 'change-password', component: () => import('@/views/ChangePassword.vue'), meta: { requiresAuth: true, allowGuest: true } },
    { path: '/find-internships', name: 'find-internships', component: () => import('@/views/FindInternships.vue'), meta: { excludeRoles: ['school'] } },
    { path: '/opportunities', name: 'opportunities', component: () => import('@/views/FindInternships.vue'), meta: { excludeRoles: ['school'] } },
    { path: '/account-disabled', name: 'account-disabled', component: () => import('@/views/AccountDisabled.vue') },
    { path: '/notifications', name: 'notifications', component: () => import('@/views/Notifications.vue'), meta: { requiresAuth: true, excludeRoles: ['guest'] } },
    { path: '/settings', name: 'settings', component: () => import('@/views/Settings.vue'), meta: { requiresAuth: true, excludeRoles: ['guest'] } },
    { path: '/billing', name: 'organization-subscription', component: () => import('@/views/OrganizationSubscription.vue'), meta: { requiresAuth: true, excludeRoles: ['guest', 'student', 'admin'] } },
    { path: '/ojt-hours', name: 'ojt-hours', component: () => import('@/views/OJTHours.vue'), meta: { requiresAuth: true, excludeRoles: ['guest'] } },
    { path: '/contracts', name: 'contracts', component: () => import('@/views/Contracts.vue'), meta: { requiresAuth: true, excludeRoles: ['guest', 'student', 'admin'] } },
    { path: '/contracts/types', name: 'contract-types-manage', component: () => import('@/views/ManageContractTypes.vue'), meta: { requiresAuth: true, excludeRoles: ['guest', 'student', 'admin'] } },
    { path: '/contracts/new', name: 'contracts-new', component: () => import('@/views/NewContractRequest.vue'), meta: { requiresAuth: true, excludeRoles: ['guest', 'student', 'admin'] } },
    { path: '/school/students', name: 'school-students', component: () => import('@/views/StudentAccounts.vue'), meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/access/roles', name: 'tenant-role-management', component: () => import('@/views/TenantRbac.vue'), props: { mode: 'roles' }, meta: { requiresAuth: true, excludeRoles: ['guest', 'student'] } },
    { path: '/access/permissions', name: 'tenant-permission-assignment', component: () => import('@/views/TenantRbac.vue'), props: { mode: 'permissions' }, meta: { requiresAuth: true, excludeRoles: ['guest', 'student'] } },
    
    // Guest routes (logged in but no role)
    { path: '/guest', name: 'guest', component: () => import('@/views/Guest.vue'), meta: { requiresAuth: true, allowGuest: true } },
    { path: '/subscription/:role?', name: 'subscription', component: () => import('@/views/Subscription.vue'), meta: { requiresAuth: true, allowGuest: true } },
    { path: '/role-selection', name: 'role-selection', component: () => import('@/views/RoleSelection.vue'), meta: { requiresAuth: true, allowGuest: true } },
    { path: '/profile', name: 'profile', component: () => import('@/views/Profile.vue'), meta: { requiresAuth: true, allowGuest: true } },
    
    // Role-specific dashboards
    { path: '/dashboard', name: 'dashboard', component: () => import('@/views/company/Company.vue'), meta: { requiresAuth: true, requiresRole: 'company' } },
    { path: '/dashboard/jobs', name: 'company-internships', component: () => import('@/views/company/Company.vue'), meta: { requiresAuth: true, requiresRole: 'company' } },
    { path: '/dashboard/applicants', name: 'company-applicants', component: () => import('@/views/company/Company.vue'), meta: { requiresAuth: true, requiresRole: 'company' } },
    { path: '/register/company', name: 'register-company', component: () => import('@/views/RegisterSimple.vue') },
    { path: '/register/school', name: 'register-school', component: () => import('@/views/RegisterSimple.vue') },
    { path: '/school', name: 'school', component: () => import('@/views/school/School.vue'), meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/school/interns', name: 'school-interns', component: () => import('@/views/school/School.vue'), meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/school/opportunities', name: 'school-opportunities', component: () => import('@/views/school/School.vue'), meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/school/endorsements', name: 'school-endorsements', component: () => import('@/views/school/School.vue'), meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/school/placements', name: 'school-placements', component: () => import('@/views/school/School.vue'), meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/school/reports', name: 'school-reports', component: () => import('@/views/school/School.vue'), meta: { requiresAuth: true, requiresRole: 'school' } },
    { path: '/intern', name: 'intern', component: () => import('@/views/intern/Intern.vue'), meta: { requiresAuth: true, requiresRole: 'student' } },
    { path: '/intern/opportunities', name: 'intern-opportunities', component: () => import('@/views/intern/Intern.vue'), meta: { requiresAuth: true, requiresRole: 'student' } },
    { path: '/intern/documents', name: 'intern-documents', component: () => import('@/views/intern/Intern.vue'), meta: { requiresAuth: true, requiresRole: 'student' } },
    { path: '/intern/applications', name: 'intern-applications', component: () => import('@/views/intern/Intern.vue'), meta: { requiresAuth: true, requiresRole: 'student' } },
    { path: '/intern/placement', name: 'intern-placement', component: () => import('@/views/intern/Intern.vue'), meta: { requiresAuth: true, requiresRole: 'student' } },
    { path: '/intern/tracker', name: 'intern-tracker', component: () => import('@/views/intern/Intern.vue'), meta: { requiresAuth: true, requiresRole: 'student' } },
    
    // Admin routes (use main /login - admin redirects to /admin/overview)
    { path: '/admin/login', redirect: '/login' },
    {
      path: '/admin',
      component: () => import('@/views/admin/AdminDashboard.vue'),
      meta: { requiresAuth: true, requiresRole: 'admin' },
      children: [
        { path: '', redirect: '/admin/overview' },
        {
          path: 'overview',
          name: 'admin-overview',
          component: () => import('@/views/admin/SystemOverview.vue'),
          meta: { requiresAuth: true, requiresRole: 'admin', layoutTitle: 'Dashboard', navKey: 'admin-overview' },
        },
        {
          path: 'interns',
          alias: 'users',
          name: 'admin-manage-interns',
          component: () => import('@/views/admin/UserManagement.vue'),
          meta: { requiresAuth: true, requiresRole: 'admin', layoutTitle: 'Manage Interns', navKey: 'admin-manage-interns' },
        },
        {
          path: 'schools',
          name: 'admin-manage-schools',
          component: () => import('@/views/admin/UserManagement.vue'),
          meta: { requiresAuth: true, requiresRole: 'admin', layoutTitle: 'Manage Schools', navKey: 'admin-manage-schools' },
        },
        {
          path: 'companies',
          name: 'admin-manage-companies',
          component: () => import('@/views/admin/UserManagement.vue'),
          meta: { requiresAuth: true, requiresRole: 'admin', layoutTitle: 'Manage Companies', navKey: 'admin-manage-companies' },
        },
        {
          path: 'reports',
          alias: 'temporary',
          name: 'admin-reports',
          component: () => import('@/views/admin/TemporaryAccounts.vue'),
          meta: { requiresAuth: true, requiresRole: 'admin', layoutTitle: 'Reports', navKey: 'admin-reports' },
        },
        {
          path: 'subscriptions',
          name: 'admin-subscriptions',
          component: () => import('@/views/admin/SubscriptionPricing.vue'),
          meta: { requiresAuth: true, requiresRole: 'admin', layoutTitle: 'Subscription Plans', navKey: 'admin-subscriptions' },
        },
        {
          path: 'settings',
          alias: 'pricing',
          name: 'admin-settings',
          component: () => import('@/views/admin/AdminSettings.vue'),
          meta: { requiresAuth: true, requiresRole: 'admin', layoutTitle: 'Settings', navKey: 'admin-settings' },
        },
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
