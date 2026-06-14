<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicNewsStore } from '../stores/publicNews'

const store = usePublicNewsStore()
const { list, listLoading, listError } = storeToRefs(store)

onMounted(() => {
  store.loadList().catch(() => {})
})

const isEmpty = computed(
  () => !listLoading.value && !listError.value && list.value.length === 0,
)

function formatDate(value) {
  if (!value) return ''
  try {
    return new Date(value).toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  } catch {
    return ''
  }
}

function itemMeta(item) {
  return [item.source, formatDate(item.published_at)].filter(Boolean).join(' · ')
}
</script>

<template>
  <PublicLayout>
    <PageHero
      compact
      title="AI news from Penn & Wharton."
      lede="The latest artificial-intelligence and analytics stories from across Penn and Wharton, refreshed automatically. Each headline links to the original source."
    />
    <section class="section paper">
      <div class="section-inner">
        <div v-if="listLoading && list.length === 0" class="card">
          <p class="meta">Loading news…</p>
        </div>

        <div v-else-if="listError" class="card">
          <p class="meta">News is temporarily unavailable. Please check back shortly.</p>
        </div>

        <div v-else-if="isEmpty" class="card">
          <p class="meta">No news right now. Please check back soon.</p>
        </div>

        <CardGrid v-else>
          <InfoCard
            v-for="(item, index) in list"
            :key="index"
            :title="item.title"
            :meta="itemMeta(item)"
          >
            {{ item.excerpt }}
            <template #actions>
              <a class="button water" :href="item.url" target="_blank" rel="noopener noreferrer">Read article</a>
            </template>
          </InfoCard>
        </CardGrid>
      </div>
    </section>
  </PublicLayout>
</template>
