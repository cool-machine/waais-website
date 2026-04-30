import { createRouter, createWebHistory } from 'vue-router'
import AboutPage from '../pages/AboutPage.vue'
import ContactPage from '../pages/ContactPage.vue'
import EventsPage from '../pages/EventsPage.vue'
import ForumPreviewPage from '../pages/ForumPreviewPage.vue'
import HomePage from '../pages/HomePage.vue'
import LegalPage from '../pages/LegalPage.vue'
import MembershipPage from '../pages/MembershipPage.vue'
import PartnersPage from '../pages/PartnersPage.vue'
import StartupsPage from '../pages/StartupsPage.vue'

const routes = [
  { path: '/', name: 'home', component: HomePage },
  { path: '/events', name: 'events', component: EventsPage },
  { path: '/startups', name: 'startups', component: StartupsPage },
  { path: '/about', name: 'about', component: AboutPage },
  { path: '/partners', name: 'partners', component: PartnersPage },
  { path: '/membership', name: 'membership', component: MembershipPage },
  { path: '/forum', name: 'forum-preview', component: ForumPreviewPage },
  { path: '/contact', name: 'contact', component: ContactPage },
  { path: '/legal', name: 'legal', component: LegalPage },
]

export default createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})
