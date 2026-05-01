import { existsSync, readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = resolve(__dirname, '..')
const repoRoot = resolve(root, '..')

const routerPath = join(root, 'src/router/index.js')
const appMockupPath = join(root, 'src/pages/AppMockupPage.vue')
const routerSource = readFileSync(routerPath, 'utf8')
const appMockupSource = readFileSync(appMockupPath, 'utf8')

const routePatterns = [
  ['/', 'home', 'HomePage.vue'],
  ['/events', 'events', 'EventsPage.vue'],
  ['/events/:id', 'event-detail', 'EventDetailPage.vue'],
  ['/startups', 'startups', 'StartupsPage.vue'],
  ['/startups/:id', 'startup-detail', 'StartupDetailPage.vue'],
  ['/about', 'about', 'AboutPage.vue'],
  ['/partners', 'partners', 'PartnersPage.vue'],
  ['/partners/:id', 'partner-detail', 'PartnerDetailPage.vue'],
  ['/membership', 'membership', 'MembershipPage.vue'],
  ['/forum', 'forum-preview', 'ForumPreviewPage.vue'],
  ['/contact', 'contact', 'ContactPage.vue'],
  ['/legal', 'legal', 'LegalPage.vue'],
  ['/app/:view?', 'app-mockup', 'AppMockupPage.vue'],
]

const concreteUrls = [
  '/',
  '/events',
  '/events/ai-founder-salon',
  '/startups',
  '/startups/neural-insights',
  '/about',
  '/partners',
  '/partners/cloud-platform',
  '/membership',
  '/forum',
  '/contact',
  '/legal',
  '/app/sign-in',
  '/app/pending',
  '/app/dashboard',
  '/app/profile',
  '/app/my-events',
  '/app/forum-feed',
  '/app/admin',
  '/app/approvals',
  '/app/users',
  '/app/events-admin',
  '/app/content-admin',
  '/app/announcements',
]

const appViews = [
  'sign-in',
  'pending',
  'dashboard',
  'profile',
  'my-events',
  'forum-feed',
  'admin',
  'approvals',
  'users',
  'events-admin',
  'content-admin',
  'announcements',
]

const failures = []

for (const [path, name, componentFile] of routePatterns) {
  if (!routerSource.includes(`path: '${path}'`)) {
    failures.push(`Missing route path ${path}`)
  }
  if (!routerSource.includes(`name: '${name}'`)) {
    failures.push(`Missing route name ${name}`)
  }
  if (!existsSync(join(root, 'src/pages', componentFile))) {
    failures.push(`Missing page component ${componentFile}`)
  }
}

for (const view of appViews) {
  if (!appMockupSource.includes(`'${view}'`) && !appMockupSource.includes(`currentView === '${view}'`)) {
    failures.push(`Missing app mockup view ${view}`)
  }
}

const requiredPagesArtifacts = [
  'index.html',
  '404.html',
  'assets/waais-hero-video.mp4',
  'favicon.svg',
  'icons.svg',
]

for (const artifact of requiredPagesArtifacts) {
  if (!existsSync(join(repoRoot, artifact))) {
    failures.push(`Missing GitHub Pages artifact ${artifact}`)
  }
}

if (!readFileSync(join(repoRoot, 'index.html'), 'utf8').includes('/waais-website/assets/')) {
  failures.push('Root index.html is not using the /waais-website/ asset base')
}

if (concreteUrls.length !== 24) {
  failures.push(`Expected 24 concrete route URLs, got ${concreteUrls.length}`)
}

if (failures.length > 0) {
  console.error('Route smoke test failed:')
  for (const failure of failures) {
    console.error(`- ${failure}`)
  }
  process.exit(1)
}

console.log(`Route smoke test passed: ${routePatterns.length} route patterns and ${concreteUrls.length} concrete URLs checked.`)
