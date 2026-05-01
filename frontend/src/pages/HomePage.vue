<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { events } from '../data/events'
import { usePublicStartupsStore } from '../stores/publicStartups'

const heroVideoSrc = `${import.meta.env.BASE_URL}assets/waais-hero-video.mp4`

// Featured startups on the homepage share the public listings store
// with /startups, so navigating between them won't refetch.
const startupsStore = usePublicStartupsStore()
const { list: startups } = storeToRefs(startupsStore)
const featuredStartups = computed(() => startups.value.slice(0, 3))

onMounted(() => {
  startupsStore.loadList({ perPage: 48 }).catch(() => {})
})
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
          <InfoCard title="Events with memory" eyebrow="Programs">Host salons, roundtables, workshops, startup demo nights, and recap pages for what members learn.</InfoCard>
          <InfoCard title="Startup discovery" eyebrow="Directory">Connect alumni founders with operators, investors, mentors, customers, and partners.</InfoCard>
          <InfoCard title="Persistent forum memory" eyebrow="Discourse">Move high-value conversation from WhatsApp into searchable industry-first Discourse categories.</InfoCard>
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
              <div class="timeline-item"><div class="timeline-node">1</div><div><h3>Google sign in</h3><p class="small">New accounts start as pending.</p></div></div>
              <div class="timeline-item"><div class="timeline-node">2</div><div><h3>Admin approval</h3><p class="small">Approved alumni, students, or invited guests receive the correct access role.</p></div></div>
              <div class="timeline-item"><div class="timeline-node">3</div><div><h3>Forum access</h3><p class="small">Discourse opens through the same account at forum.whartonai.studio.</p></div></div>
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
        <CardGrid>
          <InfoCard v-for="event in events.slice(0, 3)" :key="event.id" :title="event.title" :eyebrow="event.status" :meta="`${event.date} · ${event.location}`" :image="event.image">
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
