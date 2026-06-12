<script setup>
import { RouterLink } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { forumIndustries, forumRegions } from '../data/forum'
</script>

<template>
  <PublicLayout>
    <PageHero
      eyebrow="Forum"
      title="The WAAIS forum is live."
      lede="A space for AI builders inside the Wharton alumni community. Guests can browse public discussions; approved members sign in with their WAAIS account to post — no separate forum password."
    />
    <section class="section paper">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <p class="eyebrow">How it works</p>
            <h2>One account for site and forum.</h2>
            <p>
              Approved WAAIS members sign in to the forum with their existing site account
              (password or Google) through single sign-on. Not a member yet? Apply now —
              once approved, the forum unlocks automatically.
            </p>
          </div>
          <div class="row">
            <a class="button primary" href="https://forum.whartonai.studio">Open the forum</a>
            <RouterLink class="button water" to="/membership">Apply for membership</RouterLink>
          </div>
        </div>

        <h3 style="margin-top: 32px">Industry categories (rolling out)</h3>
        <div class="tag-list">
          <span v-for="industry in forumIndustries" :key="industry" class="tag">{{ industry }}</span>
        </div>

        <h3 style="margin-top: 24px">Region categories (rolling out)</h3>
        <div class="tag-list">
          <span v-for="region in forumRegions" :key="region" class="tag">{{ region }}</span>
        </div>

        <div class="lock-box" style="margin-top: 32px">
          <div>
            <span class="tag">Bookmark it</span>
            <h2>Forum URL: forum.whartonai.studio</h2>
            <p>
              The forum lives at <a href="https://forum.whartonai.studio"><code>forum.whartonai.studio</code></a>.
              Browsing is open to everyone; posting requires an approved WAAIS membership.
            </p>
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
