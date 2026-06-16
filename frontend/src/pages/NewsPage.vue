<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
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
      title="AI news from Penn & Wharton"
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

        <ol v-else class="news-list">
          <li v-for="(item, index) in list" :key="index" class="news-item">
            <a class="news-title" :href="item.url" target="_blank" rel="noopener noreferrer">{{ item.title }}</a>
            <p class="news-meta">{{ itemMeta(item) }}</p>
            <p v-if="item.excerpt" class="news-excerpt">{{ item.excerpt }}</p>
          </li>
        </ol>
      </div>
    </section>
  </PublicLayout>
</template>
