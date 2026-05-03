<script setup>
import { RouterLink } from 'vue-router'

const links = [
  { label: 'Home', to: '/' },
  { label: 'Events', to: '/events' },
  { label: 'Startups', to: '/startups' },
  { label: 'About', to: '/about' },
  { label: 'Partners', to: '/partners' },
  { label: 'Become a member', to: '/membership' },
  { label: 'Forum', to: '/forum' },
  { label: 'Contact', to: '/contact' },
  { label: 'Legal', to: '/legal' },
]
</script>

<template>
  <div class="site-shell">
    <header class="topbar">
      <div class="topbar-inner">
        <RouterLink class="brand brand--logo" to="/">
          <img
            class="brand-logo brand-logo--mark"
            src="/brand/waais-mark.svg"
            alt="Wharton Alumni AI Studio"
            width="100"
            height="100"
          />
          <span class="brand-text">
            <strong>Wharton Alumni AI Studio</strong>
            <small>AI affinity group, sanctioned by WAC United Kingdom</small>
          </span>
        </RouterLink>

        <nav class="main-nav" aria-label="Public pages">
          <RouterLink v-for="link in links" :key="link.to" :to="link.to">
            {{ link.label }}
          </RouterLink>
        </nav>

        <div class="actions">
          <RouterLink class="button secondary" to="/membership">Become a member</RouterLink>
          <RouterLink class="button primary" to="/app/sign-in">Member sign in</RouterLink>
        </div>
      </div>
    </header>

    <main>
      <slot />
    </main>

    <footer class="footer">
      <div>
        <strong>Wharton Alumni AI Studio</strong>
        <p class="footer-affiliation">
          An AI-focused affinity group within the Wharton Alumni Club United Kingdom,
          built for the global Wharton alumni community.
        </p>
      </div>
      <RouterLink to="/legal">Privacy, cookies, and GDPR</RouterLink>
    </footer>
  </div>
</template>
