<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicPartnersStore } from '../stores/publicPartners'

const store = usePublicPartnersStore()
const { list, listLoading, listError } = storeToRefs(store)

onMounted(() => {
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
    <PageHero compact eyebrow="Partners" title="Partners that help alumni build." lede="Partner cards will become editable public content managed by admins and super admins." />
    <section class="section paper">
      <div class="section-inner">
        <div v-if="listLoading && list.length === 0" class="card">
          <p class="meta">Loading partners…</p>
        </div>

        <div v-else-if="hasError" class="card">
          <p class="eyebrow">We couldn't load partners.</p>
          <p class="small">The API at <code>/api/public/partners</code> didn't respond. Please try again in a moment.</p>
          <button class="button water" type="button" @click="retry">Retry</button>
        </div>

        <div v-else-if="isEmpty" class="card">
          <p class="eyebrow">No published partners yet.</p>
          <p class="small">Published public and mixed-visibility partner profiles will show up here automatically.</p>
        </div>

        <CardGrid v-else>
          <InfoCard
            v-for="partner in list"
            :key="partner.id"
            :title="partner.name"
            :eyebrow="partner.partner_type || 'Partner'"
            :image="partner.logo_url || ''"
            :image-alt="`${partner.name} logo`"
          >
            {{ partner.summary }}
            <template #actions>
              <RouterLink class="button water" :to="`/partners/${partner.id}`">View partner</RouterLink>
            </template>
          </InfoCard>
        </CardGrid>
      </div>
    </section>
  </PublicLayout>
</template>
