// Post-build prerender: render each public route in a headless browser and
// save a real-HTML snapshot to dist/<route>/index.html. This gives crawlers
// (and link-preview bots) the page content without having to run JavaScript.
//
// The live app keeps loading data from https://api.whartonai.studio. That API
// only allows CORS from the production frontend origin, so during prerender we
// intercept the page's API calls and satisfy them server-side (no CORS in
// Node), with permissive headers — the saved HTML ends up with the data baked
// in, while the shipped JS bundle is unchanged (still points at the real API).
//
// Non-fatal by design: any failure exits 0 so the deploy still ships the SPA.

import { createServer } from 'node:http'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'
import { mkdirSync, writeFileSync, existsSync } from 'node:fs'
import sirv from 'sirv'
import puppeteer from 'puppeteer'

const __dirname = dirname(fileURLToPath(import.meta.url))
const dist = join(__dirname, '..', 'dist')
const API_ORIGIN = (process.env.VITE_API_BASE_URL || 'https://api.whartonai.studio').replace(/\/+$/, '')
const PORT = 4317

// Public, crawlable routes. Member-only (/app/*) and dynamic detail pages are
// intentionally left to client-side rendering.
const ROUTES = [
  '/', '/about', '/advisors', '/events', '/startups',
  '/partners', '/news', '/contact', '/membership', '/sign-in', '/legal', '/forum',
]

const CORS = {
  'access-control-allow-origin': '*',
  'access-control-allow-methods': '*',
  'access-control-allow-headers': '*',
}

const ORIGIN = 'https://whartonai.studio'
const SITE = 'Wharton Alumni AI Studio'
const OG_IMAGE = `${ORIGIN}/og-icon-512.png`

// Per-route <head> data baked into each prerendered page. The app itself does
// no runtime head changes (that destabilizes the prerender mount), so the title
// + description + Open Graph are written here, after render. `title` is the
// page-specific part; the site name is appended (empty title => site name only).
const META = {
  '/': { title: '', description: 'The Wharton Alumni AI Studio (WAAIS) connects Wharton alumni, entrepreneurs, investors, and researchers building real-world AI — an affinity group of the Wharton Club of the United Kingdom.' },
  '/about': { title: 'About', description: 'How the Wharton Alumni AI Studio began, what we do, and the founders behind it — George Gvishiani and Ines de Bagration de Ulloa.' },
  '/advisors': { title: 'Board of Advisors', description: 'The board advisors guiding the Wharton Alumni AI Studio.' },
  '/events': { title: 'Events', description: 'AI events, salons, workshops, and roundtables for the Wharton Alumni AI Studio community.' },
  '/startups': { title: 'Startups', description: 'AI companies founded and built by Wharton alumni.' },
  '/partners': { title: 'Partners', description: 'Organisations partnering with the Wharton Alumni AI Studio.' },
  '/news': { title: 'AI News', description: 'AI and analytics news from Penn & Wharton, curated by the Wharton Alumni AI Studio.' },
  '/contact': { title: 'Contact', description: 'Reach the Wharton Alumni AI Studio team about membership, events, and partnerships.' },
  '/membership': { title: 'Become a Member', description: 'Join the Wharton Alumni AI Studio — create your account and apply for membership.' },
  '/sign-in': { title: 'Member Sign In', description: 'Sign in to your Wharton Alumni AI Studio member account.' },
  '/legal': { title: 'Privacy & Legal', description: 'Privacy, cookies, and data requests for the Wharton Alumni AI Studio.' },
  '/forum': { title: 'Forum', description: 'The Wharton Alumni AI Studio member forum.' },
}

function bail(msg, err) {
  console.error('[prerender] ' + msg, err?.message ?? '')
  process.exit(0) // never block the deploy
}

if (!existsSync(join(dist, 'index.html'))) bail('dist/index.html missing — run `vite build` first.')

const serve = sirv(dist, { single: true, dev: false })
const server = createServer((req, res) => serve(req, res, () => { res.statusCode = 404; res.end('not found') }))
await new Promise((resolve) => server.listen(PORT, '127.0.0.1', resolve))

let browser
try {
  browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox', '--disable-setuid-sandbox'] })
} catch (err) {
  server.close()
  bail('headless browser failed to launch; shipping the SPA as-is.', err)
}

