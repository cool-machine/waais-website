<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  meta: { type: String, default: '' },
  image: { type: String, default: '' },
  imageAlt: { type: String, default: '' },
  // When true, cards without an image show a clean monogram tile instead of nothing.
  logoFallback: { type: Boolean, default: false },
})

const broken = ref(false)
const showImage = computed(() => Boolean(props.image) && !broken.value)
const showMonogram = computed(() => !showImage.value && (props.logoFallback || broken.value))
const hasTile = computed(() => showImage.value || showMonogram.value)

const initials = computed(() => {
  const words = (props.title || '').trim().split(/\s+/).filter(Boolean)
  if (!words.length) return '•'
  if (words.length === 1) return words[0].slice(0, 1).toUpperCase()
  return (words[0][0] + words[1][0]).toUpperCase()
})
</script>

<template>
  <article class="card info-card" :class="{ 'image-card': hasTile }">
    <div v-if="hasTile" class="logo-tile">
      <img v-if="showImage" class="logo-mark" :src="image" :alt="imageAlt || title" @error="broken = true">
      <span v-else class="logo-monogram" aria-hidden="true">{{ initials }}</span>
    </div>
    <div class="card-body">
      <span v-if="eyebrow" class="tag">{{ eyebrow }}</span>
      <h3>{{ title }}</h3>
      <p v-if="meta" class="meta">{{ meta }}</p>
      <p class="small"><slot /></p>
      <div class="card-actions"><slot name="actions" /></div>
    </div>
  </article>
</template>
