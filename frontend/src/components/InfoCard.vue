<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  meta: { type: String, default: '' },
  image: { type: String, default: '' },
  imageAlt: { type: String, default: '' },
})
</script>

<template>
  <article class="card" :class="{ 'image-card': image }">
    <img v-if="image" :src="image" :alt="imageAlt || title">
    <div class="card-body">
      <span v-if="eyebrow" class="tag">{{ eyebrow }}</span>
      <h3>{{ title }}</h3>
      <p v-if="meta" class="meta">{{ meta }}</p>
      <p class="small"><slot /></p>
      <slot name="actions" />
    </div>
  </article>
</template>
