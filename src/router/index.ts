import { createRouter, createWebHistory } from 'vue-router'
import Landing from '@/views/Landing.vue'
import Login from '@/views/Login.vue'
import Register from '@/views/Register.vue'
import FindInternships from '@/views/FindInternships.vue'
import Dashboard from '@/views/company/Company.vue'
import Intern from '@/views/intern/Intern.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'landing', component: Landing },
    { path: '/login', name: 'login', component: Login },
    { path: '/register', name: 'register', component: Register },
    { path: '/find-internships', name: 'find-internships', component: FindInternships },
    { path: '/dashboard', name: 'dashboard', component: Dashboard },
    { path: '/intern', name: 'intern', component: Intern },
  ],
})

export default router
