<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  approvalStatuses,
  affiliationTypes,
  contentStatuses,
  contentVisibilities,
  permissionRoles,
} from '../data/platformModel'
import { useAuthUserStore } from '../stores/authUser'
import { useMembershipApplicationStore } from '../stores/membershipApplication'

const route = useRoute()
const authUser = useAuthUserStore()
const applicationStore = useMembershipApplicationStore()

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
      ['my-events', 'My events'],
      ['forum-feed', 'Forum feed'],
    ],
  },
  {
    label: 'Admin dashboard',
    items: [
      ['admin', 'Admin overview'],
      ['approvals', 'Approvals'],
      ['users', 'User management'],
      ['events-admin', 'Event management'],
      ['content-admin', 'Public content'],
      ['announcements', 'Announcements'],
    ],
  },
]

const currentView = computed(() => route.params.view || 'sign-in')

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

const metrics = {
  admin: [
    ['Pending approvals', '8'],
    ['Active members', '284'],
    ['Published events', '12'],
    ['Public cards', '46'],
  ],
}

async function loadMemberDashboard() {
  await authUser.loadCurrentUser()
  if (authUser.isAuthenticated) {
    await applicationStore.load().catch((error) => {
      if (error?.status !== 401) throw error
    })
  }
}

onMounted(() => {
  loadMemberDashboard().catch(() => {})
})
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

      <div v-for="group in navGroups" :key="group.label" class="app-nav-group">
        <p class="sidebar-label">{{ group.label }}</p>
        <nav class="app-nav" :aria-label="group.label">
          <RouterLink v-for="[id, label] in group.items" :key="id" :to="`/app/${id}`">{{ label }}</RouterLink>
        </nav>
      </div>

      <RouterLink class="button secondary" to="/">Public site</RouterLink>
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
          <h2>Continue with Google</h2>
          <p class="small">Laravel Socialite owns Google OAuth. After Google returns, Sanctum keeps the browser session for `/api/user` and future member/admin requests.</p>
          <button class="button primary" type="button" @click="authUser.startGoogleSignIn()">Sign in with Google</button>
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
          <div v-for="[label, value] in metrics.admin" :key="label" class="metric"><span>{{ label }}</span><strong>{{ value }}</strong></div>
        </div>
        <div class="grid two">
          <article class="card">
            <h2>Operational queue</h2>
            <div class="table">
              <div class="table-row"><span>Approve 8 new applicants</span><strong>Needs review</strong></div>
              <div class="table-row"><span>Publish Demo Night</span><strong>Draft</strong></div>
              <div class="table-row"><span>Review AutoFlow AI listing</span><strong>Pending</strong></div>
            </div>
          </article>
          <article class="card">
            <h2>Quick actions</h2>
            <div class="button-grid">
              <RouterLink class="button water" to="/app/events-admin">Create event</RouterLink>
              <RouterLink class="button water" to="/app/content-admin">Review startup</RouterLink>
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
          <p class="lede">Fast review table with affiliation signals, application context, and approve/request-more-info/reject controls.</p>
        </div>
        <article class="card wide-card">
          <div class="admin-table">
            <div class="admin-row header"><span>Applicant</span><span>Signal</span><span>Status</span><span>Action</span></div>
            <div class="admin-row"><span>Maya Chen<br><small>maya@wharton.upenn.edu</small></span><span>Wharton email</span><span>Pending</span><span>Approve / More info / Reject</span></div>
            <div class="admin-row"><span>Daniel Reed<br><small>daniel@startup.ai</small></span><span>LinkedIn provided</span><span>Pending</span><span>Approve / More info / Reject</span></div>
            <div class="admin-row"><span>Priya Shah<br><small>priya@enterprise.com</small></span><span>Manual check</span><span>Pending</span><span>Approve / More info / Reject</span></div>
          </div>
        </article>
      </section>

      <section v-else-if="currentView === 'users'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">User management</p>
          <h1>Members, admins, students, guests, and suspended accounts.</h1>
          <p class="lede">Super admins alone can promote users to admin or remove admin privileges.</p>
        </div>
        <article class="card wide-card">
          <div class="admin-row header"><span>User</span><span>Role</span><span>Status</span><span>Action</span></div>
          <div class="admin-row"><span>George Chen</span><span>Super admin</span><span>Active</span><span>Protected</span></div>
          <div class="admin-row"><span>Nina Park</span><span>Member</span><span>Active</span><span>Edit profile</span></div>
          <div class="admin-row"><span>Alex Li</span><span>Member</span><span>Suspended</span><span>Review</span></div>
        </article>
      </section>

      <section v-else-if="currentView === 'events-admin'" class="app-stack">
        <div class="app-hero">
          <p class="eyebrow">Event management</p>
          <h1>Create, publish, cancel, hide, and recap events.</h1>
          <p class="lede">Events need visibility, capacity, waitlist, external registration URL, reminder timing, cancellation, and recap fields.</p>
        </div>
        <div class="grid two">
          <form class="app-form card">
            <label class="full">Title<input value="AI Founder Salon"></label>
            <label>Date<input value="May 14, 2026"></label>
            <label>Capacity<input value="50"></label>
            <label>Status<select><option v-for="status in contentStatuses" :key="status">{{ status }}</option></select></label>
            <label>Visibility<select><option v-for="visibility in contentVisibilities" :key="visibility">{{ visibility }}</option></select></label>
            <label class="full">External registration URL<input value="https://example.com/register"></label>
            <label class="full">Description<textarea>Private dinner for AI founders and operators in the WAAIS community.</textarea></label>
          </form>
          <article class="card">
            <h2>Published events</h2>
            <div class="table">
              <div class="table-row"><span>AI Founder Salon</span><strong>42 / 50</strong></div>
              <div class="table-row"><span>Agentic Workflows</span><strong>18 / 100</strong></div>
              <div class="table-row"><span>Demo Night</span><strong>Draft</strong></div>
            </div>
          </article>
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
