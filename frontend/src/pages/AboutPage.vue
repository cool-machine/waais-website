<script setup>
import { RouterLink } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <PageHero
      compact
      title="About the Wharton Alumni AI Studio."
      lede="Connecting Wharton alumni, entrepreneurs, investors, and researchers around real-world AI since 2020."
    />

    <section class="section paper">
      <div class="section-inner prose">
        <h2>Where we started.</h2>
        <p>
          The Wharton Alumni AI Studio (WAAIS) began in 2020, in collaboration with the Wharton Club
          of the United Kingdom, to strengthen the connections between business professionals, domain
          experts, and researchers in artificial intelligence — starting with a series of AI-focused
          events.
        </p>
        <p>
          In 2023 we launched the Wharton AI Studio Expert Network, a community of professionals who
          share an interest in the deep-tech ecosystem.
        </p>

        <h2>What we do.</h2>
        <p>
          Our purpose is to help Wharton alumni turn their business expertise into something valuable
          in the world of AI: launching new projects and startups alongside AI professionals and
          student founders, taking part in AI Studio think tanks, and exchanging on how the knowledge
          of Wharton alumni can move the deep-tech field forward.
        </p>
        <p>
          Our discussions span fintech, supply chain, marketing, and product design, and we have
          developed domain-specific approaches to applied AI. Collaborative ventures have been
          supported through government grants and the Wharton Alumni Angels.
        </p>

        <h2>Where we're going.</h2>
        <p>
          AI Studio wants to go a step further — bringing tech experts, industry professionals,
          investors, entrepreneurs, and future leaders closer together to exchange knowledge,
          capabilities, skills, and ideas about how to disrupt today's business models. We want to be
          a driving force of change, and we believe we can do that by deepening the connections
          between like-minded alumni.
        </p>
        <p>
          WAAIS was founded by George Gvishiani, founder of the Wharton Alumni AI Studio and
          co-founder of the Wharton AI Studio Expert Network.
        </p>
      </div>
    </section>

    <section class="section navy-band">
      <div class="section-inner">
        <div class="section-head">
          <div>
            <h2>Join the community.</h2>
            <p class="lede">If you'd like to be part of what we're building, we'd love to hear from you.</p>
          </div>
          <RouterLink class="button primary" to="/membership">Become a member</RouterLink>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
