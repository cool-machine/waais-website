<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { startups } from '../data/startups'

const route = useRoute()
const startup = computed(() => startups.find((item) => item.id === route.params.id))
</script>

<template>
  <PublicLayout>
    <template v-if="startup">
      <PageHero compact :eyebrow="startup.category" :title="startup.name" :lede="startup.summary" />
      <section class="section paper">
        <div class="section-inner detail-grid">
          <article class="card image-card detail-card">
            <img :src="startup.image" :alt="startup.name">
            <div class="card-body">
              <p class="eyebrow">Startup preview</p>
              <h2>{{ startup.name }}</h2>
              <p>{{ startup.detail }}</p>
              <div class="notice">
                <p class="small">Full founder identity, contact links, fundraising notes, and member-only search are intentionally gated until approved member access is implemented.</p>
              </div>
              <div class="row">
                <RouterLink class="button water" to="/startups">Back to directory</RouterLink>
                <RouterLink class="button primary" to="/membership">Apply for access</RouterLink>
              </div>
            </div>
          </article>
          <aside class="card">
            <h3>Directory rules</h3>
            <div class="table compact-table">
              <div class="table-row"><span>Stage</span><strong>{{ startup.stage }}</strong></div>
              <div class="table-row"><span>Visibility</span><strong>{{ startup.visibility }}</strong></div>
              <div class="table-row"><span>Review</span><strong>Admin approval required</strong></div>
              <div class="table-row"><span>Status model</span><strong>Draft / published / hidden / archived</strong></div>
            </div>
            <p class="small">Approved members can submit listings. Admins review before publication and keep audit history for changes.</p>
          </aside>
        </div>
      </section>
    </template>
    <section v-else class="section paper">
      <div class="section-inner">
        <article class="card">
          <h1>Startup not found.</h1>
          <RouterLink class="button water" to="/startups">Back to startups</RouterLink>
        </article>
      </div>
    </section>
  </PublicLayout>
</template>
