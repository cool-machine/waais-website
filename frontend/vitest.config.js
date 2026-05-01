import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Vitest config kept separate from vite.config.js so the production
// build (which sets `base: '/waais-website/'` for GitHub Pages) is
// unaffected by test concerns. Tests run against a jsdom environment.
export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['src/**/*.test.{js,ts}'],
  },
})
