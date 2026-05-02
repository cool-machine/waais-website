<script setup>
import { computed, reactive, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  approvalStatuses,
  affiliationTypes,
  contentStatuses,
  contentVisibilities,
  permissionRoles,
} from '../data/platformModel'
import { useAuthUserStore } from '../stores/authUser'
import { useAdminEventsStore } from '../stores/adminEvents'
import { useAdminMembershipApplicationsStore } from '../stores/adminMembershipApplications'
import { useAdminStartupListingsStore } from '../stores/adminStartupListings'
import { useAdminUsersStore } from '../stores/adminUsers'
import { useMembershipApplicationStore } from '../stores/membershipApplication'
import { useMyStartupsStore } from '../stores/myStartups'

const route = useRoute()
const authUser = useAuthUserStore()
const adminApplicationsStore = useAdminMembershipApplicationsStore()
const adminStartupListingsStore = useAdminStartupListingsStore()
const adminEventsStore = useAdminEventsStore()
const adminUsersStore = useAdminUsersStore()
const applicationStore = useMembershipApplicationStore()
const myStartupsStore = useMyStartupsStore()

const startupForm = reactive({
  name: '',
  tagline: '',
  description: '',
  website_url: '',
  logo_url: '',
  industry: '',
  stage: '',
  location: '',
  founders: '',
  submitter_role: '',
  linkedin_url: '',
})
const adminReviewForm = reactive({
  review_notes: '',
  send_email: false,
})
const adminStartupReviewForm = reactive({
  review_notes: '',
  send_email: false,
})
const eventForm = reactive({
  title: '',
  summary: '',
  description: '',
  starts_at: '',
  ends_at: '',
  location: '',
  format: '',
  image_url: '',
  registration_url: '',
  capacity_limit: '',
  waitlist_open: false,
  visibility: 'members_only',
  recap_content: '',
  reminder_days_before: '2',
})
const eventCancelForm = reactive({
  cancellation_note: '',
})
const userFilterForm = reactive({
  permission_role: 'all',
  approval_status: 'all',
  q: '',
})
const emailSignInForm = reactive({
  email: '',
})

const navGroups = [
  {
    label: 'Auth',
    items: [
      ['sign-in', 'Sign in'],
      ['pending', 'Pending approval'],
    ],
  },
  {
    label: 'Member dashboard',
    items: [
      ['dashboard', 'Overview'],
      ['profile', 'Profile'],
      ['my-startups', 'My startups'],
      ['my-events', 'My events'],
      ['forum-feed', 'Forum feed'],
    ],
  },
  {
    label: 'Admin dashboard',
    items: [
      ['admin', 'Admin overview'],
      ['approvals', 'Approvals'],
      ['startup-review', 'Startup review'],
      ['users', 'User management'],
      ['events-admin', 'Event management'],
      ['content-admin', 'Public content'],
      ['announcements', 'Announcements'],
    ],
  },
]

const currentView = computed(() => route.params.view || 'sign-in')
const visibleNavGroups = computed(() => {
  if (!authUser.isAuthenticated) {
    return navGroups
  }

  return navGroups.filter((group) => group.label !== 'Auth')
})

const displayName = computed(() => authUser.user?.name || authUser.user?.email || 'member')
const accountStatusLabel = computed(() => {
  if (!authUser.initialized || authUser.loading) return 'Checking session'
  if (!authUser.user) return 'Signed out'
  if (authUser.canAccessMemberAreas) return 'Approved member access'
  if (authUser.isPending) return 'Pending admin approval'
  return authUser.user.approval_status || 'Account created'
})
const applicationStatusLabel = computed(() => {
  if (applicationStore.loading) return 'Loading application'
  const status = applicationStore.status
  return status ? titleize(status) : 'No application on file'
})
const dashboardMetrics = computed(() => [
  ['Account status', accountStatusLabel.value],
  ['Application', applicationStatusLabel.value],
  ['Affiliation', titleize(authUser.user?.affiliation_type) || 'Not set'],
  ['Role', titleize(authUser.user?.permission_role) || 'Signed out'],
])
const profileRows = computed(() => [
  ['Name', authUser.user?.name || fullName(applicationStore.application) || 'Not provided'],
  ['Email', authUser.user?.email || applicationStore.application?.email || 'Not provided'],
  ['Affiliation', titleize(applicationStore.application?.affiliation_type || authUser.user?.affiliation_type) || 'Not provided'],
  ['School affiliation', applicationStore.application?.school_affiliation || 'Not provided'],
  ['Graduation year', applicationStore.application?.graduation_year || 'Not provided'],
  ['Location', applicationStore.application?.primary_location || 'Not provided'],
])
const applicationSummaryRows = computed(() => [
  ['Application status', applicationStatusLabel.value],
  ['Experience', applicationStore.application?.experience_summary || 'Not provided'],
  ['Expertise', applicationStore.application?.expertise_summary || 'Not provided'],
  ['Availability', applicationStore.application?.availability || 'Not provided'],
])
const canEditApplication = computed(() => authUser.isAuthenticated && applicationStore.canEdit)
const selectedStartupStatus = computed(() => titleize(myStartupsStore.currentListing?.approval_status) || 'New listing')
const startupSaveLabel = computed(() => {
  if (myStartupsStore.saving) return 'Saving...'
  if (myStartupsStore.currentListing?.id) return 'Update listing'
  return 'Submit listing'
})
const startupValidationErrors = computed(() => myStartupsStore.saveError?.body?.errors ?? {})
const canSaveStartup = computed(() => (
  authUser.canAccessMemberAreas
  && myStartupsStore.canEditCurrent
  && !myStartupsStore.saving
))
const canAccessAdminDashboard = computed(() => authUser.canPublishPublicContent)
const selectedApplication = computed(() => adminApplicationsStore.currentApplication)
const selectedApplicationRows = computed(() => {
  const application = selectedApplication.value
  return [
    ['Affiliation', titleize(application?.affiliation_type) || 'Not provided'],
    ['School', application?.school_affiliation || 'Not provided'],
    ['Graduation year', application?.graduation_year || 'Not provided'],
    ['Location', [application?.primary_location, application?.secondary_location].filter(Boolean).join(' / ') || 'Not provided'],
    ['LinkedIn', application?.linkedin_url || 'Not provided'],
    ['Availability', application?.availability || 'Not provided'],
  ]
})
const adminValidationErrors = computed(() => adminApplicationsStore.saveError?.body?.errors ?? {})
const adminQueueCount = computed(() => adminApplicationsStore.listMeta.total || adminApplicationsStore.list.length)
const selectedAdminStartupListing = computed(() => adminStartupListingsStore.currentListing)
const selectedAdminStartupRows = computed(() => {
  const listing = selectedAdminStartupListing.value
  return [
    ['Owner', listing?.owner?.name || listing?.owner?.email || 'Not provided'],
    ['Industry', listing?.industry || 'Not provided'],
    ['Stage', listing?.stage || 'Not provided'],
    ['Location', listing?.location || 'Not provided'],
    ['Founders', arrayToList(listing?.founders) || 'Not provided'],
    ['Submitter role', listing?.submitter_role || 'Not provided'],
    ['Content status', titleize(listing?.content_status) || 'Not provided'],
  ]
})
const adminStartupValidationErrors = computed(() => adminStartupListingsStore.saveError?.body?.errors ?? {})
const adminStartupQueueCount = computed(() => adminStartupListingsStore.listMeta.total || adminStartupListingsStore.list.length)
const selectedAdminEvent = computed(() => adminEventsStore.currentEvent)
const adminEventValidationErrors = computed(() => adminEventsStore.saveError?.body?.errors ?? {})
const adminEventQueueCount = computed(() => adminEventsStore.listMeta.total || adminEventsStore.list.length)
const adminEventSaveLabel = computed(() => {
  if (adminEventsStore.saving) return 'Saving...'
  return adminEventsStore.isCreatingNew ? 'Create draft event' : 'Save changes'
})
const adminEventStatusFilters = ['all', 'draft', 'pending_review', 'published', 'hidden', 'archived']
const adminUserRoleFilters = ['all', ...permissionRoles]
const adminUserApprovalFilters = ['all', ...approvalStatuses]
const selectedAdminUser = computed(() => adminUsersStore.currentUser)
const adminUserSaveError = computed(() => adminUsersStore.saveError?.body?.message
  || (adminUsersStore.saveError ? 'Could not update this user.' : null))
