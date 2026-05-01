<script setup>
import { computed, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicStartupsStore } from '../stores/publicStartups'

const route = useRoute()
const store = usePublicStartupsStore()
const { currentListing, currentLoading, currentError } = storeToRefs(store)

function load(id) {
  return store.loadOne(id).catch(() => {
    // 404 / network error captured in store.currentError; template handles it.
  })
}

onMounted(() => load(route.params.id))
watch(() => route.params.id, (id) => { if (id) load(id) })

const isNotFound = computed(() => currentError.value?.status === 404)
const isOtherError = computed(() => currentError.value !== null && !isNotFound.value)
const startup = computed(() => currentListing.value)
const founders = computed(() => Array.isArray(startup.value?.founders) ? startup.value.founders : [])
const approvedAt = computed(() => {
  if (!startup.value?.approved_at) return null
  try {
    return new Date(startup.value.approved_at).toLocaleDateString(undefined, {
      year: 'numeric', month: 'long', day: 'numeric',
    })
  } catch {
    return null
  }
})
</script>

<template>
  <PublicLayout>
    <template v-if="startup">
      <PageHero compact :eyebrow="startup.industry" :title="startup.name" :lede="startup.tagline" />
      <section class="section paper">
        <div class="section-inner detail-grid">
          <article class="card image-card detail-card">
            <img v-if="startup.logo_url" :src="startup.logo_url" :alt="`${startup.name} logo`">
            <div class="card-body">
              <p class="eyebrow">Startup profile</p>
              <h2>{{ startup.name }}</h2>
              <p>{{ startup.description }}</p>
              <div class="notice">
                <p class="small">Founder contact details and member-only search are intentionally gated until approved member access is implemented.</p>
              </div>
              <div class="row">
                <a v-if="startup.website_url" class="button primary" :href="startup.website_url" target="_blank" rel="noopener noreferrer">Visit site</a>
                <a v-if="startup.linkedin_url" class="button water" :href="startup.linkedin_url" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                <RouterLink class="button water" to="/startups">Back to directory</RouterLink>
              </div>
            </div>
          </article>
          <aside class="card">
            <h3>Listing details</h3>
            <div class="table compact-table">
              <div v-if="startup.stage" class="table-row"><span>Stage</span><strong>{{ startup.stage }}</strong></div>
              <div v-if="startup.location" class="table-row"><span>Location</span><strong>{{ startup.location }}</strong></div>
              <div v-if="founders.length > 0" class="table-row"><span>Founders</span><strong>{{ founders.join(', ') }}</strong></div>
              <div v-if="approvedAt" class="table-row"><span>Published</span><strong>{{ approvedAt }}</strong></div>
            </div>
            <p class="small">Approved members can submit listings. Admins review before publication and keep audit history for changes.</p>
            <RouterLink class="button primary" to="/membership">Apply for member access</RouterLink>
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
          <h1>Startup not found.</h1>
          <p class="small">This listing may not be published, may be members-only, or may not exist.</p>
          <RouterLink class="button water" to="/startups">Back to startups</RouterLink>
        </article>
      </div>
    </section>

    <section v-else-if="isOtherError" class="section paper">
      <div class="section-inner">
        <article class="card">
          <h1>We couldn't load this listing.</h1>
          <p class="small">The API didn't respond. Please try again in a moment.</p>
          <RouterLink class="button water" to="/startups">Back to startups</RouterLink>
        </article>
      </div>
    </section>
  </PublicLayout>
</template>
