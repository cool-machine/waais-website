// Lightweight per-route <head> manager. Vue Router calls applyHead() after
// every navigation (see router/index.js), so each page gets its own title,
// meta description, canonical URL, and Open Graph / Twitter tags. The build
// prerenders each public route in a real browser AFTER this has run, so these
// per-page tags are baked into the static HTML crawlers receive.

const SITE = 'Wharton Alumni AI Studio'
const ORIGIN = 'https://whartonai.studio'
const OG_IMAGE = `${ORIGIN}/og-icon-512.png`
const DEFAULT_DESCRIPTION =
  'The Wharton Alumni AI Studio (WAAIS) connects Wharton alumni, entrepreneurs, ' +
  'investors, and researchers building real-world AI — an affinity group of the ' +
  'Wharton Club of the United Kingdom.'

function upsertMeta(attr, key, content) {
  if (typeof document === 'undefined') return
  let el = document.head.querySelector(`meta[${attr}="${key}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function upsertLink(rel, href) {
  if (typeof document === 'undefined') return
  let el = document.head.querySelector(`link[rel="${rel}"]`)
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', rel)
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

export function applyHead(meta = {}, path = '/') {
  if (typeof document === 'undefined') return
  const title = meta.title ? `${meta.title} — ${SITE}` : SITE
  const description = meta.description || DEFAULT_DESCRIPTION
  const url = `${ORIGIN}${path === '/' ? '/' : path}`

  document.title = title
  upsertMeta('name', 'description', description)
  upsertLink('canonical', url)

  upsertMeta('property', 'og:title', title)
  upsertMeta('property', 'og:description', description)
  upsertMeta('property', 'og:url', url)
  upsertMeta('property', 'og:type', 'website')
  upsertMeta('property', 'og:site_name', SITE)
  upsertMeta('property', 'og:image', OG_IMAGE)

  upsertMeta('name', 'twitter:card', 'summary')
  upsertMeta('name', 'twitter:title', title)
  upsertMeta('name', 'twitter:description', description)
  upsertMeta('name', 'twitter:image', OG_IMAGE)
}