const adminUserCount = computed(() => adminUsersStore.listMeta.total || adminUsersStore.list.length)
const canManageAdminPrivileges = computed(() => Boolean(authUser.user?.can_manage_admin_privileges))
const selectedAdminUserRows = computed(() => {
  const user = selectedAdminUser.value
  return [
    ['Email', user?.email || 'Not provided'],
    ['Approval', titleize(user?.approval_status) || 'Not set'],
    ['Affiliation', titleize(user?.affiliation_type) || 'Not set'],
    ['Role', titleize(user?.permission_role) || 'Not set'],
    ['Email verified', user?.email_verified_at ? formatEventDateTime(user.email_verified_at) : 'No'],
    ['Member since', user?.created_at ? formatEventDateTime(user.created_at) : 'Unknown'],
  ]
})
const isCurrentAuthUser = computed(() => Boolean(
  selectedAdminUser.value?.id && authUser.user?.id && selectedAdminUser.value.id === authUser.user.id
))
const selectedAdminEventRows = computed(() => {
  const event = selectedAdminEvent.value
  return [
    ['Status', titleize(event?.content_status) || 'Draft'],
    ['Visibility', titleize(event?.visibility) || 'Members only'],
    ['Starts', formatEventDateTime(event?.starts_at) || 'Not set'],
    ['Ends', formatEventDateTime(event?.ends_at) || 'Not set'],
    ['Location', event?.location || 'Not provided'],
    ['Format', event?.format || 'Not provided'],
    ['Capacity', event?.capacity_limit ? String(event.capacity_limit) : 'Unlimited'],
    ['Cancelled', event?.cancelled_at ? formatEventDateTime(event.cancelled_at) : 'No'],
  ]
})

function titleize(value) {
  return value
    ? String(value)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase())
    : ''
}

function fullName(application) {
  const parts = [application?.first_name, application?.last_name].filter(Boolean)
  return parts.join(' ')
}

function arrayToList(value) {
  return Array.isArray(value) ? value.join(', ') : ''
}

function listToArray(value) {
  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
}

function nullableString(value) {
  return value.trim() === '' ? null : value.trim()
}

