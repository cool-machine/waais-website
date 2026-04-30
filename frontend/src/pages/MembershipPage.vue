<script setup>
import { RouterLink } from 'vue-router'
import PageHero from '../components/PageHero.vue'
import PublicLayout from '../components/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <PageHero compact eyebrow="Membership" title="Become a WAAIS member." lede="Existing members sign in. New applicants apply. Non-members can still propose topics, partnerships, and listings." />
    <section class="section paper">
      <div class="section-inner">
        <div class="grid two">
          <article class="card">
            <span class="tag">Existing members</span>
            <h3>Sign in to your account</h3>
            <p>Google verifies identity first. WAAIS then routes approved members, admins, pending users, or unknown applicants.</p>
            <RouterLink class="button water" to="/app/sign-in">Member sign in</RouterLink>
          </article>
          <article class="card">
            <span class="tag">New applicants</span>
            <h3>Apply for membership</h3>
            <p>This first Vue scaffold preserves the approved questionnaire. Backend submission and audit history come in Laravel.</p>
          </article>
        </div>

        <div class="notice" style="margin-top: 20px">
          <p class="small">Implementation note: backend should separate approval status, affiliation type, and permission role. Applicants can edit answers after submission, but cannot delete their application. Legal identity fields require admin review after verification.</p>
        </div>

        <form class="application-form">
          <label>Email *<input placeholder="you@example.com" /></label>
          <label>Phone associated with WhatsApp account (optional)<input placeholder="Only if you want to join the WhatsApp community" /></label>
          <label>First name *<input placeholder="First name" /></label>
          <label>Last name *<input placeholder="Last name" /></label>
          <label>Are you an alumnus/a? *<select><option>Yes</option><option>No</option></select></label>
          <label>School affiliation *<input placeholder="School, program, student/faculty/staff status, or other affiliation" /></label>
          <label>Graduation year *<input placeholder="e.g. 2020, WG'20, W'26" /></label>
          <label>Primary location *<input placeholder="City, region, or country" /></label>
          <label>Secondary location<input placeholder="Optional" /></label>
          <label>LinkedIn profile *<input placeholder="linkedin.com/in/..." /></label>
          <label class="full">If invited by a Wharton/Penn alumnus/a, provide their name<input placeholder="Inviter name, if applicable" /></label>
          <label class="full">Experience: industries and roles *<textarea placeholder="Tell us about industries you worked in and roles you held." /></label>
          <label class="full">Expertise *<textarea placeholder="Tell us about your expertise." /></label>
          <label class="full">Industries where you would like to add value *<textarea placeholder="Which industries would you like to contribute to?" /></label>
          <label class="full">Industries where you want to extend your expertise *<textarea placeholder="Which industries are you interested in extending your expertise to?" /></label>
          <label class="full">Availability *<textarea placeholder="How much time per month can you dedicate to a potential future project?" /></label>
          <label>Gender (optional)<select><option>Prefer not to say</option><option>Male</option><option>Female</option><option>Other</option></select></label>
          <label>Age (optional)<select><option>Prefer not to say</option><option>20s</option><option>30s</option><option>40s</option><option>50s</option><option>60s</option><option>70s+</option></select></label>
          <button class="button primary" type="button">Submit application</button>
        </form>

        <div class="grid" style="margin-top: 24px">
          <article class="card">
            <span class="tag">Non-members</span>
            <h3>Propose a topic</h3>
            <p>Suggest a founder salon, workshop, or focused AI adoption roundtable.</p>
            <RouterLink class="button water" to="/contact">Propose topic</RouterLink>
          </article>
          <article class="card">
            <span class="tag">Partners</span>
            <h3>Partner with us</h3>
            <p>Sponsor events, provide credits, or collaborate on member education.</p>
            <RouterLink class="button water" to="/contact">Partner with us</RouterLink>
          </article>
          <article class="card">
            <span class="tag">Founders</span>
            <h3>List a startup</h3>
            <p>Startup listings are reviewed and published by admins after member access is confirmed.</p>
            <RouterLink class="button water" to="/contact">Request listing</RouterLink>
          </article>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
