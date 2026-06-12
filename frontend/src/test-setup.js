// Global vitest setup. A fake XSRF-TOKEN cookie is pre-set so sendJson's
// Sanctum csrf-cookie bootstrap (which only fires when the cookie is
// absent) is skipped, keeping fetch-mock call ordering stable in tests.
// api.test.js exercises the bootstrap explicitly with its own cookie stubs.
beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test-xsrf-token'
})
