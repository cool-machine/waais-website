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
        <RouterLink class="brand" to="/">
          <span class="brand-mark">WA</span>
          <span>
            <strong>Wharton Alumni AI Studio</strong>
            <small>AI community for Wharton alumni</small>
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
        <p>Static Vue frontend scaffold. Backend, auth, CMS, and Discourse SSO come next.</p>
      </div>
      <RouterLink to="/legal">Privacy, cookies, and GDPR</RouterLink>
    </footer>
  </div>
</template>
