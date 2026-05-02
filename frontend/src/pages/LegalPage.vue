<script setup>
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Legal" title="Privacy, cookies, and data requests." lede="How WAAIS handles membership data, platform cookies, and access or deletion requests." />
    <section class="section paper">
      <div class="section-inner">
        <div class="grid">
          <article class="card">
            <h3>Privacy Policy</h3>
            <p>Wharton Alumni AI Studio and Research Center collects account, application, profile, event, announcement, and moderation data to review membership, operate the community, manage events and communications, and protect platform security.</p>
            <p>Primary production data is planned to be hosted in Azure's Europe geography, with West Europe as the default region. Members may access WAAIS globally, including from the United States, India, China, and other countries.</p>
          </article>
          <article class="card">
            <h3>Cookie Policy</h3>
            <p>WAAIS uses essential cookies and browser storage for sign-in, session security, CSRF protection, and application functionality. The platform does not require advertising cookies for launch.</p>
            <p>Google OAuth and future Discourse access may set their own cookies when you use those services. Their processing is governed by their respective service terms and privacy notices.</p>
          </article>
          <article class="card">
            <h3>Data Requests</h3>
            <p>You may request access, correction, or deletion of personal data associated with your WAAIS account or membership application. Some audit, moderation, security, and legal records may need to be retained where required for platform integrity.</p>
            <p>Use an organization-controlled privacy mailbox such as <a href="mailto:privacy@whartonai.studio">privacy@whartonai.studio</a>. Until that mailbox is confirmed, contact the WAAIS administrators through the contact page.</p>
          </article>
        </div>

      </div>
    </section>
  </PublicLayout>
</template>
