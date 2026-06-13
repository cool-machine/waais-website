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
const activeTime = ref('all')

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
    <PageHero compact title="Events for builders and decision makers." lede="Salons, workshops, roundtables, and demo nights for the WAAIS community." />
    <section class="section paper">
      <div class="section-inner">
        <div class="section-head">
          <div>
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
          <p class="meta">We couldn't load events. Please try again.</p>
          <button class="button water" type="button" @click="retry">Retry</button>
        </div>

        <div v-else-if="isEmpty" class="card">
          <p class="meta">No events yet.</p>
        </div>

        <CardGrid v-else>
          <InfoCard
            v-for="event in list"
            :key="event.id"
            :title="event.title"
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

        <section class="section-head" style="margin-top: 30px">
          <div>
            <h2>Suggest a founder salon, workshop, or focused AI adoption roundtable.</h2>
          </div>
          <RouterLink class="button primary" to="/contact">Propose a topic</RouterLink>
        </section>
      </div>
    </section>
  </PublicLayout>
</template>
