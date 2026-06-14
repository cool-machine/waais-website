<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { useAuthUserStore } from '../stores/authUser'
import { usePublicEventsStore } from '../stores/publicEvents'
import { usePublicHomepageCardsStore } from '../stores/publicHomepageCards'
import { usePublicNewsStore } from '../stores/publicNews'
import { usePublicStartupsStore } from '../stores/publicStartups'

const authUser = useAuthUserStore()
const isAuthenticated = computed(() => authUser.isAuthenticated)

const heroVideoSrc = `${import.meta.env.BASE_URL}assets/waais-hero-video.mp4`

const DEFAULT_WHAT_WE_DO_CARDS = [
  {
    id: 'fallback-events',
    title: 'Events',
    body: 'AI Studio salons, workshops, roundtables, and demo nights for Wharton alumni working in AI — with recaps so you can catch what you missed.',
    link_url: '/events',
    link_label: 'See events',
  },
  {
    id: 'fallback-startups',
    title: 'Startup directory',
    body: 'AI startups founded and led by Wharton alumni — connecting founders with operators, investors, mentors, and customers.',
    link_url: '/startups',
    link_label: 'Browse startups',
  },
  {
    id: 'fallback-partners',
    title: 'Partners',
    body: 'The schools, alumni clubs, and organizations that support the Wharton Alumni AI Studio community.',
    link_url: '/partners',
    link_label: 'Meet our partners',
  },
]

const MEMBER_VALUE = [
  { id: 'value-connect', title: 'Connect', body: 'Business professionals, domain experts, researchers, investors, and founders across the deep-tech ecosystem.' },
  { id: 'value-build', title: 'Build', body: 'Turn Wharton business expertise into new AI projects and startups — supported by grants and Wharton Alumni Angels.' },
  { id: 'value-exchange', title: 'Exchange', body: 'AI-focused events and think tanks across fintech, supply chain, marketing, and product design.' },
]

const homepageCardsStore = usePublicHomepageCardsStore()
const whatWeDoCards = computed(() => {
  const cards = homepageCardsStore.bySection('what_we_do')
  return cards.length > 0 ? cards : DEFAULT_WHAT_WE_DO_CARDS
})

// Featured startups on the homepage share the public listings store
// with /startups, so navigating between them won't refetch.
const startupsStore = usePublicStartupsStore()
const { list: startups } = storeToRefs(startupsStore)
const featuredStartups = computed(() => startups.value.slice(0, 3))

const eventsStore = usePublicEventsStore()
const { list: events, listLoading: eventsLoading, listError: eventsError } = storeToRefs(eventsStore)
const selectedEvents = computed(() => events.value.slice(0, 3))

const newsStore = usePublicNewsStore()
const { list: news } = storeToRefs(newsStore)
const latestNews = computed(() => news.value.slice(0, 5))

onMounted(() => {
  authUser.loadCurrentUser().catch(() => {})
  homepageCardsStore.loadList({ perPage: 48 }).catch(() => {})
  startupsStore.loadList({ perPage: 48 }).catch(() => {})
  eventsStore.loadList({ time: 'past', perPage: 3 }).catch(() => {})
  newsStore.loadList().catch(() => {})
})

function formatNewsMeta(item) {
  const date = item.published_at ? formatEventDate(item.published_at) : ''
  return [item.source, date].filter(Boolean).join(' · ')
}

function formatEventDate(value) {
  if (!value) return 'Date TBD'
  try {
    return new Date(value).toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  } catch {
    return 'Date TBD'
  }
}
</script>

