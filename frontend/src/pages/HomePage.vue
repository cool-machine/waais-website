<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicEventsStore } from '../stores/publicEvents'
import { usePublicHomepageCardsStore } from '../stores/publicHomepageCards'
import { usePublicStartupsStore } from '../stores/publicStartups'

const heroVideoSrc = `${import.meta.env.BASE_URL}assets/waais-hero-video.mp4`

const DEFAULT_WHAT_WE_DO_CARDS = [
  {
    id: 'fallback-events',
    eyebrow: 'Programs',
    title: 'Events with memory',
    body: 'Host salons, roundtables, workshops, startup demo nights, and recap pages for what members learn.',
  },
  {
    id: 'fallback-startups',
    eyebrow: 'Directory',
    title: 'Startup discovery',
    body: 'Connect alumni founders with operators, investors, mentors, customers, and partners.',
  },
  {
    id: 'fallback-forum',
    eyebrow: 'Discourse',
    title: 'Persistent forum memory',
    body: 'Move high-value conversation from WhatsApp into searchable industry-first Discourse categories.',
  },
]

const DEFAULT_ACCESS_FLOW_CARDS = [
  { id: 'fallback-sign-in', title: 'Google sign in', body: 'New accounts start as pending.' },
  { id: 'fallback-approval', title: 'Admin approval', body: 'Approved alumni, students, or invited guests receive the correct access role.' },
  { id: 'fallback-forum', title: 'Forum access', body: 'Discourse opens through the same account at forum.whartonai.studio.' },
]

const homepageCardsStore = usePublicHomepageCardsStore()
const whatWeDoCards = computed(() => {
  const cards = homepageCardsStore.bySection('what_we_do')
  return cards.length > 0 ? cards : DEFAULT_WHAT_WE_DO_CARDS
})
const accessFlowCards = computed(() => {
  const cards = homepageCardsStore.bySection('access_flow')
  return cards.length > 0 ? cards : DEFAULT_ACCESS_FLOW_CARDS
})

// Featured startups on the homepage share the public listings store
// with /startups, so navigating between them won't refetch.
const startupsStore = usePublicStartupsStore()
const { list: startups } = storeToRefs(startupsStore)
const featuredStartups = computed(() => startups.value.slice(0, 3))

const eventsStore = usePublicEventsStore()
const { list: events, listLoading: eventsLoading, listError: eventsError } = storeToRefs(eventsStore)
const selectedEvents = computed(() => events.value.slice(0, 3))

onMounted(() => {
  homepageCardsStore.loadList({ perPage: 48 }).catch(() => {})
  startupsStore.loadList({ perPage: 48 }).catch(() => {})
  eventsStore.loadList({ time: 'upcoming', perPage: 3 }).catch(() => {})
})

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
      eyebrow="Wharton alumni building with AI"
      title="Where Wharton alumni working in AI meet, build, and share."
      lede="WAAIS brings together founders, operators, investors, researchers, and executives using artificial intelligence in the real world."
      :video-src="heroVideoSrc"
      poster="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=2200&q=80"
    >
      <div class="hero-actions">
        <RouterLink class="button primary" to="/membership">Become a member</RouterLink>
        <RouterLink class="button secondary" to="/events">Explore events</RouterLink>
      </div>
      <template #aside>
        <div class="hero-card">
          <p class="eyebrow">Community snapshot</p>
          <div class="grid two">
            <div class="metric"><span>Members target</span><strong>500+</strong></div>
            <div class="metric"><span>AI startups</span><strong>60+</strong></div>
            <div class="metric"><span>Events yearly</span><strong>24</strong></div>
            <div class="metric"><span>Forum categories</span><strong>8</strong></div>
          </div>
        </div>
      </template>
    </PageHero>

    <section class="section paper reveal-section">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <p class="eyebrow">What we do</p>
            <h2>Turn alumni AI work into durable community infrastructure.</h2>
          </div>
          <RouterLink class="button water" to="/membership">Apply for access</RouterLink>
        </div>
        <CardGrid>
          <InfoCard
            v-for="card in whatWeDoCards"
            :key="card.id"
            :title="card.title"
            :eyebrow="card.eyebrow"
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
            <p class="eyebrow">Member platform</p>
            <h2>One account for dashboard, events, and forum.</h2>
            <p class="lede">Members sign in with Google, admins approve access, and Discourse SSO opens the private forum without a second account.</p>
          </div>
          <div class="card">
            <h3>Access flow</h3>
            <div class="timeline">
              <div v-for="(card, index) in accessFlowCards" :key="card.id" class="timeline-item">
                <div class="timeline-node">{{ index + 1 }}</div>
                <div>
                  <h3>{{ card.title }}</h3>
                  <p class="small">{{ card.body }}</p>
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
            <p class="eyebrow">Upcoming</p>
            <h2>Selected events.</h2>
          </div>
          <RouterLink class="button water" to="/events">View all events</RouterLink>
        </div>

        <div v-if="eventsLoading && selectedEvents.length === 0" class="card">
          <p class="meta">Loading events…</p>
        </div>

        <div v-else-if="eventsError" class="card">
          <p class="eyebrow">Events unavailable.</p>
          <p class="small">The public events API didn't respond. The full calendar will return when the API is available.</p>
        </div>

        <div v-else-if="selectedEvents.length === 0" class="card">
          <p class="eyebrow">No upcoming public events yet.</p>
          <p class="small">Published public and mixed-visibility events will appear here automatically.</p>
        </div>

        <CardGrid v-else>
          <InfoCard
            v-for="event in selectedEvents"
            :key="event.id"
            :title="event.title"
            :eyebrow="event.status === 'recap' ? 'Recap' : 'Upcoming'"
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
            <p class="eyebrow">Featured startups</p>
            <h2>Approved member submissions, public teasers.</h2>
          </div>
          <RouterLink class="button water" to="/startups">Open directory</RouterLink>
        </div>
        <CardGrid>
          <InfoCard
            v-for="startup in featuredStartups"
            :key="startup.id"
            :title="startup.name"
            :eyebrow="startup.industry"
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
