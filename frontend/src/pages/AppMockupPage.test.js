import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import AppMockupPage from './AppMockupPage.vue'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const MEMBER = {
  id: 1,
  name: 'Grace Hopper',
  email: 'grace@example.com',
  approval_status: 'approved',
  affiliation_type: 'alumni',
  permission_role: 'member',
  can_access_member_areas: true,
  can_publish_public_content: false,
  can_manage_admin_privileges: false,
}

const APPLICATION = {
  id: 10,
  approval_status: 'submitted',
  affiliation_type: 'alumni',
  email: 'grace@example.com',
  first_name: 'Grace',
  last_name: 'Hopper',
  school_affiliation: 'Wharton MBA',
  graduation_year: 2020,
  primary_location: 'New York',
  experience_summary: 'Enterprise software',
  expertise_summary: 'AI systems',
  availability: 'Two hours per month',
}

async function mountAt(path) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/membership', component: { template: '<div />' } },
      { path: '/app/:view?', component: AppMockupPage },
    ],
  })
  await router.push(path)
  await router.isReady()

  const pinia = createPinia()
  setActivePinia(pinia)

  const wrapper = mount(AppMockupPage, {
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

describe('member dashboard live state', () => {
  it('renders the authenticated user and membership application status on dashboard', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(MEMBER))
      if (url.includes('/api/membership-application')) return Promise.resolve(jsonResponse({ data: APPLICATION }))
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/dashboard')

    expect(wrapper.text()).toContain('Welcome back, Grace Hopper.')
    expect(wrapper.text()).toContain('Approved member access')
    expect(wrapper.text()).toContain('Submitted')
    expect(wrapper.text()).toContain('Wharton MBA')
    expect(wrapper.text()).toContain('Update application')
    expect(wrapper.text()).not.toContain('Profile completion')
  })

  it('renders the profile view from current session and application fields', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(MEMBER))
      if (url.includes('/api/membership-application')) return Promise.resolve(jsonResponse({ data: APPLICATION }))
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/profile')

    expect(wrapper.text()).toContain('Grace Hopper.')
    expect(wrapper.text()).toContain('Enterprise software')
    expect(wrapper.text()).toContain('AI systems')
    expect(wrapper.text()).toContain('Two hours per month')
  })
})
