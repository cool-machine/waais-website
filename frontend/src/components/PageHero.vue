<script setup>
defineProps({
  eyebrow: { type: String, required: true },
  title: { type: String, required: true },
  lede: { type: String, default: '' },
  compact: { type: Boolean, default: false },
  videoSrc: { type: String, default: '' },
  poster: { type: String, default: '' },
})
</script>

<template>
  <section class="hero" :class="{ compact, 'has-video': videoSrc }">
    <video
      v-if="videoSrc"
      class="hero-video"
      autoplay
      muted
      loop
      playsinline
      preload="metadata"
      :poster="poster"
      aria-hidden="true"
    >
      <source :src="videoSrc" type="video/mp4">
    </video>
    <div class="section-inner hero-inner">
      <div class="hero-copy">
        <p class="eyebrow">{{ eyebrow }}</p>
        <h1>{{ title }}</h1>
        <p v-if="lede" class="lede">{{ lede }}</p>
        <slot />
      </div>
      <slot name="aside" />
    </div>
  </section>
</template>
