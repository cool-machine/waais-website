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

const routes = [
  { path: '/', name: 'home', component: HomePage },
  { path: '/events', name: 'events', component: EventsPage },
  { path: '/events/:id', name: 'event-detail', component: EventDetailPage },
  { path: '/startups', name: 'startups', component: StartupsPage },
  { path: '/startups/:id', name: 'startup-detail', component: StartupDetailPage },
  { path: '/about', name: 'about', component: AboutPage },
  { path: '/advisors', name: 'advisors', component: AdvisorsPage },
  { path: '/partners', name: 'partners', component: PartnersPage },
  { path: '/partners/:id', name: 'partner-detail', component: PartnerDetailPage },
  { path: '/news', name: 'news', component: NewsPage },
  { path: '/membership', name: 'membership', component: MembershipPage },
  { path: '/reset-password', name: 'reset-password', component: ResetPasswordPage },
  { path: '/sign-in', name: 'sign-in', component: SignInPage },
  { path: '/forum', name: 'forum-preview', component: ForumPreviewPage },
  { path: '/contact', name: 'contact', component: ContactPage },
  { path: '/legal', name: 'legal', component: LegalPage },
  { path: '/app/:view?', name: 'app-mockup', component: AppMockupPage },
]

export default createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})
