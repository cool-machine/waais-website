import { createRouter, createWebHistory } from 'vue-router'
import AboutPage from '../pages/AboutPage.vue'
import AdvisorsPage from '../pages/AdvisorsPage.vue'
import ContactPage from '../pages/ContactPage.vue'
import AppMockupPage from '../pages/AppMockupPage.vue'
import EventDetailPage from '../pages/EventDetailPage.vue'
import EventsPage from '../pages/EventsPage.vue'
import ForumPreviewPage from '../pages/ForumPreviewPage.vue'
import HomePage from '../pages/HomePage.vue'
import LegalPage from '../pages/LegalPage.vue'
import MembershipPage from '../pages/MembershipPage.vue'
import NewsPage from '../pages/NewsPage.vue'
import PartnerDetailPage from '../pages/PartnerDetailPage.vue'
import PartnersPage from '../pages/PartnersPage.vue'
import ResetPasswordPage from '../pages/ResetPasswordPage.vue'
import SignInPage from '../pages/SignInPage.vue'
import StartupDetailPage from '../pages/StartupDetailPage.vue'
import StartupsPage from '../pages/StartupsPage.vue'

// `meta.title` is the page-specific part; the head manager appends the site
// name. `meta.description` is the per-page meta/OG description. Pages without
// meta fall back to the site defaults.
const routes = [
  { path: '/', name: 'home', component: HomePage, meta: { description: 'The Wharton Alumni AI Studio (WAAIS) connects Wharton alumni, entrepreneurs, investors, and researchers building real-world AI — an affinity group of the Wharton Club of the United Kingdom.' } },
  { path: '/events', name: 'events', component: EventsPage, meta: { title: 'Events', description: 'AI events, salons, workshops, and roundtables for the Wharton Alumni AI Studio community.' } },
  { path: '/events/:id', name: 'event-detail', component: EventDetailPage },
  { path: '/startups', name: 'startups', component: StartupsPage, meta: { title: 'Startups', description: 'AI companies founded and built by Wharton alumni.' } },
  { path: '/startups/:id', name: 'startup-detail', component: StartupDetailPage },
  { path: '/about', name: 'about', component: AboutPage, meta: { title: 'About', description: 'How the Wharton Alumni AI Studio began, what we do, and the founders behind it — George Gvishiani and Ines de Bagration de Ulloa.' } },
  { path: '/advisors', name: 'advisors', component: AdvisorsPage, meta: { title: 'Board of Advisors', description: 'The board advisors guiding the Wharton Alumni AI Studio.' } },
  { path: '/partners', name: 'partners', component: PartnersPage, meta: { title: 'Partners', description: 'Organisations partnering with the Wharton Alumni AI Studio.' } },
  { path: '/partners/:id', name: 'partner-detail', component: PartnerDetailPage },
  { path: '/news', name: 'news', component: NewsPage, meta: { title: 'AI News', description: 'AI and analytics news from Penn & Wharton, curated by the Wharton Alumni AI Studio.' } },
  { path: '/membership', name: 'membership', component: MembershipPage, meta: { title: 'Become a Member', description: 'Join the Wharton Alumni AI Studio — create your account and apply for membership.' } },
  { path: '/reset-password', name: 'reset-password', component: ResetPasswordPage },
  { path: '/sign-in', name: 'sign-in', component: SignInPage, meta: { title: 'Member Sign In', description: 'Sign in to your Wharton Alumni AI Studio member account.' } },
  { path: '/forum', name: 'forum-preview', component: ForumPreviewPage, meta: { title: 'Forum', description: 'The Wharton Alumni AI Studio member forum.' } },
  { path: '/contact', name: 'contact', component: ContactPage, meta: { title: 'Contact', description: 'Reach the Wharton Alumni AI Studio team about membership, events, and partnerships.' } },
  { path: '/legal', name: 'legal', component: LegalPage, meta: { title: 'Privacy & Legal', description: 'Privacy, cookies, and data requests for the Wharton Alumni AI Studio.' } },
  { path: '/app/:view?', name: 'app-mockup', component: AppMockupPage },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

// Set per-page title / description / canonical / social tags after each
// navigation (and on first load, which is what the prerender captures).
// Per-page <head> (title, description, Open Graph, canonical) is written by the
// prerender script after render — see scripts/prerender.mjs. We intentionally
// do NOT touch the head from a router hook: doing so during the initial
// navigation destabilizes the route component mount under prerender.

export default router
