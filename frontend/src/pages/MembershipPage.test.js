import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import MembershipPage from './MembershipPage.vue'
import { useAuthUserStore } from '../stores/authUser'

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

describe('staged registration flow', () => {
  it('shows only the account-creation card to anonymous visitors and registers', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) {
        return Promise.resolve(jsonResponse({ message: 'Unauthenticated.' }, { status: 401 }))
      }
      if (url.includes('/api/auth/register')) {
        return Promise.resolve(jsonResponse({ ok: true, verification_required: true }, { status: 201 }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountMembershipPage()

    // No application form and no sign-in option on this page.
    expect(wrapper.find('form.application-form').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Already registered')

    const card = wrapper.find('form.compact-auth-form')
    expect(card.exists()).toBe(true)

    const textInputs = card.findAll('input:not([type="email"]):not([type="password"])')
    await textInputs[0].setValue('Grace')
    await textInputs[1].setValue('Hopper')
    await card.find('input[type="email"]').setValue('newbie@example.com')
    const passwordInputs = card.findAll('input[type="password"]')
    await passwordInputs[0].setValue('super-secret-pw')
    await passwordInputs[1].setValue('super-secret-pw')

    await card.trigger('submit')
    await flushPromises()

    const request = fetchMock.mock.calls.find(([url]) => url.includes('/api/auth/register'))
    expect(request[1].method).toBe('POST')
    expect(JSON.parse(request[1].body)).toEqual({
      first_name: 'Grace',
      last_name: 'Hopper',
      email: 'newbie@example.com',
      password: 'super-secret-pw',
      password_confirmation: 'super-secret-pw',
    })
    expect(wrapper.text()).toContain('Verify your email')
    expect(wrapper.find('form.application-form').exists()).toBe(false)
  })

  it('keeps the application form hidden until the email is verified', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) {
        return Promise.resolve(jsonResponse({
          id: 7,
          name: 'Ada Lovelace',
          email: 'ada@example.com',
          email_verified: false,
          approval_status: 'draft',
          permission_role: 'pending_user',
        }))
      }
      if (url.includes('/api/membership-application')) {
        return Promise.resolve(jsonResponse({ data: null }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountMembershipPage()

    expect(wrapper.find('form.application-form').exists()).toBe(false)
    expect(wrapper.text()).toContain('Verify your email')
    expect(wrapper.text()).toContain('Resend verification email')
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
          email_verified: true,
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

describe('header sign-out clears member state', () => {
  it('hides the approved-member card after a sign-out that only clears the auth store', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/logout')) return Promise.resolve(jsonResponse({ ok: true }))
      if (url.includes('/api/user')) {
        return Promise.resolve(jsonResponse({
          id: 5,
          name: 'Ada Lovelace',
          email: 'ada@example.com',
          email_verified: true,
          approval_status: 'approved',
          permission_role: 'member',
          affiliation_type: 'alumni',
          can_access_member_areas: true,
        }))
      }
      if (url.includes('/api/membership-application')) {
        return Promise.resolve(jsonResponse({
          data: { approval_status: 'approved', email: 'ada@example.com', first_name: 'Ada', last_name: 'Lovelace' },
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountMembershipPage()

    // Approved-member card is showing.
    expect(wrapper.text()).toContain("Welcome, you're a WAAIS member.")
    expect(wrapper.text()).toContain('Open dashboard')

    // The shared header's Sign out only calls authUser.signOut(); it does not
    // touch this page's application store. The page must still reset.
    await useAuthUserStore().signOut()
    await flushPromises()

    expect(wrapper.text()).not.toContain("Welcome, you're a WAAIS member.")
    expect(wrapper.text()).not.toContain('Open dashboard')
    // Falls back to the anonymous account-creation card.
    expect(wrapper.find('form.compact-auth-form').exists()).toBe(true)
  })
})
