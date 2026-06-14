<script setup>
import { computed } from 'vue'

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  meta: { type: String, default: '' },
  image: { type: String, default: '' },
  imageAlt: { type: String, default: '' },
  // When true, cards without an image show a clean monogram tile instead of nothing.
  logoFallback: { type: Boolean, default: false },
})

const hasTile = computed(() => Boolean(props.image) || props.logoFallback)

const initials = computed(() => {
  const words = (props.title || '').trim().split(/\s+/).filter(Boolean)
  if (!words.length) return '•'
  if (words.length === 1) return words[0].slice(0, 1).toUpperCase()
  return (words[0][0] + words[1][0]).toUpperCase()
})
</script>

<template>
  <article class="card" :class="{ 'image-card': hasTile }">
    <img v-if="image" :src="image" :alt="imageAlt || title">
    <div v-else-if="logoFallback" class="logo-fallback" aria-hidden="true">{{ initials }}</div>
    <div class="card-body">
      <span v-if="eyebrow" class="tag">{{ eyebrow }}</span>
      <h3>{{ title }}</h3>
      <p v-if="meta" class="meta">{{ meta }}</p>
      <p class="small"><slot /></p>
      <div class="card-actions"><slot name="actions" /></div>
    </div>
  </article>
</template>
