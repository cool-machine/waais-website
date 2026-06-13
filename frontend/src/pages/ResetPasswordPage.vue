<script setup>
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { useAuthUserStore } from '../stores/authUser'

const route = useRoute()
const authUser = useAuthUserStore()

const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
})
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const succeeded = ref(false)

const hasToken = computed(() => token.value !== '')
const canSubmit = computed(() =>
  !authUser.passwordResetting
  && hasToken.value
  && form.email !== ''
  && form.password.length >= 8
  && form.password === form.password_confirmation,
)
const errors = computed(() => authUser.passwordResetError ? (authUser.passwordResetError.body?.errors ?? { general: [authUser.passwordResetError.body?.message || 'Could not reset the password. Please try again.'] }) : {})

async function submit() {
  await authUser.resetPassword({
    email: form.email.trim(),
    token: token.value,
    password: form.password,
    password_confirmation: form.password_confirmation,
  })
  succeeded.value = true
  form.password = ''
  form.password_confirmation = ''
}
</script>

<template>
  <PublicLayout>
    <PageHero compact title="Reset your password." lede="Choose a new password for your WAAIS account." />
    <section class="section paper">
      <div class="section-inner">
        <div v-if="!hasToken" class="notice error-notice" style="margin-top: 20px">
          <p class="small">This reset link is missing its token. Request a new one from the <RouterLink to="/membership">membership page</RouterLink>.</p>
        </div>

        <div v-else-if="succeeded" class="notice" style="margin-top: 20px">
          <p class="small"><strong>Password updated.</strong> You can now sign in with your new password.</p>
          <div class="row" style="margin-top: 10px">
            <RouterLink class="button primary" to="/membership">Go to sign in</RouterLink>
          </div>
        </div>

        <form v-else class="application-form" @submit.prevent="submit">
          <label>Email *<input v-model="form.email" required type="email" autocomplete="email" placeholder="you@example.com" :disabled="authUser.passwordResetting" /></label>
          <label>New password *<input v-model="form.password" required type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" :disabled="authUser.passwordResetting" /></label>
          <label>Confirm new password *<input v-model="form.password_confirmation" required type="password" autocomplete="new-password" minlength="8" placeholder="Repeat password" :disabled="authUser.passwordResetting" /></label>

          <div v-if="Object.keys(errors).length" class="notice error-notice full">
            <p v-for="(messages, field) in errors" :key="field" class="small">{{ messages[0] }}</p>
          </div>

          <div class="row full">
            <button class="button primary" type="submit" :disabled="!canSubmit">{{ authUser.passwordResetting ? 'Saving...' : 'Set new password' }}</button>
          </div>
        </form>
      </div>
    </section>
  </PublicLayout>
</template>
