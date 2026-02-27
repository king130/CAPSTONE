import { vi } from 'vitest'

// Mock static assets (images, etc.)
vi.mock('*.png', () => ({
  default: '/mocked-image.png',
}))

vi.mock('*.jpg', () => ({
  default: '/mocked-image.jpg',
}))

vi.mock('*.svg', () => ({
  default: '/mocked-image.svg',
}))

// Mock public folder assets
Object.defineProperty(global, 'import', {
  value: {
    meta: {
      env: {
        BASE_URL: '/',
      },
    },
  },
  writable: true,
})
