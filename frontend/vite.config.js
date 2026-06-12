import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// Production target is Azure Static Web Apps at https://whartonai.studio,
// served from the domain root. The old GitHub Pages preview (subpath
// '/waais-website/') was retired; override with VITE_BASE if ever needed.
const base = process.env.VITE_BASE ?? '/'

// https://vite.dev/config/
export default defineConfig({
  base,
  plugins: [vue(), tailwindcss()],
})
