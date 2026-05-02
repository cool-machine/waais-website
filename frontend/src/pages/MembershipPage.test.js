import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import MembershipPage from './MembershipPage.vue'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

async function mountMembershipPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/contact', component: { template: '<div />' } },
      { path: '/membership', component: MembershipPage },
    ],
  })
  await router.push('/membership')
  await router.isReady()

  const pinia = createPinia()
  setActivePinia(pinia)

  const wrapper = mount(MembershipPage, {
    global: {
      plugins: [pinia, router],
    },
  })
  await flushPromises()
  return wrapper
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('email-link membership start', () => {
  it('lets signed-out applicants request an email sign-in link', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) {
        return Promise.resolve(jsonResponse({ message: 'Unauthenticated.' }, { status: 401 }))
      }
      if (url.includes('/api/auth/email-link')) {
        return Promise.resolve(jsonResponse({ ok: true }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountMembershipPage()

    await wrapper.find('input[type="email"]').setValue('applicant@example.com')
    await wrapper.find('form.compact-auth-form').trigger('submit')
    await flushPromises()

    const request = fetchMock.mock.calls.find(([url]) => url.includes('/api/auth/email-link'))
    expect(request[1].method).toBe('POST')
    expect(JSON.parse(request[1].body)).toEqual({
      email: 'applicant@example.com',
      next: '/membership',
    })
    expect(wrapper.text()).toContain('Check your email for a WAAIS sign-in link.')
  })
})
