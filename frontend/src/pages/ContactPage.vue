<script setup>
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Contact" title="Reach the WAAIS team." lede="Contact forms will later route through Laravel and email notifications." />
    <section class="section paper">
      <div class="section-inner">
        <form class="application-form">
          <label>Name<input placeholder="Your name" /></label>
          <label>Email<input placeholder="you@example.com" /></label>
          <label>Topic<select><option>Membership</option><option>Events</option><option>Partnership</option><option>Support</option></select></label>
          <label class="full">Message<textarea placeholder="How can we help?" /></label>
          <button class="button primary" type="button">Send message</button>
        </form>
      </div>
    </section>
  </PublicLayout>
</template>
