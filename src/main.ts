import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { toast } from 'vue-sonner'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './assets/globals.css'
import 'leaflet/dist/leaflet.css'
import 'vue-sonner/style.css'
import 'sweetalert2/dist/sweetalert2.min.css'
import './styles/swal.css'
import './styles/Company.css'

if (import.meta.env.DEV) {
  console.log('Starting app initialization...')
}

// Bootstrap the application
async function bootstrap() {
  const app = createApp(App)
  const pinia = createPinia()

  app.config.errorHandler = (err, _instance, info) => {
    if (import.meta.env.DEV) {
      console.error('[Vue error]', err, info)
    }
    toast.error('Something went wrong. Please try again.', {
      description: import.meta.env.DEV && err instanceof Error ? err.message : undefined,
    })
  }

  // Install Pinia first
  app.use(pinia)

  // Initialize auth (non-blocking when disabled)
  if (import.meta.env.DEV) {
    console.log('Initializing auth...')
  }
  const authStore = useAuthStore()
  const { AUTH_DISABLED } = await import('./config/auth')
  if (AUTH_DISABLED) {
    authStore.initializing = false
    authStore.init().catch(() => {}) // Run in background, don't block
    if (import.meta.env.DEV) {
      console.log('Auth disabled - mounting app without auth wait')
    }
  } else {
    await authStore.init()
  }
  if (import.meta.env.DEV) {
    console.log('Auth initialized, mounting app...')
  }

  // Now install router and mount
  app.use(router)
  app.mount('#app')
  if (import.meta.env.DEV) {
    console.log('App mounted successfully!')
  }
}

bootstrap()
