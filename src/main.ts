import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './styles/swal.css'
import './styles/Company.css'

console.log('🚀 Starting app initialization...')

// Bootstrap the application
async function bootstrap() {
  const app = createApp(App)
  const pinia = createPinia()
  
  // Install Pinia first
  app.use(pinia)
  
  // Initialize auth (non-blocking when disabled)
  console.log('🔐 Initializing auth...')
  const authStore = useAuthStore()
  const { AUTH_DISABLED } = await import('./config/auth')
  if (AUTH_DISABLED) {
    authStore.initializing = false
    authStore.init().catch(() => {}) // Run in background, don't block
    console.log('⚠️ Auth disabled - mounting app without auth wait')
  } else {
    await authStore.init()
  }
  console.log('✅ Auth initialized, mounting app...')
  
  // Now install router and mount
  app.use(router)
  app.mount('#app')
  console.log('✅ App mounted successfully!')
}

bootstrap()
