<script setup>
import { computed, onMounted, reactive, watch } from 'vue'
import { RouterLink } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { useAuthUserStore } from '../stores/authUser'
import { useMembershipApplicationStore } from '../stores/membershipApplication'

const authUser = useAuthUserStore()
const applicationStore = useMembershipApplicationStore()

const registerForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

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
  privacy_acknowledgement: false,
})

const hasSession = computed(() => authUser.isAuthenticated)
const checkingSession = computed(() => authUser.loading || !authUser.initialized)
const isAnonymous = computed(() => authUser.initialized && !hasSession.value)
const emailVerified = computed(() => authUser.user?.email_verified === true)
const justRegistered = computed(() => authUser.registered)
const awaitingVerification = computed(() => justRegistered.value || (hasSession.value && !emailVerified.value))
// Must depend on the live session too: signing out from the shared header
// clears authUser but not this store, and without hasSession the approved
// "Open dashboard / Visit the forum" card would linger after sign-out.
const isApprovedMember = computed(() => hasSession.value && applicationStore.status === 'approved')
const showApplicationForm = computed(() => hasSession.value && emailVerified.value && !isApprovedMember.value)
const canEditFields = computed(() => showApplicationForm.value && applicationStore.canEdit && !applicationStore.saving && !applicationStore.loading)
const requiresPrivacyAcknowledgement = computed(() => !applicationStore.hasApplication || applicationStore.mustReapply)
const canSubmit = computed(() => {
  return canEditFields.value
    && (!requiresPrivacyAcknowledgement.value || form.privacy_acknowledgement)
})
const canRegister = computed(() => {
  return !authUser.registering
    && registerForm.first_name.trim() !== ''
    && registerForm.last_name.trim() !== ''
    && registerForm.email.trim() !== ''
    && registerForm.password.length >= 8
    && registerForm.password === registerForm.password_confirmation
})
const showSessionError = computed(() => Boolean(authUser.error))
const statusLabel = computed(() => {
  const status = applicationStore.status
  if (!hasSession.value) return 'Not registered'
  if (!emailVerified.value) return 'Awaiting email verification'
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
const registerErrors = computed(() => authUser.registerError ? (authUser.registerError.body?.errors ?? { general: [authUser.registerError.body?.message || 'Could not create the account. Please try again.'] }) : {})
const verificationEmail = computed(() => registerForm.email.trim() || authUser.user?.email || '')

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
  form.privacy_acknowledgement = Boolean(source.privacy_acknowledged_at)
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
  const data = {
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

  if (requiresPrivacyAcknowledgement.value) {
    data.privacy_acknowledgement = form.privacy_acknowledgement
  }

  return data
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

async function register() {
  await authUser.register({
    first_name: registerForm.first_name.trim(),
    last_name: registerForm.last_name.trim(),
    email: registerForm.email.trim(),
    password: registerForm.password,
    password_confirmation: registerForm.password_confirmation,
  })
  registerForm.password = ''
  registerForm.password_confirmation = ''
}

async function submitApplication() {
  await applicationStore.save(payload())
  populateForm(applicationStore.application)
  await authUser.loadCurrentUser({ force: true }).catch(() => {})
}

async function resendVerification() {
  await authUser.resendVerification(verificationEmail.value)
}

async function signOut() {
  await authUser.signOut()
  applicationStore.clear()
  populateForm(null)
}

watch(() => applicationStore.application, populateForm)

// Reset cached application state whenever the session ends — including a
// sign-out triggered from the shared header, which doesn't run this page's
// own signOut handler.
watch(() => authUser.isAuthenticated, (isAuthed) => {
  if (!isAuthed) {
    applicationStore.clear()
    populateForm(null)
  }
})

onMounted(() => {
  loadApplication().catch(() => {})
})
</script>

<template>
  <PublicLayout>
    <PageHero compact title="Become a WAAIS member" lede="Create your account, verify your email, then complete the membership application for admin review." />
    <section class="section paper">
      <div class="section-inner">
        <div v-if="hasSession && !isApprovedMember && !awaitingVerification" class="notice" style="margin-top: 20px">
          <p v-if="applicationStore.status === 'submitted'" class="small"><strong>Application received.</strong> Our admins are reviewing it — you'll get an email as soon as a decision is made. You can still update your answers below in the meantime.</p>
          <p v-else-if="applicationStore.needsMoreInfo" class="small"><strong>A little more information needed.</strong> Please update the requested fields below and resubmit.</p>
          <p v-else-if="applicationStore.mustReapply" class="small"><strong>Your previous application was not approved.</strong> Update your answers below and reapply when ready.</p>
          <p v-else class="small">Signed in as {{ authUser.user?.email }}. Complete the application below to apply for membership.</p>
          <p v-if="applicationStore.application?.review_notes" class="small">Note from the admins: {{ applicationStore.application.review_notes }}</p>
          <div class="row" style="margin-top: 10px">
            <button class="button water" type="button" :disabled="authUser.signingOut" @click="signOut">{{ authUser.signingOut ? 'Signing out...' : 'Sign out' }}</button>
          </div>
        </div>

        <div v-if="isApprovedMember" class="auth-gate">
          <article class="card">
            <h3>Welcome, you're a WAAIS member</h3>
            <p>Your membership is active for {{ authUser.user?.email }}. Head to your dashboard to manage your profile and startup listings, or join the conversation on the forum.</p>
            <div class="row" style="margin-top: 14px">
              <RouterLink class="button primary" to="/app/dashboard">Open dashboard</RouterLink>
              <a class="button water" href="https://forum.whartonai.studio">Visit the forum</a>
              <button class="button water" type="button" :disabled="authUser.signingOut" @click="signOut">{{ authUser.signingOut ? 'Signing out...' : 'Sign out' }}</button>
            </div>
          </article>
        </div>

        <div v-if="showSessionError" class="notice error-notice" style="margin-top: 20px">
          <p class="small">Could not check your sign-in session. Confirm the backend is running, then reload this page.</p>
        </div>

        <div v-if="applicationStore.error" class="notice error-notice" style="margin-top: 20px">
          <p class="small">Could not load your application. Confirm the backend is running, then reload this page.</p>
        </div>

        <div v-if="checkingSession" class="auth-gate">
          <article class="card">
            <h3>Looking for an active session</h3>
            <p>The application form appears after WAAIS confirms your identity.</p>
          </article>
        </div>

        <div v-else-if="awaitingVerification" class="notice" style="margin-top: 20px">
          <p class="small"><strong>Verify your email to continue.</strong> We sent a verification link<span v-if="verificationEmail"> to {{ verificationEmail }}</span>. Click it to unlock the membership application form. In local development, the link is written to the Laravel log.</p>
          <div class="row" style="margin-top: 10px">
            <button class="button water" type="button" :disabled="authUser.resendingVerification" @click="resendVerification">{{ authUser.resendingVerification ? 'Sending...' : 'Resend verification email' }}</button>
            <button class="button water" type="button" :disabled="authUser.signingOut" @click="signOut">{{ authUser.signingOut ? 'Signing out...' : 'Use a different account' }}</button>
          </div>
          <p v-if="authUser.verificationResent" class="small" style="margin-top: 8px">Verification email sent again.</p>
        </div>

        <div v-else-if="isAnonymous" class="auth-gate">
          <article class="card">
            <h3>Create your account</h3>
            <p>After verifying your email you will complete the membership application, which our admins review.</p>
            <form class="compact-auth-form" @submit.prevent="register">
              <label>First name<input v-model="registerForm.first_name" required autocomplete="given-name" placeholder="First name" :disabled="authUser.registering" /></label>
              <label>Last name<input v-model="registerForm.last_name" required autocomplete="family-name" placeholder="Last name" :disabled="authUser.registering" /></label>
              <label>Email<input v-model="registerForm.email" required type="email" autocomplete="email" placeholder="you@example.com" :disabled="authUser.registering" /></label>
              <label>Password<input v-model="registerForm.password" required type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" :disabled="authUser.registering" /></label>
              <label>Confirm password<input v-model="registerForm.password_confirmation" required type="password" autocomplete="new-password" minlength="8" placeholder="Repeat password" :disabled="authUser.registering" /></label>
              <button class="button primary" type="submit" :disabled="!canRegister">{{ authUser.registering ? 'Creating account...' : 'Create account' }}</button>
            </form>
            <div v-if="Object.keys(registerErrors).length" class="notice error-notice" style="margin-top: 14px">
              <p v-for="(messages, field) in registerErrors" :key="field" class="small">{{ messages[0] }}</p>
            </div>
            <div class="row" style="margin-top: 14px">
              <button class="button water" type="button" @click="authUser.startGoogleSignIn({ next: '/membership' })">Continue with Google</button>
            </div>
          </article>
        </div>

        <form v-else-if="showApplicationForm" class="application-form" @submit.prevent="submitApplication">
          <label>Email *<input v-model="form.email" required type="email" autocomplete="email" placeholder="you@example.com" :disabled="!canEditFields" /></label>
          <label>Phone associated with WhatsApp account (optional)<input v-model="form.phone_whatsapp" placeholder="Only if you want to join the WhatsApp community" :disabled="!canEditFields" /></label>
          <label>First name *<input v-model="form.first_name" required placeholder="First name" :disabled="!canEditFields" /></label>
          <label>Last name *<input v-model="form.last_name" required placeholder="Last name" :disabled="!canEditFields" /></label>
          <label>Affiliation type
            <select v-model="form.affiliation_type" :disabled="!canEditFields">
              <option value="alumni">Alumni</option>
              <option value="student">Student</option>
              <option value="faculty_staff">Faculty/staff</option>
              <option value="partner_guest">Partner guest</option>
              <option value="other">Other</option>
            </select>
          </label>
          <label>Are you an alumnus/a? *
            <select v-model="form.is_alumnus" :disabled="!canEditFields">
              <option :value="true">Yes</option>
              <option :value="false">No</option>
            </select>
          </label>
          <label>School affiliation<input v-model="form.school_affiliation" placeholder="School, program, student/faculty/staff status, or other affiliation" :disabled="!canEditFields" /></label>
          <label>Graduation year<input v-model="form.graduation_year" type="number" min="1800" max="2100" placeholder="e.g. 2020" :disabled="!canEditFields" /></label>
          <label>Primary location<input v-model="form.primary_location" placeholder="City, region, or country" :disabled="!canEditFields" /></label>
          <label>Secondary location<input v-model="form.secondary_location" placeholder="Optional" :disabled="!canEditFields" /></label>
          <label>LinkedIn profile<input v-model="form.linkedin_url" type="url" placeholder="https://www.linkedin.com/in/..." :disabled="!canEditFields" /></label>
          <label>Age (optional)<input v-model="form.age" type="number" min="13" max="120" placeholder="Optional" :disabled="!canEditFields" /></label>
          <label class="full">If invited by a Wharton/Penn alumnus/a, provide their name<input v-model="form.inviter_name" placeholder="Inviter name, if applicable" :disabled="!canEditFields" /></label>
          <label class="full">Experience: industries and roles<textarea v-model="form.experience_summary" placeholder="Tell us about industries you worked in and roles you held." :disabled="!canEditFields" /></label>
          <label class="full">Expertise<textarea v-model="form.expertise_summary" placeholder="Tell us about your expertise." :disabled="!canEditFields" /></label>
          <label class="full">Industries where you would like to add value<textarea v-model="form.industries_to_add_value" placeholder="Comma-separated, e.g. Finance, AI Engineering" :disabled="!canEditFields" /></label>
          <label class="full">Industries where you want to extend your expertise<textarea v-model="form.industries_to_extend_expertise" placeholder="Comma-separated, e.g. Healthcare, Education" :disabled="!canEditFields" /></label>
          <label class="full">Availability<textarea v-model="form.availability" placeholder="How much time per month can you dedicate to a potential future project?" :disabled="!canEditFields" /></label>
          <label>Gender (optional)<input v-model="form.gender" placeholder="Optional" :disabled="!canEditFields" /></label>

          <label v-if="requiresPrivacyAcknowledgement" class="checkbox-row full privacy-consent">
            <input v-model="form.privacy_acknowledgement" type="checkbox" :disabled="applicationStore.saving || applicationStore.loading" />
            <span>
              I agree that Wharton Alumni AI Studio and Research Center may process my information to review membership, operate the community, provide member services, send WAAIS-related communications, and maintain platform security and moderation records.
              <RouterLink to="/legal">Privacy details</RouterLink>
            </span>
          </label>
          <div v-else-if="applicationStore.application?.privacy_acknowledged_at" class="notice full">
            <p class="small">Privacy acknowledgement recorded on {{ new Date(applicationStore.application.privacy_acknowledged_at).toLocaleDateString() }}.</p>
          </div>

          <div v-if="Object.keys(validationErrors).length" class="notice error-notice full">
            <p v-for="(messages, field) in validationErrors" :key="field" class="small">{{ messages[0] }}</p>
          </div>

          <div class="row full">
            <button class="button primary" type="submit" :disabled="!canSubmit">{{ saveLabel }}</button>
          </div>
        </form>
      </div>
    </section>
  </PublicLayout>
</template>
