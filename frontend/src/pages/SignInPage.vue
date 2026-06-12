<script setup>
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { useAuthUserStore } from '../stores/authUser'

const router = useRouter()
const authUser = useAuthUserStore()

const form = reactive({
  email: '',
  password: '',
})

// 'sign-in' shows the credentials form; 'forgot' shows the reset-request view.
const mode = ref('sign-in')

const loginErrors = computed(() => authUser.loginError ? (authUser.loginError.body?.errors ?? { general: [authUser.loginError.body?.message || 'Could not sign in. Please try again.'] }) : {})
const startGoogleSignIn = () => authUser.startGoogleSignIn({ next: '/membership' })

function showForgotPassword() {
  authUser.loginError = null
  authUser.passwordResetRequested = false
  mode.value = 'forgot'
}

function showSignIn() {
  authUser.passwordResetRequested = false
  mode.value = 'sign-in'
}

async function signIn() {
  const response = await authUser.login({ email: form.email, password: form.password })
  form.password = ''
  // Resume an interrupted forum (Discourse SSO) sign-in if one is pending.
  if (response?.redirect) {
    window.location.assign(response.redirect)
    return
  }
  router.push('/membership')
}

async function sendResetLink() {
  await authUser.requestPasswordReset(form.email.trim())
}
</script>

<template>
  <PublicLayout>
    <PageHero
      compact
      eyebrow="Members"
      :title="mode === 'forgot' ? 'Reset your password.' : 'Sign in.'"
      :lede="mode === 'forgot' ? 'Enter your account email and we will send you a link to choose a new password.' : 'Access your member dashboard, application status, and startup listings.'"
    />
    <section class="section paper">
      <div class="section-inner">
        <div class="auth-gate">
          <article v-if="mode === 'sign-in'" class="card">
            <span class="tag">Member sign in</span>
            <h3>Welcome back.</h3>
            <form class="compact-auth-form" @submit.prevent="signIn">
              <label>Email<input v-model="form.email" required type="email" autocomplete="email" placeholder="you@example.com" :disabled="authUser.loggingIn" /></label>
              <label>Password<input v-model="form.password" required type="password" autocomplete="current-password" placeholder="Your password" :disabled="authUser.loggingIn" /></label>
              <button class="button primary" type="submit" :disabled="authUser.loggingIn">{{ authUser.loggingIn ? 'Signing in...' : 'Sign in' }}</button>
            </form>
            <div v-if="Object.keys(loginErrors).length" class="notice error-notice" style="margin-top: 14px">
              <p v-for="(messages, field) in loginErrors" :key="field" class="small">{{ messages[0] }}</p>
              <p class="small"><a href="#" @click.prevent="showForgotPassword">Forgot your password? Reset it here.</a></p>
            </div>
            <div class="row" style="margin-top: 14px">
              <button class="button water" type="button" @click="startGoogleSignIn">Continue with Google</button>
              <button class="button water" type="button" @click="showForgotPassword">Forgot password?</button>
            </div>
            <p class="small" style="margin-top: 14px">New to WAAIS? <RouterLink to="/membership">Become a member</RouterLink>.</p>
          </article>

          <article v-else class="card">
            <span class="tag">Password reset</span>
            <h3>Get a reset link by email.</h3>
            <div v-if="authUser.passwordResetRequested" class="notice" style="margin-top: 14px">
              <p class="small"><strong>Reset link on its way.</strong> If an account exists for {{ form.email }}, you'll receive an email shortly. Open the link inside to choose a new password.</p>
              <p class="small">No email after a few minutes? Check spam, or the address may not be registered — you can <RouterLink to="/membership">create an account</RouterLink>.</p>
            </div>
            <form v-else class="compact-auth-form" @submit.prevent="sendResetLink">
              <label>Email<input v-model="form.email" required type="email" autocomplete="email" placeholder="you@example.com" :disabled="authUser.passwordResetRequesting" /></label>
              <button class="button primary" type="submit" :disabled="authUser.passwordResetRequesting || form.email.trim() === ''">{{ authUser.passwordResetRequesting ? 'Sending...' : 'Email me a reset link' }}</button>
            </form>
            <div class="row" style="margin-top: 14px">
              <button class="button water" type="button" @click="showSignIn">Back to sign in</button>
            </div>
          </article>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
