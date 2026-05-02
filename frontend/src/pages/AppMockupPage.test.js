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

const PENDING_USER = {
  ...MEMBER,
  approval_status: 'submitted',
  permission_role: 'pending_user',
  can_access_member_areas: false,
}

const ADMIN = {
  ...MEMBER,
  permission_role: 'admin',
  can_publish_public_content: true,
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

const ADMIN_APPLICATION = {
  ...APPLICATION,
  id: 12,
  email: 'ada@example.com',
  first_name: 'Ada',
  last_name: 'Lovelace',
  applicant: {
    id: 99,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
  },
  linkedin_url: 'https://www.linkedin.com/in/ada',
}

const STARTUP = {
  id: 7,
  name: 'AutoFlow AI',
  tagline: 'Workflow automation for B2B teams.',
  description: 'AutoFlow AI helps operations teams orchestrate workflows.',
  industry: 'AI Engineering',
  stage: 'Seed',
  location: 'New York',
  founders: ['Daniel Reed', 'Priya Patel'],
  submitter_role: 'Cofounder',
  approval_status: 'submitted',
  content_status: 'pending_review',
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
      if (url.includes('/api/startup-listings')) return Promise.resolve(jsonResponse({ data: [] }))
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
      if (url.includes('/api/startup-listings')) return Promise.resolve(jsonResponse({ data: [] }))
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/profile')

    expect(wrapper.text()).toContain('Grace Hopper.')
    expect(wrapper.text()).toContain('Enterprise software')
    expect(wrapper.text()).toContain('AI systems')
    expect(wrapper.text()).toContain('Two hours per month')
  })

  it('renders member-owned startup listings and selects one into the form', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(MEMBER))
      if (url.includes('/api/membership-application')) return Promise.resolve(jsonResponse({ data: APPLICATION }))
      if (url.includes('/api/startup-listings')) return Promise.resolve(jsonResponse({ data: [STARTUP] }))
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/my-startups')

    expect(wrapper.text()).toContain('Submit and track your startup listings.')
    expect(wrapper.text()).toContain('AutoFlow AI')
    expect(wrapper.text()).toContain('Submitted')

    await wrapper.find('.table-button').trigger('click')

    expect(wrapper.find('input[required]').element.value).toBe('AutoFlow AI')
    expect(wrapper.text()).toContain('Edit listing')
  })

  it('shows an approval-required state instead of a disabled startup form for pending users', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(PENDING_USER))
      if (url.includes('/api/membership-application')) return Promise.resolve(jsonResponse({ data: APPLICATION }))
      return Promise.resolve(jsonResponse({ message: 'Forbidden.' }, { status: 403 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/my-startups')

    expect(wrapper.text()).toContain('Startup submissions open after member approval.')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('renders the admin membership approvals queue from the admin API', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/applications/12')) return Promise.resolve(jsonResponse({ data: ADMIN_APPLICATION }))
      if (url.includes('/api/admin/applications')) {
        return Promise.resolve(jsonResponse({
          data: [ADMIN_APPLICATION],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/approvals')

    expect(wrapper.text()).toContain('Review new member applications.')
    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('ada@example.com')
    expect(wrapper.text()).toContain('Wharton MBA')
    expect(wrapper.text()).toContain('Approve')

    const listRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/applications?'))
    expect(listRequest[1].credentials).toBe('include')
  })

  it('posts an approval transition from the selected admin application', async () => {
    const approved = { ...ADMIN_APPLICATION, approval_status: 'approved' }
    const fetchMock = vi.fn((url, init = {}) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/applications/12/approve')) return Promise.resolve(jsonResponse({ data: approved }))
      if (url.includes('/api/admin/applications/12')) return Promise.resolve(jsonResponse({ data: ADMIN_APPLICATION }))
      if (url.includes('/api/admin/applications')) {
        return Promise.resolve(jsonResponse({
          data: [ADMIN_APPLICATION],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/approvals')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const approveRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/applications/12/approve'))
    expect(approveRequest[1].method).toBe('POST')
    expect(approveRequest[1].credentials).toBe('include')
    expect(JSON.parse(approveRequest[1].body)).toEqual({ review_notes: null })
    expect(wrapper.text()).toContain('Approved')
  })

  it('shows a sign-out action for authenticated users and clears app state after logout', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(MEMBER))
      if (url.includes('/api/logout')) return Promise.resolve(jsonResponse({ ok: true }))
      if (url.includes('/api/membership-application')) return Promise.resolve(jsonResponse({ data: APPLICATION }))
      if (url.includes('/api/startup-listings')) return Promise.resolve(jsonResponse({ data: [STARTUP] }))
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/dashboard')

    await wrapper.find('button.button.secondary').trigger('click')
    await flushPromises()

    const logoutRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/logout'))
    expect(logoutRequest[1].method).toBe('POST')
    expect(logoutRequest[1].credentials).toBe('include')
    expect(wrapper.text()).not.toContain('Sign out')
  })

  it('shows only sign-in choices before authentication', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse({ message: 'Unauthenticated.' }, { status: 401 }))
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/sign-in')

    expect(wrapper.text()).toContain('Sign in with Google')
    expect(wrapper.text()).toContain('Sign in with email')
    expect(wrapper.text()).not.toContain('Sign out')
  })

  it('shows only sign-out controls after authentication on the sign-in view', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(MEMBER))
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/sign-in')

    expect(wrapper.text()).toContain('Sign out')
    expect(wrapper.text()).not.toContain('Sign in with Google')
    expect(wrapper.text()).not.toContain('Sign in with email')
    expect(wrapper.findAll('.app-nav-group').some((group) => group.text().includes('Auth'))).toBe(false)
  })
})
