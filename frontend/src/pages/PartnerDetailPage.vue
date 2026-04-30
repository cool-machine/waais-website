<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { partners } from '../data/partners'

const route = useRoute()
const partner = computed(() => partners.find((item) => item.id === route.params.id))
</script>

<template>
  <PublicLayout>
    <template v-if="partner">
      <PageHero compact :eyebrow="partner.type" :title="partner.name" :lede="partner.summary" />
      <section class="section paper">
        <div class="section-inner detail-grid">
          <article class="card">
            <p class="eyebrow">Partner detail</p>
            <h2>CMS-managed partner profile.</h2>
            <p>Partner pages can either route to internal detail pages or link to external partner websites. Admins and super admins will manage these records in Laravel later.</p>
            <div class="row">
              <RouterLink class="button water" to="/partners">Back to partners</RouterLink>
              <RouterLink class="button primary" to="/contact">Discuss partnership</RouterLink>
            </div>
          </article>
          <aside class="card">
            <h3>Publishing rules</h3>
            <div class="table compact-table">
              <div class="table-row"><span>Type</span><strong>{{ partner.type }}</strong></div>
              <div class="table-row"><span>Destination</span><strong>{{ partner.destination }}</strong></div>
              <div class="table-row"><span>Editable by</span><strong>Admins / super admins</strong></div>
              <div class="table-row"><span>Status model</span><strong>Draft / published / hidden / archived</strong></div>
            </div>
            <p class="small">Public content changes should be audited and removable content should be hidden/archived before hard deletion.</p>
          </aside>
        </div>
      </section>
    </template>
    <section v-else class="section paper">
      <div class="section-inner">
        <article class="card">
          <h1>Partner not found.</h1>
          <RouterLink class="button water" to="/partners">Back to partners</RouterLink>
        </article>
      </div>
    </section>
  </PublicLayout>
</template>
