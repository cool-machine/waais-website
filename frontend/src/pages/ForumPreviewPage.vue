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
      title="The WAAIS forum is coming soon."
      lede="A members-only space for AI builders inside the Wharton alumni community — industry-first categories, regional sub-rooms, with public teasers curated by admins. We're shipping the site, member directory, events, and startup directory first, then opening the forum once we have the community to fill it."
    />
    <section class="section paper">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <p class="eyebrow">When it lands</p>
            <h2>Members get in first.</h2>
            <p>
              When the forum opens, approved WAAIS members will be able to sign in via the
              site's existing Google login — no separate forum account, no separate password.
              If you're not yet a member, the fastest way to be ready is to apply now.
            </p>
          </div>
          <RouterLink class="button primary" to="/membership">Apply for membership</RouterLink>
        </div>

        <h3 style="margin-top: 32px">Planned industry categories</h3>
        <div class="tag-list">
          <span v-for="industry in forumIndustries" :key="industry" class="tag">{{ industry }}</span>
        </div>

        <h3 style="margin-top: 24px">Planned region categories</h3>
        <div class="tag-list">
          <span v-for="region in forumRegions" :key="region" class="tag">{{ region }}</span>
        </div>

        <div class="lock-box" style="margin-top: 32px">
          <div>
            <span class="tag">Heads-up</span>
            <h2>Forum URL: forum.whartonai.studio</h2>
            <p>
              The forum will live at <code>forum.whartonai.studio</code> when it ships.
              That subdomain is reserved but not yet active — bookmarking it today won't resolve.
            </p>
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
