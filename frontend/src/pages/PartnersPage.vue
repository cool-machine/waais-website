<script setup>
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { partners } from '../data/partners'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Partners" title="Partners that help alumni build." lede="Partner cards will become editable public content managed by admins and super admins." />
    <section class="section paper">
      <div class="section-inner">
        <CardGrid>
          <InfoCard v-for="partner in partners" :key="partner.id" :title="partner.name" :eyebrow="partner.type">
            Partner detail pages or external partner links will be wired during implementation.
          </InfoCard>
        </CardGrid>
      </div>
    </section>
  </PublicLayout>
</template>
