<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

const pages = {
  'sign-in': {
    eyebrow: 'Member access',
    title: 'Sign in to WAAIS.',
    lede: 'Google verifies the account first. WAAIS then routes approved members, pending users, admins, and super admins.',
    primary: 'Continue with Google',
  },
  pending: {
    eyebrow: 'Registration received',
    title: 'Your account is awaiting approval.',
    lede: 'Pending users are not shown in the member directory or forums, including private forum spaces.',
    primary: 'Contact support',
  },
  dashboard: {
    eyebrow: 'Member dashboard',
    title: 'A compact operating home for members.',
    lede: 'Member dashboard pages will cover profile, events, forum feed, and notification surfaces once APIs exist.',
    primary: 'Browse events',
  },
  admin: {
    eyebrow: 'Admin dashboard',
    title: 'Manage approvals, users, content, and announcements.',
    lede: 'Admin screens remain frontend-only until Laravel auth, permissions, persistence, audit logs, and email are implemented.',
    primary: 'Review approvals',
  },
}

const page = computed(() => pages[route.params.view] || pages['sign-in'])

const nav = [
  ['sign-in', 'Sign in'],
  ['pending', 'Pending'],
  ['dashboard', 'Member dashboard'],
  ['admin', 'Admin dashboard'],
]
</script>

<template>
  <div class="app-shell">
    <aside class="app-sidebar">
      <RouterLink class="brand" to="/">
        <span class="brand-mark">WA</span>
        <span>
          <strong>WAAIS App</strong>
          <small>Frontend mockup only</small>
        </span>
      </RouterLink>
      <nav class="app-nav" aria-label="App mockup pages">
        <RouterLink v-for="[id, label] in nav" :key="id" :to="`/app/${id}`">{{ label }}</RouterLink>
      </nav>
      <RouterLink class="button secondary" to="/">Public site</RouterLink>
    </aside>

    <main class="app-canvas">
      <section class="app-hero">
        <p class="eyebrow">{{ page.eyebrow }}</p>
        <h1>{{ page.title }}</h1>
        <p class="lede">{{ page.lede }}</p>
        <button class="button primary" type="button">{{ page.primary }}</button>
      </section>

      <section class="grid app-grid">
        <article class="card">
          <span class="tag">Roles</span>
          <h3>Separate approval status from permission role.</h3>
          <p>Backend implementation should model pending/approved/rejected separately from member, student, partner_guest, admin, and super_admin.</p>
        </article>
        <article class="card">
          <span class="tag">Super admins</span>
          <h3>Only super admins manage admin privileges.</h3>
          <p>George plus at most two designated super admins can promote users to admin or remove admin access.</p>
        </article>
        <article class="card">
          <span class="tag">Notifications</span>
          <h3>Email and dashboard notifications are API work.</h3>
          <p>Application, approval, request-more-info, event reminders, and announcements need Laravel-backed delivery.</p>
        </article>
      </section>
    </main>
  </div>
</template>
