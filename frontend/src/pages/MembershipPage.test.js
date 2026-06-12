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

describe('anonymous registration', () => {
  it('registers a new account from the combined application form', async () => {
    const fetchMock = vi.fn((url, options = {}) => {
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

    // Anonymous visitors see the sign-in card and the application form with password fields.
    expect(wrapper.find('form.compact-auth-form').exists()).toBe(true)
    const appForm = wrapper.find('form.application-form')
    expect(appForm.exists()).toBe(true)

    await appForm.find('input[type="email"]').setValue('newbie@example.com')
    const passwordInputs = appForm.findAll('input[type="password"]')
    expect(passwordInputs).toHaveLength(2)
    await passwordInputs[0].setValue('super-secret-pw')
    await passwordInputs[1].setValue('super-secret-pw')
    await appForm.findAll('input:not([type="email"]):not([type="password"]):not([type="checkbox"]):not([type="number"])')[1].setValue('Grace')
    await appForm.findAll('input:not([type="email"]):not([type="password"]):not([type="checkbox"]):not([type="number"])')[2].setValue('Hopper')
    await appForm.find('input[type="checkbox"]').setValue(true)

    await appForm.trigger('submit')
    await flushPromises()

    const request = fetchMock.mock.calls.find(([url]) => url.includes('/api/auth/register'))
    expect(request[1].method).toBe('POST')
    expect(JSON.parse(request[1].body)).toMatchObject({
      email: 'newbie@example.com',
      first_name: 'Grace',
      last_name: 'Hopper',
      password: 'super-secret-pw',
      password_confirmation: 'super-secret-pw',
      privacy_acknowledgement: true,
    })
    expect(wrapper.text()).toContain('verify your email')
  })

  it('signs in an existing account with email and password', async () => {
    let authenticated = false
    const fetchMock = vi.fn((url, options = {}) => {
      if (url.includes('/api/user')) {
        return authenticated
          ? Promise.resolve(jsonResponse({ id: 7, name: 'Ada Lovelace', email: 'ada@example.com', approval_status: 'submitted', permission_role: 'pending_user' }))
          : Promise.resolve(jsonResponse({ message: 'Unauthenticated.' }, { status: 401 }))
      }
      if (url.includes('/api/auth/login')) {
        authenticated = true
        return Promise.resolve(jsonResponse({ ok: true, email_verified: true }))
      }
      if (url.includes('/api/membership-application')) {
        return Promise.resolve(jsonResponse({ data: null }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountMembershipPage()
    const signInCard = wrapper.find('form.compact-auth-form')

    await signInCard.find('input[type="email"]').setValue('ada@example.com')
    await signInCard.find('input[type="password"]').setValue('my-password-123')
    await signInCard.trigger('submit')
    await flushPromises()

    const request = fetchMock.mock.calls.find(([url]) => url.includes('/api/auth/login'))
    expect(request[1].method).toBe('POST')
    expect(JSON.parse(request[1].body)).toEqual({ email: 'ada@example.com', password: 'my-password-123' })
    expect(wrapper.text()).toContain('ada@example.com')
    expect(wrapper.find('form.compact-auth-form').exists()).toBe(false)
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
