<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import CardGrid from '../components/CardGrid.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicTeamStore } from '../stores/publicTeam'

const store = usePublicTeamStore()
const { advisors, listLoading } = storeToRefs(store)

onMounted(() => {
  store.loadList().catch(() => {})
})

const isEmpty = computed(() => !listLoading.value && advisors.value.length === 0)

function initials(name) {
  const words = (name || '').trim().split(/\s+/).filter(Boolean)
  if (!words.length) return '•'
  if (words.length === 1) return words[0].slice(0, 1).toUpperCase()
  return (words[0][0] + words[words.length - 1][0]).toUpperCase()
}
</script>

<template>
  <PublicLayout>
    <PageHero
      compact
      title="Board advisors"
      lede="The advisors who help guide the Wharton Alumni AI Studio, listed alphabetically by surname."
    />
    <section class="section paper">
      <div class="section-inner">
        <div v-if="isEmpty" class="card">
          <p class="meta">Our board advisors will be announced soon.</p>
          <RouterLink class="button water" to="/about">Back to About</RouterLink>
        </div>

        <CardGrid v-else>
          <article v-for="member in advisors" :key="member.id" class="card info-card team-card">
            <div class="team-photo">
              <img v-if="member.photo_url" :src="member.photo_url" :alt="member.name">
              <span v-else class="logo-monogram" aria-hidden="true">{{ initials(member.name) }}</span>
            </div>
            <div class="card-body">
              <h3>{{ member.name }}</h3>
              <p v-if="member.role_title" class="meta">{{ member.role_title }}</p>
              <p v-if="member.bio" class="small">{{ member.bio }}</p>
              <div class="card-actions">
                <a v-if="member.linkedin_url" class="button water" :href="member.linkedin_url" target="_blank" rel="noopener noreferrer">LinkedIn</a>
              </div>
            </div>
          </article>
        </CardGrid>
      </div>
    </section>
  </PublicLayout>
</template>
