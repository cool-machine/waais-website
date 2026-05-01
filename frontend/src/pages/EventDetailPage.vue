<script setup>
import { computed, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicEventsStore } from '../stores/publicEvents'

const route = useRoute()
const store = usePublicEventsStore()
const { currentEvent, currentLoading, currentError } = storeToRefs(store)

function load(id) {
  return store.loadOne(id).catch(() => {
    // 404 / network error captured in store.currentError; template handles it.
  })
}

onMounted(() => load(route.params.id))
watch(() => route.params.id, (id) => { if (id) load(id) })

const event = computed(() => currentEvent.value)
const isNotFound = computed(() => currentError.value?.status === 404)
const isOtherError = computed(() => currentError.value !== null && !isNotFound.value)
const startsAt = computed(() => formatDateTime(event.value?.starts_at))
const endsAt = computed(() => formatDateTime(event.value?.ends_at))
const statusLabel = computed(() => formatStatus(event.value?.status))
const capacityLabel = computed(() => {
  if (!event.value?.capacity_limit) return 'Not capped'
  return `${event.value.capacity_limit} seats`
})
const registrationLabel = computed(() => {
  if (event.value?.status === 'recap' || event.value?.status === 'past') return 'Closed'
  if (event.value?.waitlist_open) return 'Waitlist open'
  if (event.value?.registration_url) return 'External registration'
  return 'TBD'
})

function formatDateTime(value) {
  if (!value) return null
  try {
    return new Date(value).toLocaleString(undefined, {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    })
  } catch {
    return null
  }
}

function formatStatus(status) {
  if (status === 'recap') return 'Recap'
  if (status === 'past') return 'Past'
  return 'Upcoming'
}
</script>

<template>
  <PublicLayout>
    <template v-if="event">
      <PageHero compact :eyebrow="event.visibility" :title="event.title" :lede="event.summary" />
      <section class="section paper">
        <div class="section-inner detail-grid">
          <article class="card image-card detail-card">
            <img v-if="event.image_url" :src="event.image_url" :alt="event.title">
            <div class="card-body">
              <p class="eyebrow">Event detail</p>
              <h2>{{ event.title }}</h2>
              <p>{{ event.description }}</p>
              <div v-if="event.recap_content" class="notice">
                <p class="eyebrow">Recap</p>
                <p class="small">{{ event.recap_content }}</p>
              </div>
              <div class="row">
                <RouterLink class="button water" to="/events">Back to events</RouterLink>
                <a
                  v-if="event.registration_url && event.status !== 'past' && event.status !== 'recap'"
                  class="button primary"
                  :href="event.registration_url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Register externally
                </a>
              </div>
            </div>
          </article>
          <aside class="card">
            <h3>Event details</h3>
            <div class="table compact-table">
              <div class="table-row"><span>Status</span><strong>{{ statusLabel }}</strong></div>
              <div v-if="startsAt" class="table-row"><span>Starts</span><strong>{{ startsAt }}</strong></div>
              <div v-if="endsAt" class="table-row"><span>Ends</span><strong>{{ endsAt }}</strong></div>
              <div v-if="event.location" class="table-row"><span>Location</span><strong>{{ event.location }}</strong></div>
              <div v-if="event.format" class="table-row"><span>Format</span><strong>{{ event.format }}</strong></div>
              <div class="table-row"><span>Capacity</span><strong>{{ capacityLabel }}</strong></div>
              <div class="table-row"><span>Registration</span><strong>{{ registrationLabel }}</strong></div>
            </div>
            <p class="small">Public events include published public and mixed-visibility records. Members-only and cancelled events stay out of this view.</p>
          </aside>
        </div>
      </section>
    </template>

    <section v-else-if="currentLoading" class="section paper">
      <div class="section-inner">
        <article class="card">
          <p class="meta">Loading…</p>
        </article>
      </div>
    </section>

    <section v-else-if="isNotFound" class="section paper">
      <div class="section-inner">
        <article class="card">
          <h1>Event not found.</h1>
          <p class="small">This event may not be published, may be members-only, may be cancelled, or may not exist.</p>
          <RouterLink class="button water" to="/events">Back to events</RouterLink>
        </article>
      </div>
    </section>

    <section v-else-if="isOtherError" class="section paper">
      <div class="section-inner">
        <article class="card">
          <h1>We couldn't load this event.</h1>
          <p class="small">The API didn't respond. Please try again in a moment.</p>
          <RouterLink class="button water" to="/events">Back to events</RouterLink>
        </article>
      </div>
    </section>
  </PublicLayout>
</template>
