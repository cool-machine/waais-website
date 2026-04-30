<script setup>
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Legal" title="Privacy, cookies, and GDPR requests." lede="Final legal copy will be added before launch." />
    <section class="section paper">
      <div class="section-inner grid">
        <article class="card"><h3>Privacy Policy</h3><p>Placeholder for final privacy policy.</p></article>
        <article class="card"><h3>Cookie Policy</h3><p>Placeholder for final cookie policy.</p></article>
        <article class="card"><h3>GDPR Request</h3><p>Placeholder for data access/deletion request flow.</p></article>
      </div>
    </section>
  </PublicLayout>
</template>
