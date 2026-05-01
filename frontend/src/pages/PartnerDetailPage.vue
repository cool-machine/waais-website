<script setup>
import { computed, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicPartnersStore } from '../stores/publicPartners'

const route = useRoute()
const store = usePublicPartnersStore()
const { currentPartner, currentLoading, currentError } = storeToRefs(store)

function load(id) {
  return store.loadOne(id).catch(() => {
    // 404 / network error captured in store.currentError; template handles it.
  })
}

onMounted(() => load(route.params.id))
watch(() => route.params.id, (id) => { if (id) load(id) })

const partner = computed(() => currentPartner.value)
const isNotFound = computed(() => currentError.value?.status === 404)
const isOtherError = computed(() => currentError.value !== null && !isNotFound.value)
const publishedAt = computed(() => {
  if (!partner.value?.published_at) return null
  try {
    return new Date(partner.value.published_at).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })
  } catch {
    return null
  }
})
</script>

<template>
  <PublicLayout>
    <template v-if="partner">
      <PageHero compact :eyebrow="partner.partner_type || 'Partner'" :title="partner.name" :lede="partner.summary" />
      <section class="section paper">
        <div class="section-inner detail-grid">
          <article class="card image-card detail-card">
            <img v-if="partner.logo_url" :src="partner.logo_url" :alt="`${partner.name} logo`">
            <div class="card-body">
              <p class="eyebrow">Partner detail</p>
              <h2>{{ partner.name }}</h2>
              <p>{{ partner.description }}</p>
              <div class="row">
                <a v-if="partner.website_url" class="button primary" :href="partner.website_url" target="_blank" rel="noopener noreferrer">Visit partner</a>
                <RouterLink class="button water" to="/partners">Back to partners</RouterLink>
                <RouterLink class="button water" to="/contact">Discuss partnership</RouterLink>
              </div>
            </div>
          </article>
          <aside class="card">
            <h3>Partner profile</h3>
            <div class="table compact-table">
              <div v-if="partner.partner_type" class="table-row"><span>Type</span><strong>{{ partner.partner_type }}</strong></div>
              <div class="table-row"><span>Visibility</span><strong>{{ partner.visibility }}</strong></div>
              <div v-if="publishedAt" class="table-row"><span>Published</span><strong>{{ publishedAt }}</strong></div>
            </div>
            <p class="small">{{ partner.summary }}</p>
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
          <h1>Partner not found.</h1>
          <p class="small">This partner profile may not be published, may be members-only, or may not exist.</p>
          <RouterLink class="button water" to="/partners">Back to partners</RouterLink>
        </article>
      </div>
    </section>

    <section v-else-if="isOtherError" class="section paper">
      <div class="section-inner">
        <article class="card">
          <h1>We couldn't load this partner.</h1>
          <p class="small">The API didn't respond. Please try again in a moment.</p>
          <RouterLink class="button water" to="/partners">Back to partners</RouterLink>
        </article>
      </div>
    </section>
  </PublicLayout>
</template>
