<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicEventsStore } from '../stores/publicEvents'

const FILTERS = [
  { label: 'All', time: 'all' },
  { label: 'Upcoming', time: 'upcoming' },
  { label: 'Past', time: 'past' },
]

const store = usePublicEventsStore()
const { list, listLoading, listError } = storeToRefs(store)
const activeTime = ref('upcoming')

onMounted(() => {
  loadEvents(activeTime.value)
})

const hasError = computed(() => listError.value !== null)
const isEmpty = computed(() => !listLoading.value && !hasError.value && list.value.length === 0)

function loadEvents(time, force = false) {
  activeTime.value = time
  return store.loadList({ time, perPage: 48, force }).catch(() => {
    // Error is captured into store.listError; the template handles it.
  })
}

function retry() {
  loadEvents(activeTime.value, true)
}

function formatDate(value) {
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

function formatStatus(status) {
  if (status === 'recap') return 'Recap'
  if (status === 'past') return 'Past'
  return 'Upcoming'
}

function eventMeta(event) {
  return [formatStatus(event.status), formatDate(event.starts_at), event.location]
    .filter(Boolean)
    .join(' · ')
}
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Events" title="Events for builders and decision makers." lede="Each event will support public, members-only, or mixed visibility, external registration links, capacity limits, waitlists, and recap pages." />
    <section class="section paper">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <p class="eyebrow">Calendar</p>
            <h2>Upcoming and past sessions.</h2>
          </div>
          <div class="filters" aria-label="Event filters">
            <button
              v-for="filter in FILTERS"
              :key="filter.time"
              class="filter"
              :class="{ active: activeTime === filter.time }"
              type="button"
              @click="loadEvents(filter.time)"
            >
              {{ filter.label }}
            </button>
          </div>
        </div>

        <div v-if="listLoading && list.length === 0" class="card">
          <p class="meta">Loading events…</p>
        </div>

        <div v-else-if="hasError" class="card">
          <p class="eyebrow">We couldn't load events.</p>
          <p class="small">The API at <code>/api/public/events</code> didn't respond. Please try again in a moment.</p>
          <button class="button water" type="button" @click="retry">Retry</button>
        </div>

        <div v-else-if="isEmpty" class="card">
          <p class="eyebrow">No published events yet.</p>
          <p class="small">Published public and mixed-visibility events will show up here automatically.</p>
        </div>

        <CardGrid v-else>
          <InfoCard
            v-for="event in list"
            :key="event.id"
            :title="event.title"
            :eyebrow="event.visibility"
            :meta="eventMeta(event)"
            :image="event.image_url || ''"
          >
            {{ event.summary }}
            <template #actions>
              <p class="meta">{{ event.format || 'Format TBD' }}<span v-if="event.capacity_limit"> · Capacity {{ event.capacity_limit }}</span></p>
              <RouterLink class="button water" :to="`/events/${event.id}`">{{ event.status === 'recap' ? 'Recap' : 'Details' }}</RouterLink>
            </template>
          </InfoCard>
        </CardGrid>
      </div>
    </section>
  </PublicLayout>
</template>
