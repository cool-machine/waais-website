<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { useAuthUserStore } from '../stores/authUser'

const authUser = useAuthUserStore()
const isAuthenticated = computed(() => authUser.isAuthenticated)

onMounted(() => {
  authUser.loadCurrentUser().catch(() => {})
})
</script>

<template>
  <PublicLayout>
    <PageHero
      title="The WAAIS forum is live"
      lede="A space for AI builders inside the Wharton alumni community. Anyone can browse public discussions; approved members sign in with their WAAIS account to post — no separate forum password."
    />
    <section class="section paper">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <h2>Recent discussions</h2>
            <p>The forum is just getting started. Public discussions will show up here — be the first to start the conversation once you're an approved member.</p>
          </div>
          <div class="row">
            <a class="button primary" href="https://forum.whartonai.studio">Open the forum</a>
            <RouterLink v-if="isAuthenticated" class="button water" to="/app/dashboard">Go to dashboard</RouterLink>
            <RouterLink v-else class="button water" to="/membership">Apply for membership</RouterLink>
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