<template>
  <PublicLayout>
    <PageHero
      title="Where Wharton alumni working in AI meet, build, and share."
      lede="WAAIS brings together founders, operators, investors, researchers, and executives using artificial intelligence in the real world."
      :video-src="heroVideoSrc"
      poster="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=2200&q=80"
    >
      <div class="hero-actions">
        <RouterLink v-if="isAuthenticated" class="button primary" to="/app/dashboard">Go to dashboard</RouterLink>
        <RouterLink v-else class="button primary" to="/membership">Become a member</RouterLink>
        <RouterLink class="button secondary" to="/events">Explore events</RouterLink>
      </div>
      <template #aside>
        <div class="hero-card">
          <div class="grid two">
            <div class="metric"><span>Members</span><strong>740+</strong></div>
            <div class="metric"><span>AI startups</span><strong>30+</strong></div>
            <div class="metric"><span>Events yearly</span><strong>24</strong></div>
            <div class="metric"><span>Partners</span><strong>6</strong></div>
          </div>
        </div>
      </template>
    </PageHero>

    <section v-if="latestNews.length > 0" class="section reveal-section">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <h2>Latest AI news.</h2>
          </div>
          <RouterLink class="button water" to="/news">View all news</RouterLink>
        </div>
        <ol class="news-list">
          <li v-for="(item, index) in latestNews" :key="index" class="news-item">
            <a class="news-title" :href="item.url" target="_blank" rel="noopener noreferrer">{{ item.title }}</a>
            <p class="news-meta">{{ formatNewsMeta(item) }}</p>
          </li>
        </ol>
      </div>
    </section>

    <section class="section paper reveal-section">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <h2>What you'll find at the AI Studio.</h2>
          </div>
          <RouterLink v-if="isAuthenticated" class="button water" to="/app/dashboard">Go to dashboard</RouterLink>
          <RouterLink v-else class="button water" to="/membership">Apply for access</RouterLink>
        </div>
        <CardGrid>
          <InfoCard
            v-for="card in whatWeDoCards"
            :key="card.id"
            :title="card.title"
          >
            {{ card.body }}
            <template #actions v-if="card.link_url && card.link_label">
              <RouterLink v-if="card.link_url.startsWith('/')" class="button water" :to="card.link_url">{{ card.link_label }}</RouterLink>
              <a v-else class="button water" :href="card.link_url" target="_blank" rel="noopener noreferrer">{{ card.link_label }}</a>
            </template>
          </InfoCard>
        </CardGrid>
      </div>
    </section>

    <section class="section navy-band reveal-section">
      <div class="section-inner">
        <div class="grid two">
          <div>
            <h2>Turning Wharton expertise into impact in AI.</h2>
            <p class="lede">Founded in 2020 with the Wharton Club of the UK, WAAIS connects business professionals, domain experts, and researchers around real-world AI — helping alumni turn that expertise into new projects, startups, and an expert network across the deep-tech ecosystem.</p>
          </div>
          <div class="card">
            <h3>What members get</h3>
            <div class="timeline">
              <div v-for="(item, index) in MEMBER_VALUE" :key="item.id" class="timeline-item">
                <div class="timeline-node">{{ index + 1 }}</div>
                <div>
                  <h3>{{ item.title }}</h3>
                  <p class="small">{{ item.body }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section reveal-section">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <h2>Recent events.</h2>
          </div>
          <RouterLink class="button water" to="/events">View all events</RouterLink>
        </div>

        <div v-if="eventsLoading && selectedEvents.length === 0" class="card">
          <p class="meta">Loading events…</p>
        </div>

        <div v-else-if="eventsError" class="card">
          <p class="meta">Events are temporarily unavailable. Please check back shortly.</p>
        </div>

        <div v-else-if="selectedEvents.length === 0" class="card">
          <p class="meta">No events yet.</p>
        </div>

        <CardGrid v-else>
          <InfoCard
            v-for="event in selectedEvents"
            :key="event.id"
            :title="event.title"
            :meta="[formatEventDate(event.starts_at), event.location].filter(Boolean).join(' · ')"
            :image="event.image_url || ''"
          >
            {{ event.summary }}
            <template #actions>
              <RouterLink class="button water" :to="`/events/${event.id}`">Details</RouterLink>
            </template>
          </InfoCard>
        </CardGrid>
      </div>
    </section>

    <section v-if="featuredStartups.length > 0" class="section paper reveal-section">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <h2>Featured alumni startups.</h2>
          </div>
          <RouterLink class="button water" to="/startups">Open directory</RouterLink>
        </div>
        <CardGrid>
          <InfoCard
            v-for="startup in featuredStartups"
            :key="startup.id"
            :title="startup.name"
            :meta="startup.stage"
            :image="startup.logo_url || ''"
            :image-alt="`${startup.name} logo`"
          >
            {{ startup.tagline }}
            <template #actions>
              <RouterLink class="button water" :to="`/startups/${startup.id}`">Preview</RouterLink>
            </template>
          </InfoCard>
        </CardGrid>
      </div>
    </section>
  </PublicLayout>
</template>
