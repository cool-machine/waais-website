<script setup>
import { RouterLink } from 'vue-router'

// Inline nav links. "Become a member" is intentionally NOT in this list
// because it's already the right-side CTA — duplicating it caused the nav
// row to overflow into the CTA on narrower viewports. "Legal" is in the
// footer only for the same reason.
const links = [
  { label: 'Home', to: '/' },
  { label: 'Events', to: '/events' },
  { label: 'Startups', to: '/startups' },
  { label: 'About', to: '/about' },
  { label: 'Partners', to: '/partners' },
  { label: 'Forum', to: '/forum' },
  { label: 'Contact', to: '/contact' },
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
            <small>AI affinity group</small>
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
          An AI affinity group for the global Wharton alumni community.
        </p>
      </div>
      <RouterLink to="/legal">Privacy, cookies, and GDPR</RouterLink>
    </footer>
  </div>
</template>
