<script setup>
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { usePublicTeamStore } from '../stores/publicTeam'

const teamStore = usePublicTeamStore()
const { founders, advisors } = storeToRefs(teamStore)

onMounted(() => {
  teamStore.loadList().catch(() => {})
})

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
      title="About the Wharton Alumni AI Studio"
      lede="Connecting Wharton alumni, entrepreneurs, investors, and researchers around real-world AI since 2020."
    />

    <section class="section paper">
      <div class="section-inner">
        <div class="grid journey">
          <article class="card journey-card">
            <span class="journey-step" aria-hidden="true">1</span>
            <h2 class="journey-title">Where we started</h2>
            <p>
              The Wharton Alumni AI Studio (WAAIS) began in 2020, in collaboration with the Wharton
              Club of the United Kingdom, to strengthen the connections between business
              professionals, domain experts, and researchers in artificial intelligence — starting
              with a series of AI-focused events.
            </p>
            <p>
              In 2023 we launched the Wharton AI Studio Expert Network, a community of professionals
              who share an interest in the deep-tech ecosystem.
            </p>
          </article>

          <article class="card journey-card">
            <span class="journey-step" aria-hidden="true">2</span>
            <h2 class="journey-title">What we do</h2>
            <p>
              Our purpose is to help Wharton alumni turn their business expertise into something
              valuable in the world of AI: launching new projects and startups alongside AI
              professionals and student founders, taking part in AI Studio think tanks, and
              exchanging on how the knowledge of Wharton alumni can move the deep-tech field forward.
            </p>
            <p>
              Our discussions span fintech, supply chain, marketing, and product design, and we have
              developed domain-specific approaches to applied AI. Collaborative ventures have been
              supported through government grants and the Wharton Alumni Angels.
            </p>
          </article>

          <article class="card journey-card">
            <span class="journey-step" aria-hidden="true">3</span>
            <h2 class="journey-title">Where we're going</h2>
            <p>
              AI Studio wants to go a step further — bringing tech experts, industry professionals,
              investors, entrepreneurs, and future leaders closer together to exchange knowledge,
              capabilities, skills, and ideas about how to disrupt today's business models. We want
              to be a driving force of change, and we believe we can do that by deepening the
              connections between like-minded alumni.
            </p>
          </article>
        </div>

        <p class="journey-note">
          WAAIS was founded by George Gvishiani, founder of the Wharton Alumni AI Studio and
          co-founder of the Wharton AI Studio Expert Network.
        </p>
      </div>
    </section>

    <section v-if="founders.length > 0" class="section paper about-team">
      <div class="section-inner">
        <div class="section-head">
          <div><h2>Founders</h2></div>
          <RouterLink v-if="advisors.length > 0" class="button water" to="/advisors">Board advisors</RouterLink>
        </div>
        <div class="grid founders-grid">
          <article v-for="member in founders" :key="member.id" class="card info-card team-card">
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
        </div>
      </div>
    </section>

    <section class="section navy-band">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <h2>Join the community</h2>
            <p class="lede">If you'd like to be part of what we're building, we'd love to hear from you.</p>
          </div>
          <RouterLink class="button primary" to="/membership">Become a member</RouterLink>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