let ok = 0
for (const route of ROUTES) {
  const m = META[route] || META['/']
  const pageTitle = m.title ? `${m.title} — ${SITE}` : SITE
  const pageDesc = m.description
  const pageUrl = route === '/' ? `${ORIGIN}/` : `${ORIGIN}${route}`
  let html = null

  // The SPA's initial mount under prerender is timing-sensitive, so retry a
  // few times and only keep a snapshot that actually rendered content. If a
  // route never renders, we write nothing and the normal SPA fallback serves
  // it — so this can never regress a page to a blank shell.
  for (let attempt = 1; attempt <= 3 && !html; attempt++) {
    const page = await browser.newPage()
    page.on('pageerror', () => {})
    await page.setRequestInterception(true)
    page.on('request', async (req) => {
      const url = req.url()
      if (url.startsWith(API_ORIGIN)) {
        // Echo the page origin + allow credentials so even the app's
        // credentialed /api/user call resolves cleanly (a 401) rather than a
        // hard CORS failure, which can intermittently break the route mount.
        const origin = req.headers().origin || `http://127.0.0.1:${PORT}`
        const cors = {
          'access-control-allow-origin': origin,
          'access-control-allow-credentials': 'true',
          'access-control-allow-methods': '*',
          'access-control-allow-headers': '*',
        }
        if (req.method() === 'OPTIONS') {
          return req.respond({ status: 204, headers: cors, body: '' }).catch(() => {})
        }
        try {
          const upstream = await fetch(url, { method: req.method(), headers: { Accept: 'application/json' } })
          const body = Buffer.from(await upstream.arrayBuffer())
          return req.respond({
            status: upstream.status,
            headers: { ...cors, 'content-type': upstream.headers.get('content-type') || 'application/json' },
            body,
          }).catch(() => {})
        } catch {
          return req.respond({ status: 502, headers: cors, body: '{"data":[]}' }).catch(() => {})
        }
      }
      return req.continue().catch(() => {})
    })

    try {
      await page.goto(`http://127.0.0.1:${PORT}${route}`, { waitUntil: 'networkidle0', timeout: 45000 })
      await new Promise((r) => setTimeout(r, 2500)) // let the route + its data render
      const appLen = await page.evaluate(() => (document.getElementById('app')?.innerHTML || '').length)
      if (appLen <= 1000) continue // didn't render this attempt — retry

      // Write the per-route <head> AFTER render (doing it during mount breaks
      // the route component under prerender).
      await page.evaluate(({ pageTitle, pageDesc, pageUrl, image, site }) => {
        document.title = pageTitle
        const upMeta = (attr, key, val) => {
          let el = document.head.querySelector(`meta[${attr}="${key}"]`)
          if (!el) { el = document.createElement('meta'); el.setAttribute(attr, key); document.head.appendChild(el) }
          el.setAttribute('content', val)
        }
        let canonical = document.head.querySelector('link[rel="canonical"]')
        if (!canonical) { canonical = document.createElement('link'); canonical.setAttribute('rel', 'canonical'); document.head.appendChild(canonical) }
        canonical.setAttribute('href', pageUrl)
        upMeta('name', 'description', pageDesc)
        upMeta('property', 'og:title', pageTitle)
        upMeta('property', 'og:description', pageDesc)
        upMeta('property', 'og:url', pageUrl)
        upMeta('property', 'og:type', 'website')
        upMeta('property', 'og:site_name', site)
        upMeta('property', 'og:image', image)
        upMeta('name', 'twitter:card', 'summary')
        upMeta('name', 'twitter:title', pageTitle)
        upMeta('name', 'twitter:description', pageDesc)
        upMeta('name', 'twitter:image', image)
      }, { pageTitle, pageDesc, pageUrl, image: OG_IMAGE, site: SITE })

      html = '<!doctype html>\n' + (await page.evaluate(() => document.documentElement.outerHTML))
    } catch (err) {
      console.error(`[prerender] attempt ${attempt} ${route} :: ${err.message}`)
    } finally {
      await page.close().catch(() => {})
    }
  }

  if (html) {
    const outDir = route === '/' ? dist : join(dist, route)
    mkdirSync(outDir, { recursive: true })
    writeFileSync(join(outDir, 'index.html'), html)
    ok++
    console.log(`[prerender] ok   ${route.padEnd(12)} ${html.length} bytes`)
  } else {
    console.error(`[prerender] skip ${route.padEnd(12)} (no content after retries; SPA fallback kept)`)
  }
}

await browser.close().catch(() => {})
server.close()
console.log(`[prerender] done: ${ok}/${ROUTES.length} routes rendered`)
