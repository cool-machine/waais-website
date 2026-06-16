<script setup>
import { computed, reactive } from 'vue'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
import { useContactMessageStore } from '../stores/contactMessage'

const contactStore = useContactMessageStore()

const form = reactive({
  name: '',
  email: '',
  topic: 'Membership',
  message: '',
})

const canSubmit = computed(() =>
  !contactStore.sending
  && form.name.trim() !== ''
  && form.email.trim() !== ''
  && form.message.trim() !== '',
)
const errors = computed(() => contactStore.error ? (contactStore.error.body?.errors ?? { general: [contactStore.error.body?.message || 'Could not send your message. Please try again.'] }) : {})

async function submit() {
  await contactStore.send({
    name: form.name.trim(),
    email: form.email.trim(),
    topic: form.topic,
    message: form.message.trim(),
  })
  form.message = ''
}
</script>

<template>
  <PublicLayout>
    <PageHero compact title="Reach the WAAIS team" lede="Questions about membership, events, partnerships, or anything else — send us a message and we'll reply by email." />
    <section class="section paper">
      <div class="section-inner">
        <div v-if="contactStore.sent" class="notice" style="margin-top: 20px">
          <p class="small"><strong>Message sent.</strong> Thanks for reaching out — we'll get back to you at the email address you provided.</p>
        </div>

        <form v-else class="application-form" @submit.prevent="submit">
          <label>Name *<input v-model="form.name" required autocomplete="name" placeholder="Your name" :disabled="contactStore.sending" /></label>
          <label>Email *<input v-model="form.email" required type="email" autocomplete="email" placeholder="you@example.com" :disabled="contactStore.sending" /></label>
          <label>Topic
            <select v-model="form.topic" :disabled="contactStore.sending">
              <option>Membership</option>
              <option>Events</option>
              <option>Partnership</option>
              <option>Support</option>
            </select>
          </label>
          <label class="full">Message *<textarea v-model="form.message" required placeholder="How can we help?" :disabled="contactStore.sending" /></label>

          <div v-if="Object.keys(errors).length" class="notice error-notice full">
            <p v-for="(messages, field) in errors" :key="field" class="small">{{ messages[0] }}</p>
          </div>

          <button class="button primary" type="submit" :disabled="!canSubmit">{{ contactStore.sending ? 'Sending...' : 'Send message' }}</button>
        </form>
      </div>
    </section>
  </PublicLayout>
</template>
