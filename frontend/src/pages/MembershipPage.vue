<script setup>
import { computed, onMounted, reactive, watch } from 'vue'
import { RouterLink } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { useAuthUserStore } from '../stores/authUser'
import { useMembershipApplicationStore } from '../stores/membershipApplication'

const authUser = useAuthUserStore()
const applicationStore = useMembershipApplicationStore()

const form = reactive({
  affiliation_type: 'alumni',
  email: '',
  first_name: '',
  last_name: '',
  phone_whatsapp: '',
  is_alumnus: true,
  school_affiliation: '',
  graduation_year: '',
  inviter_name: '',
  primary_location: '',
  secondary_location: '',
  linkedin_url: '',
  experience_summary: '',
  expertise_summary: '',
  industries_to_add_value: '',
  industries_to_extend_expertise: '',
  availability: '',
  gender: '',
  age: '',
})
const emailLinkForm = reactive({
  email: '',
})

const hasSession = computed(() => authUser.isAuthenticated)
const checkingSession = computed(() => authUser.loading || !authUser.initialized)
const showApplicationForm = computed(() => authUser.initialized && hasSession.value)
const canSubmit = computed(() => showApplicationForm.value && applicationStore.canEdit && !applicationStore.saving && !applicationStore.loading)
const showSessionError = computed(() => Boolean(authUser.error))
const statusLabel = computed(() => {
  const status = applicationStore.status
  if (!hasSession.value) return 'Sign in required'
  if (applicationStore.loading) return 'Loading application'
  if (!status) return 'Not submitted'
  return status.replaceAll('_', ' ')
})
const saveLabel = computed(() => {
  if (applicationStore.saving) return 'Saving...'
  if (applicationStore.mustReapply) return 'Reapply'
  if (applicationStore.hasApplication) return 'Update application'
  return 'Submit application'
})
const validationErrors = computed(() => applicationStore.saveError?.body?.errors ?? {})
const startMembershipSignIn = () => authUser.startGoogleSignIn({ next: '/membership' })
const emailLinkErrors = computed(() => authUser.emailLinkError?.body?.errors ?? {})

function populateForm(application) {
  const source = application ?? {}
  form.affiliation_type = source.affiliation_type ?? authUser.user?.affiliation_type ?? 'alumni'
  form.email = source.email ?? authUser.user?.email ?? ''
  form.first_name = source.first_name ?? firstName(authUser.user?.name) ?? ''
  form.last_name = source.last_name ?? lastName(authUser.user?.name) ?? ''
  form.phone_whatsapp = source.phone_whatsapp ?? ''
  form.is_alumnus = source.is_alumnus ?? true
  form.school_affiliation = source.school_affiliation ?? ''
  form.graduation_year = source.graduation_year ?? ''
  form.inviter_name = source.inviter_name ?? ''
  form.primary_location = source.primary_location ?? ''
  form.secondary_location = source.secondary_location ?? ''
  form.linkedin_url = source.linkedin_url ?? ''
  form.experience_summary = source.experience_summary ?? ''
  form.expertise_summary = source.expertise_summary ?? ''
  form.industries_to_add_value = arrayToList(source.industries_to_add_value)
  form.industries_to_extend_expertise = arrayToList(source.industries_to_extend_expertise)
  form.availability = source.availability ?? ''
  form.gender = source.gender ?? ''
  form.age = source.age ?? ''
}

function firstName(name) {
  return name?.split(' ')?.[0] ?? ''
}

