<script setup>
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { startups } from '../data/startups'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Startups" title="Discover Wharton alumni AI companies." lede="Public users see teaser cards. Approved members later unlock full startup profiles, contact links, and member-only search." />
    <section class="section paper">
      <div class="section-inner">
        <div class="lock-box">
          <div>
            <span class="tag">Public preview</span>
            <h2>Full startup profiles require approved member access.</h2>
            <p>Approved members can submit startup listings. Admins review and approve listings before publication.</p>
          </div>
          <RouterLink class="button water" to="/membership">Apply for access</RouterLink>
        </div>
        <CardGrid>
          <InfoCard v-for="startup in startups" :key="startup.id" :title="startup.name" :eyebrow="startup.category" :meta="startup.stage" :image="startup.image">
            {{ startup.summary }}
            <template #actions>
              <p class="meta">Founder details and contact links unlock after approval.</p>
              <RouterLink class="button water" :to="`/startups/${startup.id}`">Preview profile</RouterLink>
            </template>
          </InfoCard>
        </CardGrid>
        <section class="section-head" style="margin-top: 30px">
          <div>
            <p class="eyebrow">Member submissions</p>
            <h2>Startup listings enter admin review before publication.</h2>
          </div>
          <RouterLink class="button primary" to="/contact">Request listing</RouterLink>
        </section>
      </div>
    </section>
  </PublicLayout>
</template>
