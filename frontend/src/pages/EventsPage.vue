<script setup>
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { events } from '../data/events'
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
            <button class="filter active" type="button">All</button>
            <button class="filter" type="button">Upcoming</button>
            <button class="filter" type="button">Past</button>
            <button class="filter" type="button">Workshops</button>
          </div>
        </div>
        <CardGrid>
          <InfoCard v-for="event in events" :key="event.id" :title="event.title" :eyebrow="event.visibility" :meta="`${event.status} · ${event.date} · ${event.location}`" :image="event.image">
            {{ event.summary }}
            <template #actions>
              <p class="meta">{{ event.registration }} · {{ event.capacity }}</p>
              <button class="button water" type="button">{{ event.status === 'Recap' ? 'Recap' : 'Register' }}</button>
            </template>
          </InfoCard>
        </CardGrid>
        <div class="notice" style="margin-top: 24px">
          <p class="small">Backend note: event records need visibility, external registration URL, capacity, waitlist state, cancellation state, recap content, and configurable reminder timing.</p>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
