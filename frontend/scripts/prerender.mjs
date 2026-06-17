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
  const page = await browser.newPage()
  await page.setRequestInterception(true)
  page.on('request', async (req) => {
    const url = req.url()
    if (url.startsWith(API_ORIGIN)) {
      if (req.method() === 'OPTIONS') {
        return req.respond({ status: 204, headers: CORS, body: '' }).catch(() => {})
      }
      try {
        const upstream = await fetch(url, { method: req.method(), headers: { Accept: 'application/json' } })
        const body = Buffer.from(await upstream.arrayBuffer())
        return req.respond({
          status: upstream.status,
          headers: { ...CORS, 'content-type': upstream.headers.get('content-type') || 'application/json' },
          body,
        }).catch(() => {})
      } catch {
        return req.respond({ status: 502, headers: CORS, body: '{"data":[]}' }).catch(() => {})
      }
    }
    return req.continue().catch(() => {})
  })

  try {
    await page.goto(`http://127.0.0.1:${PORT}${route}`, { waitUntil: 'networkidle0', timeout: 45000 })
    await new Promise((r) => setTimeout(r, 400)) // let Vue settle after data resolves
    const html = '<!doctype html>\n' + (await page.evaluate(() => document.documentElement.outerHTML))
    const outDir = route === '/' ? dist : join(dist, route)
    mkdirSync(outDir, { recursive: true })
    writeFileSync(join(outDir, 'index.html'), html)
    ok++
    console.log(`[prerender] ok   ${route.padEnd(12)} ${html.length} bytes`)
  } catch (err) {
    console.error(`[prerender] FAIL ${route.padEnd(12)} ${err.message}`)
  } finally {
    await page.close().catch(() => {})
  }
}

await browser.close().catch(() => {})
server.close()
console.log(`[prerender] done: ${ok}/${ROUTES.length} routes rendered`)