function lastName(name) {
  const parts = name?.split(' ').filter(Boolean) ?? []
  return parts.length > 1 ? parts.slice(1).join(' ') : ''
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

function nullableInteger(value) {
  if (value === '' || value === null || value === undefined) return null
  return Number(value)
}

function payload() {
  return {
    affiliation_type: form.affiliation_type || null,
    email: form.email.trim(),
    first_name: form.first_name.trim(),
    last_name: form.last_name.trim(),
    phone_whatsapp: nullableString(form.phone_whatsapp),
    is_alumnus: form.is_alumnus,
    school_affiliation: nullableString(form.school_affiliation),
    graduation_year: nullableInteger(form.graduation_year),
    inviter_name: nullableString(form.inviter_name),
    primary_location: nullableString(form.primary_location),
    secondary_location: nullableString(form.secondary_location),
    linkedin_url: nullableString(form.linkedin_url),
    experience_summary: nullableString(form.experience_summary),
    expertise_summary: nullableString(form.expertise_summary),
    industries_to_add_value: listToArray(form.industries_to_add_value),
    industries_to_extend_expertise: listToArray(form.industries_to_extend_expertise),
    availability: nullableString(form.availability),
    gender: nullableString(form.gender),
    age: nullableInteger(form.age),
  }
}

async function loadApplication() {
  await authUser.loadCurrentUser({ force: true })
  populateForm(null)

  if (authUser.isAuthenticated) {
    await applicationStore.load().catch((error) => {
      if (error?.status !== 401) throw error
    })
    populateForm(applicationStore.application)
  }
}

async function submitApplication() {
  await applicationStore.save(payload())
  populateForm(applicationStore.application)
  await authUser.loadCurrentUser({ force: true }).catch(() => {})
}

async function requestEmailLink() {
  await authUser.requestEmailSignIn(emailLinkForm.email, { next: '/membership' })
}

watch(() => applicationStore.application, populateForm)

onMounted(() => {
  loadApplication().catch(() => {})
})
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Membership" title="Become a WAAIS member." lede="Existing members sign in. New applicants apply. Non-members can still propose topics, partnerships, and listings." />
    <section class="section paper">
      <div class="section-inner">
        <div v-if="hasSession" class="notice" style="margin-top: 20px">
          <p class="small">
            Account status: <strong>{{ statusLabel }}</strong>
            <span v-if="authUser.user"> for {{ authUser.user.email }}</span>.
          </p>
          <p v-if="applicationStore.application?.review_notes" class="small">Admin note: {{ applicationStore.application.review_notes }}</p>
          <p v-if="applicationStore.status === 'approved'" class="small">Approved applications are retained for profile history and cannot be edited here.</p>
          <p v-else-if="applicationStore.needsMoreInfo" class="small">Please update the requested fields and resubmit for review.</p>
          <p v-else-if="applicationStore.mustReapply" class="small">Your previous application was rejected. Update your answers and reapply when ready.</p>
          <p v-else-if="checkingSession" class="small">Checking for an active Google sign-in session.</p>
          <p v-else-if="!hasSession" class="small">Sign in with Google before the application form is shown.</p>
        </div>

        <div v-if="showSessionError" class="notice error-notice" style="margin-top: 20px">
          <p class="small">Could not check your sign-in session. Confirm the backend is running, then reload this page.</p>
        </div>

        <div v-if="applicationStore.error" class="notice error-notice" style="margin-top: 20px">
          <p class="small">Could not load your application. Confirm the backend is running, then reload this page.</p>
        </div>

        <div v-if="checkingSession" class="auth-gate">
          <article class="card">
            <span class="tag">Checking session</span>
            <h3>Looking for an active session.</h3>
            <p>The application form appears after WAAIS confirms your identity.</p>
          </article>
        </div>

        <div v-else-if="!hasSession" class="auth-gate">
          <article class="card">
            <span class="tag">Membership application</span>
            <h3>Start or resume your application.</h3>
            <p>Choose an identity method to open the membership questionnaire. Google sign-in works immediately; email sends a secure link to this browser flow.</p>
            <form class="compact-auth-form" @submit.prevent="requestEmailLink">
              <label>Email address<input v-model="emailLinkForm.email" required type="email" placeholder="you@example.com" :disabled="authUser.emailLinkSending" /></label>
              <button class="button secondary paper-button" type="submit" :disabled="authUser.emailLinkSending">{{ authUser.emailLinkSending ? 'Sending...' : 'Start with email' }}</button>
            </form>
            <div v-if="authUser.emailLinkSent" class="notice" style="margin-top: 14px">
              <p class="small">Check your email for a WAAIS sign-in link. In local development, it is written to the Laravel log.</p>
            </div>
            <div v-if="Object.keys(emailLinkErrors).length" class="notice error-notice" style="margin-top: 14px">
              <p v-for="(messages, field) in emailLinkErrors" :key="field" class="small">{{ messages[0] }}</p>
            </div>
            <div class="row" style="margin-top: 14px">
              <button class="button primary" type="button" @click="startMembershipSignIn">Continue with Google</button>
            </div>
          </article>
        </div>

        <form v-else class="application-form" @submit.prevent="submitApplication">
          <label>Email *<input v-model="form.email" required type="email" placeholder="you@example.com" :disabled="!canSubmit" /></label>
          <label>Phone associated with WhatsApp account (optional)<input v-model="form.phone_whatsapp" placeholder="Only if you want to join the WhatsApp community" :disabled="!canSubmit" /></label>
          <label>First name *<input v-model="form.first_name" required placeholder="First name" :disabled="!canSubmit" /></label>
          <label>Last name *<input v-model="form.last_name" required placeholder="Last name" :disabled="!canSubmit" /></label>
          <label>Affiliation type
            <select v-model="form.affiliation_type" :disabled="!canSubmit">
              <option value="alumni">Alumni</option>
              <option value="student">Student</option>
              <option value="faculty_staff">Faculty/staff</option>
              <option value="partner_guest">Partner guest</option>
              <option value="other">Other</option>
            </select>
          </label>
          <label>Are you an alumnus/a? *
            <select v-model="form.is_alumnus" :disabled="!canSubmit">
              <option :value="true">Yes</option>
              <option :value="false">No</option>
            </select>
          </label>
          <label>School affiliation<input v-model="form.school_affiliation" placeholder="School, program, student/faculty/staff status, or other affiliation" :disabled="!canSubmit" /></label>
          <label>Graduation year<input v-model="form.graduation_year" type="number" min="1800" max="2100" placeholder="e.g. 2020" :disabled="!canSubmit" /></label>
          <label>Primary location<input v-model="form.primary_location" placeholder="City, region, or country" :disabled="!canSubmit" /></label>
          <label>Secondary location<input v-model="form.secondary_location" placeholder="Optional" :disabled="!canSubmit" /></label>
          <label>LinkedIn profile<input v-model="form.linkedin_url" type="url" placeholder="https://www.linkedin.com/in/..." :disabled="!canSubmit" /></label>
          <label>Age (optional)<input v-model="form.age" type="number" min="13" max="120" placeholder="Optional" :disabled="!canSubmit" /></label>
          <label class="full">If invited by a Wharton/Penn alumnus/a, provide their name<input v-model="form.inviter_name" placeholder="Inviter name, if applicable" :disabled="!canSubmit" /></label>
          <label class="full">Experience: industries and roles<textarea v-model="form.experience_summary" placeholder="Tell us about industries you worked in and roles you held." :disabled="!canSubmit" /></label>
          <label class="full">Expertise<textarea v-model="form.expertise_summary" placeholder="Tell us about your expertise." :disabled="!canSubmit" /></label>
          <label class="full">Industries where you would like to add value<textarea v-model="form.industries_to_add_value" placeholder="Comma-separated, e.g. Finance, AI Engineering" :disabled="!canSubmit" /></label>
          <label class="full">Industries where you want to extend your expertise<textarea v-model="form.industries_to_extend_expertise" placeholder="Comma-separated, e.g. Healthcare, Education" :disabled="!canSubmit" /></label>
          <label class="full">Availability<textarea v-model="form.availability" placeholder="How much time per month can you dedicate to a potential future project?" :disabled="!canSubmit" /></label>
          <label>Gender (optional)<input v-model="form.gender" placeholder="Optional" :disabled="!canSubmit" /></label>

          <div v-if="Object.keys(validationErrors).length" class="notice error-notice full">
            <p v-for="(messages, field) in validationErrors" :key="field" class="small">{{ messages[0] }}</p>
          </div>

          <div class="row full">
            <button class="button primary" type="submit" :disabled="!canSubmit">{{ saveLabel }}</button>
            <button v-if="!hasSession" class="button water" type="button" @click="startMembershipSignIn">Sign in with Google</button>
          </div>
        </form>

        <div class="grid" style="margin-top: 24px">
          <article class="card">
            <span class="tag">Non-members</span>
            <h3>Propose a topic</h3>
            <p>Suggest a founder salon, workshop, or focused AI adoption roundtable.</p>
            <RouterLink class="button water" to="/contact">Propose topic</RouterLink>
          </article>
          <article class="card">
            <span class="tag">Partners</span>
            <h3>Partner with us</h3>
            <p>Sponsor events, provide credits, or collaborate on member education.</p>
            <RouterLink class="button water" to="/contact">Partner with us</RouterLink>
          </article>
          <article class="card">
            <span class="tag">Founders</span>
            <h3>List a startup</h3>
            <p>Startup listings are reviewed and published by admins after member access is confirmed.</p>
            <RouterLink class="button water" to="/contact">Request listing</RouterLink>
          </article>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