function toLocalDateTimeInput(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function localDateTimeToIso(value) {
  if (!value) return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return date.toISOString()
}

function formatEventDateTime(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString()
}

function nullableInteger(value) {
  if (value === '' || value === null || value === undefined) return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? Math.trunc(parsed) : null
}

function populateStartupForm(listing) {
  const source = listing ?? {}
  startupForm.name = source.name ?? ''
  startupForm.tagline = source.tagline ?? ''
  startupForm.description = source.description ?? ''
  startupForm.website_url = source.website_url ?? ''
  startupForm.logo_url = source.logo_url ?? ''
  startupForm.industry = source.industry ?? ''
  startupForm.stage = source.stage ?? ''
  startupForm.location = source.location ?? ''
  startupForm.founders = arrayToList(source.founders)
  startupForm.submitter_role = source.submitter_role ?? ''
  startupForm.linkedin_url = source.linkedin_url ?? ''
}

function populateAdminReviewForm(application) {
  adminReviewForm.review_notes = application?.review_notes ?? ''
  adminReviewForm.send_email = false
}

function populateAdminStartupReviewForm(listing) {
  adminStartupReviewForm.review_notes = listing?.review_notes ?? ''
  adminStartupReviewForm.send_email = false
}

function startupPayload() {
  return {
    name: startupForm.name.trim(),
    tagline: startupForm.tagline.trim(),
    description: startupForm.description.trim(),
    website_url: nullableString(startupForm.website_url),
    logo_url: nullableString(startupForm.logo_url),
    industry: startupForm.industry.trim(),
    stage: nullableString(startupForm.stage),
    location: nullableString(startupForm.location),
    founders: listToArray(startupForm.founders),
    submitter_role: nullableString(startupForm.submitter_role),
    linkedin_url: nullableString(startupForm.linkedin_url),
  }
}

function selectStartup(listing) {
  myStartupsStore.selectListing(listing)
  populateStartupForm(listing)
}

function startNewStartup() {
  myStartupsStore.startNew()
  populateStartupForm(null)
}

async function submitStartup() {
  const saved = await myStartupsStore.save(startupPayload())
  populateStartupForm(saved)
}

async function selectApplication(application) {
  adminApplicationsStore.selectApplication(application)
  populateAdminReviewForm(application)
  await adminApplicationsStore.loadOne(application.id)
  populateAdminReviewForm(adminApplicationsStore.currentApplication)
}

async function loadAdminApplications(status = adminApplicationsStore.listStatus) {
  await adminApplicationsStore.loadList({ status, force: true })
  populateAdminReviewForm(adminApplicationsStore.currentApplication)
}

async function approveApplication() {
  await adminApplicationsStore.approve(adminReviewForm.review_notes)
  populateAdminReviewForm(adminApplicationsStore.currentApplication)
}

async function rejectApplication() {
  await adminApplicationsStore.reject(adminReviewForm.review_notes, adminReviewForm.send_email)
  populateAdminReviewForm(adminApplicationsStore.currentApplication)
}

async function requestApplicationInfo() {
  await adminApplicationsStore.requestInfo(adminReviewForm.review_notes)
  populateAdminReviewForm(adminApplicationsStore.currentApplication)
}

async function selectAdminStartupListing(listing) {
  adminStartupListingsStore.selectListing(listing)
  populateAdminStartupReviewForm(listing)
  await adminStartupListingsStore.loadOne(listing.id)
  populateAdminStartupReviewForm(adminStartupListingsStore.currentListing)
}

async function loadAdminStartupListings(status = adminStartupListingsStore.listStatus) {
  await adminStartupListingsStore.loadList({ status, force: true })
  populateAdminStartupReviewForm(adminStartupListingsStore.currentListing)
}

async function approveStartupListing() {
  await adminStartupListingsStore.approve(adminStartupReviewForm.review_notes)
  populateAdminStartupReviewForm(adminStartupListingsStore.currentListing)
}

async function rejectStartupListing() {
  await adminStartupListingsStore.reject(adminStartupReviewForm.review_notes, adminStartupReviewForm.send_email)
  populateAdminStartupReviewForm(adminStartupListingsStore.currentListing)
}

async function requestStartupListingInfo() {
  await adminStartupListingsStore.requestInfo(adminStartupReviewForm.review_notes)
  populateAdminStartupReviewForm(adminStartupListingsStore.currentListing)
}

function populateEventForm(event) {
  const source = event ?? {}
  eventForm.title = source.title ?? ''
  eventForm.summary = source.summary ?? ''
  eventForm.description = source.description ?? ''
  eventForm.starts_at = toLocalDateTimeInput(source.starts_at)
  eventForm.ends_at = toLocalDateTimeInput(source.ends_at)
  eventForm.location = source.location ?? ''
  eventForm.format = source.format ?? ''
  eventForm.image_url = source.image_url ?? ''
  eventForm.registration_url = source.registration_url ?? ''
  eventForm.capacity_limit = source.capacity_limit ?? source.capacity_limit === 0 ? String(source.capacity_limit ?? '') : ''
  eventForm.waitlist_open = Boolean(source.waitlist_open)
  eventForm.visibility = source.visibility ?? 'members_only'
  eventForm.recap_content = source.recap_content ?? ''
  eventForm.reminder_days_before = source.reminder_days_before === null || source.reminder_days_before === undefined
    ? '2'
    : String(source.reminder_days_before)
  eventCancelForm.cancellation_note = source.cancellation_note ?? ''
}

function eventPayload() {
  return {
    title: eventForm.title.trim(),
    summary: eventForm.summary.trim(),
    description: eventForm.description.trim(),
    starts_at: localDateTimeToIso(eventForm.starts_at),
    ends_at: localDateTimeToIso(eventForm.ends_at),
    location: nullableString(eventForm.location),
    format: nullableString(eventForm.format),
    image_url: nullableString(eventForm.image_url),
    registration_url: nullableString(eventForm.registration_url),
    capacity_limit: nullableInteger(eventForm.capacity_limit),
    waitlist_open: Boolean(eventForm.waitlist_open),
    visibility: eventForm.visibility || null,
    recap_content: nullableString(eventForm.recap_content),
    reminder_days_before: nullableInteger(eventForm.reminder_days_before),
  }
}

async function loadAdminEvents(contentStatus = adminEventsStore.listContentStatus) {
  await adminEventsStore.loadList({ contentStatus, force: true })
  populateEventForm(adminEventsStore.currentEvent)
}

async function selectAdminEvent(event) {
  adminEventsStore.selectEvent(event)
  populateEventForm(event)
  await adminEventsStore.loadOne(event.id)
  populateEventForm(adminEventsStore.currentEvent)
}

function startNewEvent() {
  adminEventsStore.startNew()
  populateEventForm(null)
}

async function submitAdminEvent() {
  const saved = await adminEventsStore.save(eventPayload())
  populateEventForm(saved ?? adminEventsStore.currentEvent)
}

async function publishAdminEvent() {
  await adminEventsStore.publish()
  populateEventForm(adminEventsStore.currentEvent)
}

async function hideAdminEvent() {
  await adminEventsStore.hide()
  populateEventForm(adminEventsStore.currentEvent)
}

async function archiveAdminEvent() {
  await adminEventsStore.archive()
  populateEventForm(adminEventsStore.currentEvent)
}

async function cancelAdminEvent() {
  await adminEventsStore.cancel(eventCancelForm.cancellation_note)
  populateEventForm(adminEventsStore.currentEvent)
}

async function loadAdminUsers(overrides = {}) {
  await adminUsersStore.loadList({
    permissionRole: overrides.permissionRole ?? userFilterForm.permission_role,
    approvalStatus: overrides.approvalStatus ?? userFilterForm.approval_status,
    q: overrides.q ?? userFilterForm.q,
    force: true,
  })
}

function selectAdminUser(user) {
  adminUsersStore.selectUser(user)
}

async function searchAdminUsers() {
  await loadAdminUsers()
}

async function setAdminUserRoleFilter(role) {
  userFilterForm.permission_role = role
  await loadAdminUsers({ permissionRole: role })
}

async function setAdminUserApprovalFilter(status) {
  userFilterForm.approval_status = status
  await loadAdminUsers({ approvalStatus: status })
}

async function promoteAdminUser() {
  if (!confirm('Promote this user to Admin?')) return
  await adminUsersStore.promoteAdmin()
}

async function demoteAdminUser() {
  if (!confirm('Demote this admin to Member?')) return
  await adminUsersStore.demoteAdmin()
}

async function promoteSuperAdminUser() {
  if (!confirm('Promote this admin to Super Admin?')) return
  await adminUsersStore.promoteSuperAdmin()
}

async function demoteSuperAdminUser() {
  if (!confirm('Demote this super admin to Admin?')) return
  await adminUsersStore.demoteSuperAdmin()
}

async function requestAppEmailLink() {
  await authUser.requestEmailSignIn(emailSignInForm.email, { next: '/app/dashboard' })
}

async function signOut() {
  await authUser.signOut()
  applicationStore.clear()
  myStartupsStore.clear()
  adminApplicationsStore.clear()
  adminStartupListingsStore.clear()
  adminEventsStore.clear()
  adminUsersStore.clear()
}

const adminMetrics = computed(() => [
  ['Member approvals', String(adminQueueCount.value || 0)],
  ['Startup reviews', String(adminStartupQueueCount.value || 0)],
  ['Events in view', String(adminEventQueueCount.value || 0)],
  ['Active members', '284'],
])

async function loadMemberDashboard() {
  await authUser.loadCurrentUser()
  const memberViews = ['dashboard', 'profile', 'my-startups']
  const adminViews = ['admin', 'approvals', 'startup-review', 'events-admin', 'users']

  if (authUser.isAuthenticated && memberViews.includes(currentView.value)) {
    await applicationStore.load().catch((error) => {
      if (error?.status !== 401 && error?.status !== 404) throw error
    })
  }
  if (authUser.canAccessMemberAreas && currentView.value === 'my-startups') {
    await myStartupsStore.loadList().catch((error) => {
      if (error?.status !== 401 && error?.status !== 403) throw error
    })
  }
  if (canAccessAdminDashboard.value && adminViews.includes(currentView.value)) {
    if (currentView.value === 'admin' || currentView.value === 'approvals') {
      await loadAdminApplications().catch((error) => {
        if (error?.status !== 401 && error?.status !== 403) throw error
      })
    }
    if (currentView.value === 'admin' || currentView.value === 'startup-review') {
      await loadAdminStartupListings().catch((error) => {
        if (error?.status !== 401 && error?.status !== 403) throw error
      })
    }
    if (currentView.value === 'admin' || currentView.value === 'events-admin') {
      await loadAdminEvents().catch((error) => {
        if (error?.status !== 401 && error?.status !== 403) throw error
      })
    }
    if (currentView.value === 'admin' || currentView.value === 'users') {
      await loadAdminUsers().catch((error) => {
        if (error?.status !== 401 && error?.status !== 403) throw error
      })
    }
  }
}

watch(() => myStartupsStore.currentListing, populateStartupForm)
watch(() => adminApplicationsStore.currentApplication, populateAdminReviewForm)
watch(() => adminStartupListingsStore.currentListing, populateAdminStartupReviewForm)
watch(() => adminEventsStore.currentEvent, populateEventForm)
watch(currentView, () => {
  loadMemberDashboard().catch(() => {})
}, { immediate: true })
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

      <div v-if="authUser.isAuthenticated" class="sidebar-account">
        <span class="status-pill">Signed in</span>
        <p>{{ authUser.user.email }}</p>
        <button
          class="button secondary"
          type="button"
          :disabled="authUser.signingOut"
          @click="signOut"
        >
          {{ authUser.signingOut ? 'Signing out...' : 'Sign out' }}
        </button>
      </div>

      <div v-for="group in visibleNavGroups" :key="group.label" class="app-nav-group">
        <p class="sidebar-label">{{ group.label }}</p>
        <nav class="app-nav" :aria-label="group.label">
          <RouterLink v-for="[id, label] in group.items" :key="id" :to="`/app/${id}`">{{ label }}</RouterLink>
        </nav>
      </div>

      <div class="sidebar-actions">
        <RouterLink class="button secondary" to="/">Public site</RouterLink>
      </div>
    </aside>

    <main class="app-canvas">
      <section v-if="currentView === 'sign-in'" class="auth-wrap">
        <div class="auth-panel">
          <p class="eyebrow">Member access</p>
          <h1>Sign in to WAAIS.</h1>
          <p class="lede">Google verifies the account first; WAAIS then checks whether the person is approved, pending, admin, or super admin.</p>
          <div class="grid three">
            <div class="metric"><span>Pending approvals</span><strong>8</strong></div>
            <div class="metric"><span>Upcoming events</span><strong>12</strong></div>
            <div class="metric"><span>Forum topics</span><strong>146</strong></div>
          </div>
        </div>
        <div class="auth-card">
          <h2>{{ authUser.isAuthenticated ? 'Signed in' : 'Sign in' }}</h2>
          <p v-if="!authUser.isAuthenticated" class="small">Choose Google OAuth now, or request a secure email sign-in link.</p>
          <p v-else class="small">You are signed in to WAAIS. Use sign out to end this browser session.</p>
          <form v-if="!authUser.isAuthenticated" class="app-email-form" @submit.prevent="requestAppEmailLink">
            <label>Email<input v-model="emailSignInForm.email" required type="email" placeholder="you@example.com" :disabled="authUser.emailLinkSending" /></label>
            <button class="button secondary" type="submit" :disabled="authUser.emailLinkSending">{{ authUser.emailLinkSending ? 'Sending...' : 'Sign in with email' }}</button>
            <p v-if="authUser.emailLinkSent" class="small">Check your email for a WAAIS sign-in link. In local development, it is written to the Laravel log.</p>
          </form>
          <div v-if="!authUser.isAuthenticated" class="button-grid">
            <button class="button primary" type="button" @click="authUser.startGoogleSignIn()">Sign in with Google</button>
          </div>
          <button v-else class="button secondary" type="button" :disabled="authUser.signingOut" @click="signOut">{{ authUser.signingOut ? 'Signing out...' : 'Sign out' }}</button>
          <div class="auth-status">
            <span class="status-pill" :class="{ pending: authUser.isPending }">{{ accountStatusLabel }}</span>
            <p v-if="authUser.user" class="small">Signed in as {{ authUser.user.email }}.</p>
            <p v-else-if="authUser.error" class="small">Could not check the current session. Try again after the backend is running.</p>
            <p v-else class="small">No active WAAIS session found in this browser.</p>
          </div>
          <RouterLink v-if="authUser.canAccessMemberAreas" class="button water" to="/app/dashboard">Open dashboard</RouterLink>
          <RouterLink v-else-if="authUser.isPending" class="button water" to="/app/pending">View pending status</RouterLink>
        </div>
      </section>

      <section v-else-if="currentView === 'pending'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Registration received</p>
          <h1>Your account is awaiting approval.</h1>
          <p class="lede">Pending users are not shown in the directory or forums, including private forums.</p>
        </div>
        <div class="grid two">
          <article class="card">
            <h2>What happens next</h2>
            <div class="timeline">
              <div class="timeline-item"><div class="timeline-node">1</div><div><h3>Google account verified</h3><p class="small">Name, email, and profile image are stored securely.</p></div></div>
              <div class="timeline-item"><div class="timeline-node">2</div><div><h3>Admin review</h3><p class="small">An admin checks affiliation, application answers, and community fit.</p></div></div>
              <div class="timeline-item"><div class="timeline-node">3</div><div><h3>Access enabled</h3><p class="small">Approved users receive dashboard and Discourse SSO access.</p></div></div>
            </div>
          </article>
          <article class="card">
            <h2>Account status</h2>
            <span class="status-pill" :class="{ pending: authUser.isPending }">{{ accountStatusLabel }}</span>
            <p v-if="authUser.user" class="small">{{ authUser.user.email }} is signed in with permission role {{ authUser.user.permission_role }}.</p>
            <p v-else-if="authUser.loading" class="small">Checking the current Sanctum session.</p>
            <p v-else class="small">Sign in with Google to create or resume a pending account.</p>
            <button class="button primary" type="button" @click="authUser.startGoogleSignIn()">Sign in with Google</button>
          </article>
        </div>
      </section>

      <section v-else-if="currentView === 'dashboard'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Member dashboard</p>
          <h1>Welcome back, {{ displayName }}.</h1>
          <p class="lede">Your WAAIS account, application status, and member access state in one place.</p>
          <p v-if="authUser.initialized && !authUser.canAccessMemberAreas" class="small">This account has not been approved for member areas yet.</p>
        </div>
        <div class="grid four">
          <div v-for="[label, value] in dashboardMetrics" :key="label" class="metric"><span>{{ label }}</span><strong>{{ value }}</strong></div>
        </div>
        <div class="grid two">
          <article class="card">
            <h2>Profile snapshot</h2>
            <div class="table">
              <div v-for="[label, value] in profileRows" :key="label" class="table-row"><span>{{ label }}</span><strong>{{ value }}</strong></div>
            </div>
            <RouterLink class="button water" to="/app/profile">View profile</RouterLink>
          </article>
          <article class="card">
            <h2>Application status</h2>
            <span class="status-pill" :class="{ pending: applicationStore.status === 'submitted' || applicationStore.needsMoreInfo }">{{ applicationStatusLabel }}</span>
            <p v-if="applicationStore.application?.review_notes" class="small">Admin note: {{ applicationStore.application.review_notes }}</p>
            <p v-else-if="applicationStore.loading" class="small">Loading the latest application record.</p>
            <p v-else-if="applicationStore.error" class="small">Could not load your membership application. Confirm the backend is running and try again.</p>
            <p v-else class="small">This status comes from the authenticated membership application endpoint.</p>
            <RouterLink v-if="canEditApplication" class="button water" to="/membership">Update application</RouterLink>
            <RouterLink v-else-if="authUser.isAuthenticated" class="button water" to="/membership">View application</RouterLink>
            <button v-else class="button primary" type="button" @click="authUser.startGoogleSignIn({ next: '/app/dashboard' })">Sign in with Google</button>
          </article>
        </div>
      </section>

      <section v-else-if="currentView === 'profile'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Member profile</p>
          <h1>{{ displayName }}.</h1>
          <p class="lede">Profile and application fields pulled from the current authenticated session and membership application record.</p>
        </div>
        <div class="grid two">
          <article class="card">
            <h2>Identity</h2>
            <div class="table">
              <div v-for="[label, value] in profileRows" :key="label" class="table-row"><span>{{ label }}</span><strong>{{ value }}</strong></div>
            </div>
          </article>
          <article class="card">
            <h2>Application answers</h2>
            <div class="table">
              <div v-for="[label, value] in applicationSummaryRows" :key="label" class="table-row"><span>{{ label }}</span><strong>{{ value }}</strong></div>
            </div>
            <RouterLink v-if="canEditApplication" class="button water" to="/membership">Edit application</RouterLink>
            <RouterLink v-else class="button water" to="/membership">Open membership page</RouterLink>
          </article>
        </div>
      </section>

      <section v-else-if="currentView === 'my-startups'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">My startups</p>
          <h1>Submit and track your startup listings.</h1>
          <p class="lede">Approved members can submit startup listings for admin review before they appear publicly.</p>
          <p v-if="authUser.initialized && !authUser.canAccessMemberAreas" class="small">Approved member access is required before you can submit startup listings.</p>
        </div>
        <div v-if="myStartupsStore.error" class="notice error-notice">
          <p class="small">Could not load your startup listings. Confirm this account has approved member access and the backend is running.</p>
        </div>
        <div class="grid two">
          <article class="card">
            <div class="row">
              <h2>Listings</h2>
              <button class="button secondary" type="button" @click="startNewStartup">New listing</button>
            </div>
            <p v-if="myStartupsStore.loading" class="small">Loading your startup listings.</p>
            <p v-else-if="!myStartupsStore.hasListings" class="small">No startup listings submitted yet.</p>
            <div v-else class="table">
              <button
                v-for="listing in myStartupsStore.list"
                :key="listing.id"
                class="table-row table-button"
                type="button"
                @click="selectStartup(listing)"
              >
                <span>{{ listing.name }}<br><small>{{ listing.tagline }}</small></span>
                <strong>{{ titleize(listing.approval_status) }}</strong>
              </button>
            </div>
          </article>

          <article v-if="authUser.initialized && !authUser.canAccessMemberAreas" class="card">
            <span class="tag">Approval required</span>
            <h2>Startup submissions open after member approval.</h2>
            <p class="small">Your account can still track membership status, but startup listings are accepted only from approved members. Once approved, this page will show the listing form.</p>
            <RouterLink class="button water" to="/app/dashboard">View account status</RouterLink>
          </article>

          <form v-else class="app-form card" @submit.prevent="submitStartup">
            <div class="full row">
              <div>
                <span class="tag">{{ selectedStartupStatus }}</span>
                <h2>{{ myStartupsStore.currentListing?.id ? 'Edit listing' : 'New listing' }}</h2>
              </div>
            </div>
            <label>Name *<input v-model="startupForm.name" required :disabled="!canSaveStartup" /></label>
            <label>Industry *<input v-model="startupForm.industry" required :disabled="!canSaveStartup" /></label>
            <label class="full">Tagline *<input v-model="startupForm.tagline" required :disabled="!canSaveStartup" /></label>
            <label>Website<input v-model="startupForm.website_url" type="url" :disabled="!canSaveStartup" /></label>
            <label>LinkedIn<input v-model="startupForm.linkedin_url" type="url" :disabled="!canSaveStartup" /></label>
            <label>Stage<input v-model="startupForm.stage" placeholder="Seed, growth, public, etc." :disabled="!canSaveStartup" /></label>
            <label>Location<input v-model="startupForm.location" :disabled="!canSaveStartup" /></label>
            <label class="full">Founders<input v-model="startupForm.founders" placeholder="Comma-separated names" :disabled="!canSaveStartup" /></label>
            <label class="full">Your role<input v-model="startupForm.submitter_role" placeholder="Founder, investor, operator, advisor..." :disabled="!canSaveStartup" /></label>
            <label class="full">Description *<textarea v-model="startupForm.description" required :disabled="!canSaveStartup" /></label>
            <label class="full">Logo URL<input v-model="startupForm.logo_url" type="url" :disabled="!canSaveStartup" /></label>

            <div v-if="Object.keys(startupValidationErrors).length" class="notice error-notice full">
              <p v-for="(messages, field) in startupValidationErrors" :key="field" class="small">{{ messages[0] }}</p>
            </div>

            <div class="row full">
              <button class="button primary" type="submit" :disabled="!canSaveStartup">{{ startupSaveLabel }}</button>
              <button class="button secondary" type="button" @click="startNewStartup">Clear</button>
            </div>
            <p v-if="myStartupsStore.currentListing?.approval_status === 'approved'" class="small full">Approved listings cannot be edited here. Ask an admin if the public listing needs a change.</p>
          </form>
        </div>
      </section>

      <section v-else-if="currentView === 'my-events'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">My events</p>
          <h1>Registered events and recommended sessions.</h1>
          <p class="lede">This view previews registrations, waitlists, reminders, tickets, and past event artifacts.</p>
        </div>
        <article class="card">
          <div class="table">
            <div class="table-row"><span>AI Founder Salon</span><strong>Confirmed · May 14</strong></div>
            <div class="table-row"><span>Demo Night</span><strong>Waitlist · Jun 05</strong></div>
          </div>
        </article>
      </section>

      <section v-else-if="currentView === 'forum-feed'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Forum feed</p>
          <h1>Recent discussion from Discourse.</h1>
          <p class="lede">Full posting and moderation will remain in Discourse at forum.whartonai.studio.</p>
        </div>
        <article class="card">
          <div class="table">
            <div class="table-row"><span>How are teams pricing internal AI agents?</span><strong>18 replies</strong></div>
            <div class="table-row"><span>Founders raising in Q2</span><strong>9 replies</strong></div>
            <div class="table-row"><span>Evaluation tools for regulated workflows</span><strong>22 replies</strong></div>
          </div>
        </article>
      </section>

      <section v-else-if="currentView === 'admin'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Admin dashboard</p>
          <h1>Manage approvals, public content, members, and announcements.</h1>
          <p class="lede">Admins control approvals, events, startups, partners, homepage cards, announcements, and moderation shortcuts. Super admins can override and manage admin privileges.</p>
        </div>
        <div class="grid four">
          <div v-for="[label, value] in adminMetrics" :key="label" class="metric"><span>{{ label }}</span><strong>{{ value }}</strong></div>
        </div>
        <div class="grid two">
          <article class="card">
            <h2>Operational queue</h2>
            <div class="table">
              <div class="table-row"><span>Review member applications</span><strong>{{ adminQueueCount }}</strong></div>
              <div class="table-row"><span>Review startup listings</span><strong>{{ adminStartupQueueCount }}</strong></div>
              <div class="table-row"><span>Publish Demo Night</span><strong>Draft</strong></div>
            </div>
          </article>
          <article class="card">
            <h2>Quick actions</h2>
            <div class="button-grid">
              <RouterLink class="button water" to="/app/approvals">Review members</RouterLink>
              <RouterLink class="button water" to="/app/startup-review">Review startups</RouterLink>
              <RouterLink class="button water" to="/app/events-admin">Create event</RouterLink>
              <RouterLink class="button water" to="/app/announcements">Create announcement</RouterLink>
              <button class="button secondary" type="button">Open Discourse admin</button>
            </div>
          </article>
        </div>
        <article class="card">
          <h2>Model contract</h2>
          <p class="small">Laravel should store approval status, affiliation type, and permission role separately.</p>
          <div class="model-columns">
            <div>
              <h3>Approval status</h3>
              <div class="tag-row"><span v-for="status in approvalStatuses" :key="status" class="tag">{{ status }}</span></div>
            </div>
            <div>
              <h3>Affiliation type</h3>
              <div class="tag-row"><span v-for="type in affiliationTypes" :key="type" class="tag">{{ type }}</span></div>
            </div>
            <div>
              <h3>Permission role</h3>
              <div class="tag-row"><span v-for="role in permissionRoles" :key="role" class="tag">{{ role }}</span></div>
            </div>
          </div>
        </article>
      </section>

      <section v-else-if="currentView === 'approvals'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Approvals queue</p>
          <h1>Review new member applications.</h1>
          <p class="lede">Membership applications come from the authenticated admin API and use the same approve, request-more-info, and reject transitions as the backend.</p>
          <p v-if="authUser.initialized && !canAccessAdminDashboard" class="small">Approved admin access is required for this queue.</p>
        </div>
        <div v-if="adminApplicationsStore.error" class="notice error-notice">
          <p class="small">Could not load membership applications. Confirm this account has admin access and the backend is running.</p>
        </div>
        <div v-if="canAccessAdminDashboard" class="filter-row">
          <button
            v-for="status in ['submitted', 'needs_more_info', 'approved', 'rejected']"
            :key="status"
            class="button secondary"
            :class="{ active: adminApplicationsStore.listStatus === status }"
            type="button"
            @click="loadAdminApplications(status)"
          >
            {{ titleize(status) }}
          </button>
        </div>
        <div v-if="canAccessAdminDashboard" class="grid two">
          <article class="card">
            <div class="row">
              <h2>Applications</h2>
              <button class="button secondary" type="button" @click="loadAdminApplications(adminApplicationsStore.listStatus)">Refresh</button>
            </div>
            <p v-if="adminApplicationsStore.loading" class="small">Loading membership applications.</p>
            <p v-else-if="!adminApplicationsStore.hasApplications" class="small">No applications in this status.</p>
            <div v-else class="table">
              <button
                v-for="application in adminApplicationsStore.list"
                :key="application.id"
                class="table-row table-button"
                type="button"
                @click="selectApplication(application)"
              >
                <span>
                  {{ fullName(application) || application.applicant?.name || application.email }}
                  <br>
                  <small>{{ application.email }}</small>
                </span>
                <strong>{{ titleize(application.approval_status) }}</strong>
              </button>
            </div>
          </article>

          <article v-if="!selectedApplication" class="card">
            <span class="tag">No selection</span>
            <h2>Select an application.</h2>
            <p class="small">Choose a row from the queue to view the applicant profile and review actions.</p>
          </article>

          <form v-else class="app-form card" @submit.prevent="approveApplication">
            <div class="full row">
              <div>
                <span class="tag">{{ titleize(selectedApplication.approval_status) }}</span>
                <h2>{{ adminApplicationsStore.selectedApplicantName }}</h2>
                <p class="small">{{ selectedApplication.email }}</p>
              </div>
            </div>

            <div class="table full">
              <div v-for="[label, value] in selectedApplicationRows" :key="label" class="table-row"><span>{{ label }}</span><strong>{{ value }}</strong></div>
            </div>

            <label class="full">Experience<textarea :value="selectedApplication.experience_summary || 'Not provided'" readonly /></label>
            <label class="full">Expertise<textarea :value="selectedApplication.expertise_summary || 'Not provided'" readonly /></label>
            <label class="full">Review notes<textarea v-model="adminReviewForm.review_notes" :disabled="adminApplicationsStore.saving" /></label>
            <label class="checkbox-row full"><input v-model="adminReviewForm.send_email" type="checkbox" :disabled="adminApplicationsStore.saving" /> Send rejection email</label>

            <div v-if="Object.keys(adminValidationErrors).length" class="notice error-notice full">
              <p v-for="(messages, field) in adminValidationErrors" :key="field" class="small">{{ messages[0] }}</p>
            </div>

            <div class="button-grid full">
              <button class="button primary" type="submit" :disabled="adminApplicationsStore.saving">Approve</button>
              <button class="button water" type="button" :disabled="adminApplicationsStore.saving" @click="requestApplicationInfo">Request info</button>
              <button class="button secondary" type="button" :disabled="adminApplicationsStore.saving" @click="rejectApplication">Reject</button>
            </div>
            <p v-if="adminApplicationsStore.currentLoading" class="small full">Loading full application detail.</p>
          </form>
        </div>
      </section>

      <section v-else-if="currentView === 'startup-review'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Startup review</p>
          <h1>Review submitted startup listings.</h1>
          <p class="lede">Startup listings come from the authenticated admin API and use the same approve, request-more-info, and reject transitions as member applications.</p>
          <p v-if="authUser.initialized && !canAccessAdminDashboard" class="small">Approved admin access is required for this queue.</p>
        </div>
        <div v-if="adminStartupListingsStore.error" class="notice error-notice">
          <p class="small">Could not load startup listings. Confirm this account has admin access and the backend is running.</p>
        </div>
        <div v-if="canAccessAdminDashboard" class="filter-row">
          <button
            v-for="status in ['submitted', 'needs_more_info', 'approved', 'rejected']"
            :key="status"
            class="button secondary"
            :class="{ active: adminStartupListingsStore.listStatus === status }"
            type="button"
            @click="loadAdminStartupListings(status)"
          >
            {{ titleize(status) }}
          </button>
        </div>
        <div v-if="canAccessAdminDashboard" class="grid two">
          <article class="card">
            <div class="row">
              <h2>Listings</h2>
              <button class="button secondary" type="button" @click="loadAdminStartupListings(adminStartupListingsStore.listStatus)">Refresh</button>
            </div>
            <p v-if="adminStartupListingsStore.loading" class="small">Loading startup listings.</p>
            <p v-else-if="!adminStartupListingsStore.hasListings" class="small">No startup listings in this status.</p>
            <div v-else class="table">
              <button
                v-for="listing in adminStartupListingsStore.list"
                :key="listing.id"
                class="table-row table-button"
                type="button"
                @click="selectAdminStartupListing(listing)"
              >
                <span>
                  {{ listing.name }}
                  <br>
                  <small>{{ listing.tagline }}</small>
                </span>
                <strong>{{ titleize(listing.approval_status) }}</strong>
              </button>
            </div>
          </article>

          <article v-if="!selectedAdminStartupListing" class="card">
            <span class="tag">No selection</span>
            <h2>Select a startup listing.</h2>
            <p class="small">Choose a row from the queue to view listing context and review actions.</p>
          </article>

          <form v-else class="app-form card" @submit.prevent="approveStartupListing">
            <div class="full row">
              <div>
                <span class="tag">{{ titleize(selectedAdminStartupListing.approval_status) }}</span>
                <h2>{{ adminStartupListingsStore.selectedListingName }}</h2>
                <p class="small">{{ selectedAdminStartupListing.tagline }}</p>
              </div>
            </div>

            <div class="table full">
              <div v-for="[label, value] in selectedAdminStartupRows" :key="label" class="table-row"><span>{{ label }}</span><strong>{{ value }}</strong></div>
            </div>

            <label class="full">Description<textarea :value="selectedAdminStartupListing.description || 'Not provided'" readonly /></label>
            <label>Website<input :value="selectedAdminStartupListing.website_url || 'Not provided'" readonly /></label>
            <label>LinkedIn<input :value="selectedAdminStartupListing.linkedin_url || 'Not provided'" readonly /></label>
            <label class="full">Review notes<textarea v-model="adminStartupReviewForm.review_notes" :disabled="adminStartupListingsStore.saving" /></label>
            <label class="checkbox-row full"><input v-model="adminStartupReviewForm.send_email" type="checkbox" :disabled="adminStartupListingsStore.saving" /> Send rejection email</label>

            <div v-if="Object.keys(adminStartupValidationErrors).length" class="notice error-notice full">
              <p v-for="(messages, field) in adminStartupValidationErrors" :key="field" class="small">{{ messages[0] }}</p>
            </div>

            <div class="button-grid full">
              <button class="button primary" type="submit" :disabled="adminStartupListingsStore.saving">Approve</button>
              <button class="button water" type="button" :disabled="adminStartupListingsStore.saving" @click="requestStartupListingInfo">Request info</button>
              <button class="button secondary" type="button" :disabled="adminStartupListingsStore.saving" @click="rejectStartupListing">Reject</button>
            </div>
            <p v-if="adminStartupListingsStore.currentLoading" class="small full">Loading full listing detail.</p>
          </form>
        </div>
      </section>

      <section v-else-if="currentView === 'users'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">User management</p>
          <h1>Search the membership and adjust roles.</h1>
          <p class="lede">Filter the directory by role, approval, or affiliation. Only super admins can promote or demote admins; regular admins can review profiles only.</p>
          <p v-if="authUser.initialized && !canAccessAdminDashboard" class="small">Approved admin access is required for this view.</p>
        </div>
        <div v-if="adminUsersStore.error" class="notice error-notice">
          <p class="small">Could not load users. Confirm this account has admin access and the backend is running.</p>
        </div>
        <div v-if="canAccessAdminDashboard" class="filter-row">
          <span class="small">Role:</span>
          <button
            v-for="role in adminUserRoleFilters"
            :key="role"
            class="button secondary"
            :class="{ active: userFilterForm.permission_role === role }"
            type="button"
            @click="setAdminUserRoleFilter(role)"
          >
            {{ titleize(role) }}
          </button>
        </div>
        <div v-if="canAccessAdminDashboard" class="filter-row">
          <span class="small">Approval:</span>
          <button
            v-for="status in adminUserApprovalFilters"
            :key="status"
            class="button secondary"
            :class="{ active: userFilterForm.approval_status === status }"
            type="button"
            @click="setAdminUserApprovalFilter(status)"
          >
            {{ titleize(status) }}
          </button>
        </div>
        <form v-if="canAccessAdminDashboard" class="filter-row" @submit.prevent="searchAdminUsers">
          <input v-model="userFilterForm.q" type="search" placeholder="Search name or email" />
          <button class="button water" type="submit" :disabled="adminUsersStore.loading">Search</button>
          <button class="button secondary" type="button" @click="loadAdminUsers()">Refresh</button>
        </form>
        <div v-if="canAccessAdminDashboard" class="grid two">
          <article class="card">
            <div class="row">
              <h2>Users</h2>
              <span class="small">{{ adminUserCount }} matching</span>
            </div>
            <p v-if="adminUsersStore.loading" class="small">Loading users.</p>
            <p v-else-if="!adminUsersStore.hasUsers" class="small">No users match these filters.</p>
            <div v-else class="table">
              <button
                v-for="user in adminUsersStore.list"
                :key="user.id"
                class="table-row table-button"
                type="button"
                @click="selectAdminUser(user)"
              >
                <span>
                  {{ user.name || user.email }}
                  <br>
                  <small>{{ user.email }}</small>
                </span>
                <strong>
                  {{ titleize(user.permission_role) }}
                  <small v-if="user.suspended_at"> · Suspended</small>
                  <small v-else-if="user.approval_status && user.approval_status !== 'approved'"> · {{ titleize(user.approval_status) }}</small>
                </strong>
              </button>
            </div>
          </article>

          <article v-if="!selectedAdminUser" class="card">
            <span class="tag">No selection</span>
            <h2>Select a user.</h2>
            <p class="small">Choose a row from the directory to view profile context and role actions.</p>
          </article>

          <article v-else class="card">
            <div class="row">
              <div>
                <span class="tag">{{ titleize(selectedAdminUser.permission_role) }}</span>
                <h2>{{ adminUsersStore.selectedUserName }}</h2>
                <p class="small">{{ selectedAdminUser.email }}</p>
              </div>
            </div>

            <div class="table">
              <div v-for="[label, value] in selectedAdminUserRows" :key="label" class="table-row"><span>{{ label }}</span><strong>{{ value }}</strong></div>
            </div>

            <p v-if="adminUserSaveError" class="notice error-notice small">{{ adminUserSaveError }}</p>

            <div v-if="!canManageAdminPrivileges" class="notice small">Super admin access is required to change roles.</div>
            <div v-else-if="isCurrentAuthUser" class="notice small">You cannot change your own role from this screen.</div>
            <div v-else class="button-grid">
              <button v-if="selectedAdminUser.permission_role === 'member'" class="button water" type="button" :disabled="adminUsersStore.saving || selectedAdminUser.approval_status !== 'approved'" @click="promoteAdminUser">Promote to admin</button>
              <button v-if="selectedAdminUser.permission_role === 'admin'" class="button secondary" type="button" :disabled="adminUsersStore.saving" @click="demoteAdminUser">Demote admin</button>
              <button v-if="selectedAdminUser.permission_role === 'admin'" class="button water" type="button" :disabled="adminUsersStore.saving" @click="promoteSuperAdminUser">Promote to super admin</button>
              <button v-if="selectedAdminUser.permission_role === 'super_admin'" class="button secondary" type="button" :disabled="adminUsersStore.saving" @click="demoteSuperAdminUser">Demote super admin</button>
            </div>
            <p v-if="adminUsersStore.currentLoading" class="small">Loading full user detail.</p>
          </article>
        </div>
      </section>

      <section v-else-if="currentView === 'events-admin'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Event management</p>
          <h1>Create, edit, publish, hide, archive, and cancel events.</h1>
          <p class="lede">Admins author events as drafts, then publish them to public or members-only audiences. Cancellation hides events from public surfaces while keeping them visible here.</p>
          <p v-if="authUser.initialized && !canAccessAdminDashboard" class="small">Approved admin access is required for this view.</p>
        </div>
        <div v-if="adminEventsStore.error" class="notice error-notice">
          <p class="small">Could not load events. Confirm this account has admin access and the backend is running.</p>
        </div>
        <div v-if="canAccessAdminDashboard" class="filter-row">
          <button
            v-for="status in adminEventStatusFilters"
            :key="status"
            class="button secondary"
            :class="{ active: adminEventsStore.listContentStatus === status }"
            type="button"
            @click="loadAdminEvents(status)"
          >
            {{ titleize(status) }}
          </button>
        </div>
        <div v-if="canAccessAdminDashboard" class="grid two">
          <article class="card">
            <div class="row">
              <h2>Events</h2>
              <div class="row gap">
                <button class="button secondary" type="button" @click="loadAdminEvents(adminEventsStore.listContentStatus)">Refresh</button>
                <button class="button water" type="button" @click="startNewEvent">New event</button>
              </div>
            </div>
            <p v-if="adminEventsStore.loading" class="small">Loading events.</p>
            <p v-else-if="!adminEventsStore.hasEvents" class="small">No events in this status.</p>
            <div v-else class="table">
              <button
                v-for="event in adminEventsStore.list"
                :key="event.id"
                class="table-row table-button"
                type="button"
                @click="selectAdminEvent(event)"
              >
                <span>
                  {{ event.title }}
                  <br>
                  <small>{{ formatEventDateTime(event.starts_at) || 'No start date' }}</small>
                </span>
                <strong>
                  {{ titleize(event.content_status) }}
                  <small v-if="event.cancelled_at"> · Cancelled</small>
                </strong>
              </button>
            </div>
          </article>

          <form class="app-form card" @submit.prevent="submitAdminEvent">
            <div class="full row">
              <div>
                <span class="tag">{{ adminEventsStore.isCreatingNew ? 'New event' : titleize(selectedAdminEvent?.content_status) || 'Draft' }}</span>
                <h2>{{ adminEventsStore.selectedEventTitle }}</h2>
                <p v-if="!adminEventsStore.isCreatingNew && selectedAdminEvent?.cancelled_at" class="small">Cancelled {{ formatEventDateTime(selectedAdminEvent.cancelled_at) }}.</p>
              </div>
            </div>

            <div v-if="!adminEventsStore.isCreatingNew" class="table full">
              <div v-for="[label, value] in selectedAdminEventRows" :key="label" class="table-row"><span>{{ label }}</span><strong>{{ value }}</strong></div>
            </div>

            <label class="full">Title<input v-model="eventForm.title" :disabled="adminEventsStore.saving" required /></label>
            <label class="full">Summary<textarea v-model="eventForm.summary" :disabled="adminEventsStore.saving" required /></label>
            <label class="full">Description<textarea v-model="eventForm.description" :disabled="adminEventsStore.saving" required /></label>
            <label>Starts<input v-model="eventForm.starts_at" type="datetime-local" :disabled="adminEventsStore.saving" required /></label>
            <label>Ends<input v-model="eventForm.ends_at" type="datetime-local" :disabled="adminEventsStore.saving" /></label>
            <label>Location<input v-model="eventForm.location" :disabled="adminEventsStore.saving" /></label>
            <label>Format<input v-model="eventForm.format" :disabled="adminEventsStore.saving" /></label>
            <label>Capacity<input v-model="eventForm.capacity_limit" type="number" min="0" :disabled="adminEventsStore.saving" /></label>
            <label>Visibility
              <select v-model="eventForm.visibility" :disabled="adminEventsStore.saving">
                <option v-for="visibility in contentVisibilities" :key="visibility" :value="visibility">{{ titleize(visibility) }}</option>
              </select>
            </label>
            <label>Reminder days before<input v-model="eventForm.reminder_days_before" type="number" min="0" max="60" :disabled="adminEventsStore.saving" /></label>
            <label class="checkbox-row"><input v-model="eventForm.waitlist_open" type="checkbox" :disabled="adminEventsStore.saving" /> Waitlist open</label>
            <label class="full">External registration URL<input v-model="eventForm.registration_url" type="url" :disabled="adminEventsStore.saving" /></label>
            <label class="full">Image URL<input v-model="eventForm.image_url" type="url" :disabled="adminEventsStore.saving" /></label>
            <label class="full">Recap content<textarea v-model="eventForm.recap_content" :disabled="adminEventsStore.saving" /></label>

            <div v-if="Object.keys(adminEventValidationErrors).length" class="notice error-notice full">
              <p v-for="(messages, field) in adminEventValidationErrors" :key="field" class="small">{{ messages[0] }}</p>
            </div>

            <div class="button-grid full">
              <button class="button primary" type="submit" :disabled="adminEventsStore.saving">{{ adminEventSaveLabel }}</button>
              <button v-if="!adminEventsStore.isCreatingNew && selectedAdminEvent?.content_status !== 'published'" class="button water" type="button" :disabled="adminEventsStore.saving" @click="publishAdminEvent">Publish</button>
              <button v-if="!adminEventsStore.isCreatingNew && selectedAdminEvent?.content_status !== 'hidden'" class="button secondary" type="button" :disabled="adminEventsStore.saving" @click="hideAdminEvent">Hide</button>
              <button v-if="!adminEventsStore.isCreatingNew && selectedAdminEvent?.content_status !== 'archived'" class="button secondary" type="button" :disabled="adminEventsStore.saving" @click="archiveAdminEvent">Archive</button>
            </div>

            <div v-if="!adminEventsStore.isCreatingNew && !selectedAdminEvent?.cancelled_at" class="full">
              <label class="full">Cancellation note<textarea v-model="eventCancelForm.cancellation_note" :disabled="adminEventsStore.saving" /></label>
              <button class="button secondary" type="button" :disabled="adminEventsStore.saving" @click="cancelAdminEvent">Cancel event</button>
            </div>
            <p v-if="adminEventsStore.currentLoading" class="small full">Loading full event detail.</p>
          </form>
        </div>
      </section>

      <section v-else-if="currentView === 'content-admin'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Public content</p>
          <h1>Edit website cards without touching code.</h1>
          <p class="lede">Admin CMS area for events, startups, partners, homepage cards, and visibility controls.</p>
        </div>
        <div class="grid two">
          <article class="card">
            <h2>Content inventory</h2>
            <div class="table">
              <div class="table-row"><span>Neural Insights</span><strong>Published</strong></div>
              <div class="table-row"><span>Founder support partner</span><strong>Draft</strong></div>
              <div class="table-row"><span>Demo Night</span><strong>Hidden</strong></div>
              <div class="table-row"><span>AutoFlow AI</span><strong>Pending review</strong></div>
            </div>
          </article>
          <form class="app-form card">
            <label class="full">Title<input value="Neural Insights"></label>
            <label>Type<select><option>Startup</option><option>Event</option><option>Partner</option><option>Homepage feature</option></select></label>
            <label>Status<select><option v-for="status in contentStatuses" :key="status">{{ status }}</option></select></label>
            <label class="full">Short description<textarea>AI-powered analytics platform for extracting actionable insights from complex datasets.</textarea></label>
          </form>
        </div>
      </section>

      <section v-else-if="currentView === 'announcements'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Announcements</p>
          <h1>Broadcast updates to members or segments.</h1>
          <p class="lede">Announcements should go through both email and dashboard notification when selected.</p>
        </div>
        <div class="grid two">
          <form class="app-form card">
            <label>Audience<select><option>All active members</option><option>Admins only</option><option>Event registrants</option></select></label>
            <label>Channel<select><option>Email + dashboard</option><option>Dashboard only</option></select></label>
            <label class="full">Subject<input value="New WAAIS forum categories are live"></label>
            <label class="full">Message<textarea>We have opened new member discussion spaces for founders, operators, research, jobs, and member introductions.</textarea></label>
          </form>
          <article class="card mobile-preview">
            <h2>Preview</h2>
            <div class="mobile-card">
              <p class="eyebrow">Announcement</p>
              <h3>New WAAIS forum categories are live</h3>
              <p class="small">We have opened new member discussion spaces for founders, operators, research, jobs, and member introductions.</p>
            </div>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>
