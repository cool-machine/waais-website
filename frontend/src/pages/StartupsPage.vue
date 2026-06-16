<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicStartupsStore } from '../stores/publicStartups'

const store = usePublicStartupsStore()
const { list, listLoading, listError } = storeToRefs(store)

onMounted(() => {
  // Public list — anonymous, paginated, capped at 48 server-side.
  // The store's TTL means back-navigation from a detail page won't refetch.
  store.loadList({ perPage: 48 }).catch(() => {
    // Error is captured into store.listError; the template handles it.
  })
})

const hasError = computed(() => listError.value !== null)
const isEmpty = computed(() => !listLoading.value && !hasError.value && list.value.length === 0)

function retry() {
  store.loadList({ perPage: 48, force: true }).catch(() => {})
}
</script>

<template>
  <PublicLayout>
    <PageHero compact title="Discover Wharton alumni AI companies" lede="AI companies founded and built by Wharton alumni." />
    <section class="section paper">
      <div class="section-inner">
        <div class="lock-box">
          <div>
            <h2>Full member directory and contact details require approved member access</h2>
            <p>Approved members can submit startup listings. Admins review and approve listings before publication.</p>
          </div>
          <RouterLink class="button water" to="/membership">Apply for access</RouterLink>
        </div>

        <div v-if="listLoading && list.length === 0" class="card">
          <p class="meta">Loading the directory…</p>
        </div>

        <div v-else-if="hasError" class="card">
          <p class="meta">We couldn't load the directory. Please try again.</p>
          <button class="button water" type="button" @click="retry">Retry</button>
        </div>

        <div v-else-if="isEmpty" class="card">
          <p class="meta">No listings yet.</p>
        </div>

        <CardGrid v-else>
          <InfoCard
            v-for="startup in list"
            :key="startup.id"
            :title="startup.name"
            :meta="startup.stage"
            :image="startup.logo_url || ''"
            :image-alt="`${startup.name} logo`"
            logo-fallback
          >
            {{ startup.tagline }}
            <template #actions>
              <p class="meta">Founder contact details unlock after approval.</p>
              <RouterLink class="button water" :to="`/startups/${startup.id}`">View profile</RouterLink>
            </template>
          </InfoCard>
        </CardGrid>

        <section class="section-head" style="margin-top: 30px">
          <div>
            <h2>Startup listings enter admin review before publication</h2>
          </div>
          <RouterLink class="button primary" to="/contact">Request listing</RouterLink>
        </section>
      </div>
    </section>
  </PublicLayout>
</template>
