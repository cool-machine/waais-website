<script setup>
import CardGrid from '../components/CardGrid.vue'
import InfoCard from '../components/InfoCard.vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { forumIndustries, forumRegions, forumTopics } from '../data/forum'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Forum" title="Discourse will live at forum.whartonai.studio." lede="This internal preview stays in Vue until Discourse is installed and connected through Laravel SSO." />
    <section class="section paper">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <p class="eyebrow">Industry first</p>
            <h2>Initial forum directions, with regions secondary.</h2>
          </div>
        </div>
        <h3>Industry categories</h3>
        <div class="tag-list">
          <span v-for="industry in forumIndustries" :key="industry" class="tag">{{ industry }}</span>
        </div>
        <h3 style="margin-top: 24px">Region categories</h3>
        <div class="tag-list">
          <span v-for="region in forumRegions" :key="region" class="tag">{{ region }}</span>
        </div>
        <CardGrid>
          <InfoCard v-for="topic in forumTopics" :key="topic.id" :title="topic.title" :eyebrow="topic.category" :meta="topic.visibility">
            Public teasers will be curated or approved by admins. Approved members see all member forums.
          </InfoCard>
        </CardGrid>
        <div class="lock-box" style="margin-top: 20px">
          <div>
            <span class="tag">Future URL</span>
            <h2>Forum access opens after approval.</h2>
            <p>Discourse will live at forum.whartonai.studio. The Vue route exists only as a preview until Discourse is installed.</p>
          </div>
          <a class="button water" href="https://forum.whartonai.studio">Future forum URL</a>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
