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
const forgotPasswordNeedsEmail = ref(false)

const loginErrors = computed(() => authUser.loginError ? (authUser.loginError.body?.errors ?? { general: [authUser.loginError.body?.message || 'Could not sign in. Please try again.'] }) : {})
const startGoogleSignIn = () => authUser.startGoogleSignIn({ next: '/membership' })

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

async function forgotPassword() {
  if (form.email.trim() === '') {
    forgotPasswordNeedsEmail.value = true
    return
  }
  forgotPasswordNeedsEmail.value = false
  await authUser.requestPasswordReset(form.email.trim())
}
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Members" title="Sign in." lede="Access your member dashboard, application status, and startup listings." />
    <section class="section paper">
      <div class="section-inner">
        <div class="auth-gate">
          <article class="card">
            <span class="tag">Member sign in</span>
            <h3>Welcome back.</h3>
            <form class="compact-auth-form" @submit.prevent="signIn">
              <label>Email<input v-model="form.email" required type="email" autocomplete="email" placeholder="you@example.com" :disabled="authUser.loggingIn" /></label>
              <label>Password<input v-model="form.password" required type="password" autocomplete="current-password" placeholder="Your password" :disabled="authUser.loggingIn" /></label>
              <button class="button primary" type="submit" :disabled="authUser.loggingIn">{{ authUser.loggingIn ? 'Signing in...' : 'Sign in' }}</button>
            </form>
            <div v-if="Object.keys(loginErrors).length" class="notice error-notice" style="margin-top: 14px">
              <p v-for="(messages, field) in loginErrors" :key="field" class="small">{{ messages[0] }}</p>
            </div>
            <div class="row" style="margin-top: 14px">
              <button class="button water" type="button" @click="startGoogleSignIn">Continue with Google</button>
              <button class="button water" type="button" :disabled="authUser.passwordResetRequesting" @click="forgotPassword">{{ authUser.passwordResetRequesting ? 'Sending...' : 'Forgot password?' }}</button>
            </div>
            <div v-if="authUser.passwordResetRequested" class="notice" style="margin-top: 14px">
              <p class="small">If an account exists for that email, a password reset link is on its way. In local development, it is written to the Laravel log.</p>
            </div>
            <p v-if="forgotPasswordNeedsEmail" class="small" style="margin-top: 10px">Enter your email above first, then press "Forgot password?".</p>
            <p class="small" style="margin-top: 14px">New to WAAIS? <RouterLink to="/membership">Become a member</RouterLink>.</p>
          </article>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
