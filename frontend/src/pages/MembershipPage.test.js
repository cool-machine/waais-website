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
      { path: '/legal', component: { template: '<div />' } },
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

describe('membership application privacy acknowledgement', () => {
  it('requires privacy acknowledgement before submitting a new application', async () => {
    const fetchMock = vi.fn((url, options = {}) => {
      if (url.includes('/api/user')) {
        return Promise.resolve(jsonResponse({
          id: 1,
          name: 'Ada Lovelace',
          email: 'ada@example.com',
          approval_status: 'submitted',
          permission_role: 'pending_user',
          affiliation_type: 'alumni',
        }))
      }
      if (url.includes('/api/membership-application') && options.method === 'GET') {
        return Promise.resolve(jsonResponse({ data: null }))
      }
      if (url.includes('/api/membership-application') && options.method === 'POST') {
        return Promise.resolve(jsonResponse({
          data: {
            id: 10,
            approval_status: 'submitted',
            email: 'ada@example.com',
            first_name: 'Ada',
            last_name: 'Lovelace',
            privacy_acknowledged_at: '2026-05-02T20:37:00.000000Z',
          },
        }, { status: 201 }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountMembershipPage()
    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('I agree that Wharton Alumni AI Studio and Research Center may process my information')

    await wrapper.find('input[type="checkbox"]').setValue(true)
    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeUndefined()

    await wrapper.find('form.application-form').trigger('submit')
    await flushPromises()

    const request = fetchMock.mock.calls.find(([url, options]) => url.includes('/api/membership-application') && options?.method === 'POST')
    expect(JSON.parse(request[1].body)).toMatchObject({
      email: 'ada@example.com',
      first_name: 'Ada',
      last_name: 'Lovelace',
      privacy_acknowledgement: true,
    })
  })
})
