import { createRouter, createWebHistory } from 'vue-router'
import Landing from '@/views/Landing.vue'
import Login from '@/views/Login.vue'
import Register from '@/views/Register.vue'
import FindInternships from '@/views/FindInternships.vue'
import Dashboard from '@/views/company/Company.vue'
import Intern from '@/views/intern/Intern.vue'
import AccountDisabled from '@/views/AccountDisabled.vue'
import AdminLogin from '@/views/admin/AdminLogin.vue'
import AdminDashboard from '@/views/admin/AdminDashboard.vue'
import UserManagement from '@/views/admin/UserManagement.vue'
import TemporaryAccounts from '@/views/admin/TemporaryAccounts.vue'
import SystemOverview from '@/views/admin/SystemOverview.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'landing', component: Landing },
    { path: '/login', name: 'login', component: Login },
    { path: '/register', name: 'register', component: Register },
    { path: '/find-internships', name: 'find-internships', component: FindInternships },
    { path: '/dashboard', name: 'dashboard', component: Dashboard },
    { path: '/intern', name: 'intern', component: Intern },
    { path: '/account-disabled', name: 'account-disabled', component: AccountDisabled },
    { path: '/admin/login', name: 'admin-login', component: AdminLogin },
    {
      path: '/admin',
      component: AdminDashboard,
      meta: { requiresAdmin: true },
      children: [
        { path: '', redirect: '/admin/overview' },
        { path: 'overview', name: 'admin-overview', component: SystemOverview, meta: { requiresAdmin: true } },
        { path: 'users', name: 'admin-users', component: UserManagement, meta: { requiresAdmin: true } },
        { path: 'temporary', name: 'admin-temporary', component: TemporaryAccounts, meta: { requiresAdmin: true } },
      ],
    },
  ],
})

router.beforeEach((to) => {
  const authStore = useAuthStore()

  if (authStore.blockedReason && to.name !== 'account-disabled') {
    return { name: 'account-disabled' }
  }

  if (to.meta.requiresAdmin && authStore.user?.role !== 'admin') {
    return { name: 'admin-login' }
  }

  return true
})

export default router
