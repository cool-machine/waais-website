import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// Production target is Azure Static Web Apps at https://whartonai.studio,
// served from the domain root. The old GitHub Pages preview (subpath
// '/waais-website/') was retired; override with VITE_BASE if ever needed.
const base = process.env.VITE_BASE ?? '/'

// Dev-only: the Laravel API runs on 127.0.0.1:8000 and browsers refuse to
// share cookies between `localhost` and `127.0.0.1` (different sites), which
// breaks Sanctum sessions and CSRF. Redirect any localhost request to the
// loopback IP so dev always runs on a cookie-compatible origin.
const redirectLocalhostToLoopback = {
  name: 'redirect-localhost-to-loopback',
  apply: 'serve',
  configureServer(server) {
    server.middlewares.use((req, res, next) => {
      const host = req.headers.host ?? ''
      if (host === 'localhost' || host.startsWith('localhost:')) {
        const port = host.split(':')[1] ?? '5173'
        res.statusCode = 302
        res.setHeader('Location', `http://127.0.0.1:${port}${req.url}`)
        res.end()
        return
      }
      next()
    })
  },
}

// https://vite.dev/config/
export default defineConfig({
  base,
  // Bind the dev server to the IPv4 loopback so the app and the Laravel
  // API (127.0.0.1:8000) are same-site for cookies. Browsers hitting
  // `localhost` still connect (and are then redirected by the plugin above).
  server: {
    host: '127.0.0.1',
    port: 5173,
  },
  plugins: [vue(), tailwindcss(), redirectLocalhostToLoopback],
})
