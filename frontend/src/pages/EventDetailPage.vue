<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { events } from '../data/events'

const route = useRoute()
const event = computed(() => events.find((item) => item.id === route.params.id))
</script>

<template>
  <PublicLayout>
    <template v-if="event">
      <PageHero compact :eyebrow="event.visibility" :title="event.title" :lede="event.summary" />
      <section class="section paper">
        <div class="section-inner detail-grid">
          <article class="card image-card detail-card">
            <img :src="event.image" :alt="event.title">
            <div class="card-body">
              <p class="eyebrow">Event detail</p>
              <h2>{{ event.title }}</h2>
              <p>{{ event.description }}</p>
              <div class="row">
                <RouterLink class="button water" to="/events">Back to events</RouterLink>
                <button class="button primary" type="button">{{ event.recapAvailable ? 'View recap' : 'Register externally' }}</button>
              </div>
            </div>
          </article>
          <aside class="card">
            <h3>Backend contract preview</h3>
            <div class="table compact-table">
              <div class="table-row"><span>Status</span><strong>{{ event.status }}</strong></div>
              <div class="table-row"><span>Date</span><strong>{{ event.date }}</strong></div>
              <div class="table-row"><span>Location</span><strong>{{ event.location }}</strong></div>
              <div class="table-row"><span>Format</span><strong>{{ event.format }}</strong></div>
              <div class="table-row"><span>Capacity</span><strong>{{ event.capacity }}</strong></div>
              <div class="table-row"><span>Registration</span><strong>{{ event.registration }}</strong></div>
              <div class="table-row"><span>Reminder</span><strong>{{ event.reminderDefault }}</strong></div>
            </div>
            <p class="small">Laravel should later supply external registration URLs, waitlist state, cancellation visibility, recap content, and notification timing.</p>
          </aside>
        </div>
      </section>
    </template>
    <section v-else class="section paper">
      <div class="section-inner">
        <article class="card">
          <h1>Event not found.</h1>
          <RouterLink class="button water" to="/events">Back to events</RouterLink>
        </article>
      </div>
    </section>
  </PublicLayout>
</template>
