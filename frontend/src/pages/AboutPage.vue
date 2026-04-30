<script setup>
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="About" title="A Wharton alumni community for applied AI." lede="WAAIS connects alumni across startups, enterprise, research, investing, and public impact." />
    <section class="section paper">
      <div class="section-inner prose">
        <h2>Designed for repeat collaboration.</h2>
        <p>The community helps members learn faster, find collaborators, support alumni startups, and build durable relationships beyond one-off events.</p>
        <p>Team members will be able to edit their own profile details, while public content is managed by admins and super admins.</p>
      </div>
    </section>
  </PublicLayout>
</template>
