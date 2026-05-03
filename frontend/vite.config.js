import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// `base` differs by deployment target:
// - GitHub Pages preview at https://cool-machine.github.io/waais-website/ → '/waais-website/'
// - Azure Static Web Apps production at https://whartonai.studio → '/'
// CI passes VITE_BASE=/ for the SWA build; default stays '/waais-website/' so
// `npm run build` continues to produce the GH Pages preview artifact.
const base = process.env.VITE_BASE ?? '/waais-website/'

// https://vite.dev/config/
export default defineConfig({
  base,
  plugins: [vue(), tailwindcss()],
})
