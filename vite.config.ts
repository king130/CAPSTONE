import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  server: {
    watch: {
      // Avoid transient EBUSY on Windows when files are momentarily locked during save.
      usePolling: true,
      interval: 120,
      awaitWriteFinish: {
        stabilityThreshold: 300,
        pollInterval: 80,
      },
    },
  },
  resolve: {
    alias: [
      { find: '@', replacement: fileURLToPath(new URL('./src', import.meta.url)) },
      { find: /^sweetalert2$/, replacement: fileURLToPath(new URL('./src/services/sweetalert2.ts', import.meta.url)) },
    ],
  },
  test: {
    globals: true,
    environment: 'happy-dom',
    css: true,
    setupFiles: ['./src/test-setup.ts'],
    server: {
      deps: {
        inline: ['vue-router'],
      },
    },
  },
})
