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

const ADMIN_STARTUP = {
  ...STARTUP,
  owner: {
    id: 44,
    name: 'Grace Hopper',
    email: 'grace@example.com',
  },
  website_url: 'https://autoflow.example',
  linkedin_url: 'https://www.linkedin.com/company/autoflow',
}

const HOMEPAGE_CARD = {
  id: 51,
  section: 'what_we_do',
  eyebrow: 'Studio',
  title: 'Build with Wharton AI operators',
  body: 'Peer-led build sessions and founder support.',
  link_label: 'Join',
  link_url: '/membership',
  content_status: 'draft',
  visibility: 'public',
  sort_order: 1,
}

const PARTNER = {
  id: 61,
  name: 'Wharton AI Lab',
  partner_type: 'Academic partner',
  summary: 'Research collaboration.',
  description: 'Supports WAAIS programming and research exchange.',
  website_url: 'https://example.com',
  logo_url: null,
  content_status: 'draft',
  visibility: 'mixed',
  sort_order: 2,
}

const ANNOUNCEMENT = {
  id: 71,
  title: 'Forum categories are live',
  summary: 'New member discussion spaces are available.',
  body: 'We opened new member spaces for founders, operators, research, jobs, and member introductions.',
  content_status: 'draft',
  visibility: 'members_only',
  audience: 'all_members',
  channel: 'dashboard',
  action_label: 'Open forum',
  action_url: 'https://forum.whartonai.studio',
}

const MEMBER_ANNOUNCEMENT = {
  ...ANNOUNCEMENT,
  content_status: 'published',
  published_at: '2026-05-02T18:00:00.000000Z',
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
      if (url.includes('/api/announcements')) {
        return Promise.resolve(jsonResponse({
          data: [MEMBER_ANNOUNCEMENT],
          current_page: 1,
          last_page: 1,
          per_page: 3,
          total: 1,
        }))
      }
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
    expect(wrapper.text()).toContain('Forum categories are live')
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
    expect(wrapper.text()).toContain('No applications in this status.')
    expect(wrapper.text()).toContain('Select an application.')
  })

  it('renders the admin startup review queue from the admin API', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/startup-listings/7')) return Promise.resolve(jsonResponse({ data: ADMIN_STARTUP }))
      if (url.includes('/api/admin/startup-listings')) {
        return Promise.resolve(jsonResponse({
          data: [ADMIN_STARTUP],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/startup-review')

    expect(wrapper.text()).toContain('Review submitted startup listings.')
    expect(wrapper.text()).toContain('AutoFlow AI')
    expect(wrapper.text()).toContain('Workflow automation for B2B teams.')
    expect(wrapper.text()).toContain('Grace Hopper')
    expect(wrapper.text()).toContain('Approve')

    const listRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/startup-listings?'))
    expect(listRequest[1].credentials).toBe('include')
  })

  it('posts an approval transition from the selected admin startup listing', async () => {
    const approved = { ...ADMIN_STARTUP, approval_status: 'approved', content_status: 'published' }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/startup-listings/7/approve')) return Promise.resolve(jsonResponse({ data: approved }))
      if (url.includes('/api/admin/startup-listings/7')) return Promise.resolve(jsonResponse({ data: ADMIN_STARTUP }))
      if (url.includes('/api/admin/startup-listings')) {
        return Promise.resolve(jsonResponse({
          data: [ADMIN_STARTUP],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/startup-review')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const approveRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/startup-listings/7/approve'))
    expect(approveRequest[1].method).toBe('POST')
    expect(approveRequest[1].credentials).toBe('include')
    expect(JSON.parse(approveRequest[1].body)).toEqual({ review_notes: null })
    expect(wrapper.text()).toContain('No startup listings in this status.')
    expect(wrapper.text()).toContain('Select a startup listing.')
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

  it('renders the admin event management queue from the admin API', async () => {
    const ADMIN_EVENT = {
      id: 33,
      title: 'AI Founder Salon',
      summary: 'Focused salon.',
      description: 'Private dinner.',
      starts_at: '2026-06-10T18:00:00.000000Z',
      ends_at: '2026-06-10T21:00:00.000000Z',
      location: 'New York',
      content_status: 'draft',
      visibility: 'members_only',
      cancelled_at: null,
      capacity_limit: 50,
      reminder_days_before: 2,
      waitlist_open: false,
    }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.match(/\/api\/admin\/events\/33$/)) return Promise.resolve(jsonResponse({ data: ADMIN_EVENT }))
      if (url.includes('/api/admin/events')) {
        return Promise.resolve(jsonResponse({
          data: [ADMIN_EVENT],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/events-admin')

    expect(wrapper.text()).toContain('Create, edit, publish, hide, archive, and cancel events.')
    expect(wrapper.text()).toContain('AI Founder Salon')

    const listRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/events?'))
    expect(listRequest[0]).toContain('content_status=draft')
    expect(listRequest[1].credentials).toBe('include')

    await wrapper.find('.table-button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Publish')
  })

  it('renders the admin user directory from the admin API', async () => {
    const TARGET = {
      id: 22,
      name: 'Grace Hopper',
      email: 'grace@example.com',
      approval_status: 'approved',
      affiliation_type: 'alumni',
      permission_role: 'member',
      created_at: '2026-01-15T10:00:00.000000Z',
    }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/users')) {
        return Promise.resolve(jsonResponse({
          data: [TARGET],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/users')

    expect(wrapper.text()).toContain('Search the members and adjust roles.')
    expect(wrapper.text()).toContain('Grace Hopper')
    expect(wrapper.text()).toContain('grace@example.com')

    await wrapper.find('.table-button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Super admin access is required to change roles.')
  })

  it('renders the admin public content homepage-card editor from the admin API', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.match(/\/api\/admin\/homepage-cards\/51$/)) return Promise.resolve(jsonResponse({ data: HOMEPAGE_CARD }))
      if (url.includes('/api/admin/homepage-cards')) {
        return Promise.resolve(jsonResponse({
          data: [HOMEPAGE_CARD],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/content-admin')

    expect(wrapper.text()).toContain('Edit homepage cards and partners without touching code.')
    expect(wrapper.text()).toContain('Build with Wharton AI operators')

    const listRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/homepage-cards?'))
    expect(listRequest[0]).toContain('content_status=draft')
    expect(listRequest[1].credentials).toBe('include')

    await wrapper.find('.table-button').trigger('click')
    await flushPromises()
    expect(wrapper.find('input[required]').element.value).toBe('what_we_do')
  })

  it('switches the admin public content editor to partners', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/partners')) {
        return Promise.resolve(jsonResponse({
          data: [PARTNER],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      if (url.includes('/api/admin/homepage-cards')) {
        return Promise.resolve(jsonResponse({
          data: [HOMEPAGE_CARD],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/content-admin')
    const partnersButton = wrapper.findAll('button').find((node) => node.text() === 'Partners')
    expect(partnersButton).toBeTruthy()

    await partnersButton.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Wharton AI Lab')
    const partnerRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/partners?'))
    expect(partnerRequest[0]).toContain('content_status=draft')
  })

  it('publishes selected public content and removes it from the draft queue', async () => {
    const published = { ...HOMEPAGE_CARD, content_status: 'published' }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/homepage-cards/51/publish')) return Promise.resolve(jsonResponse({ data: published }))
      if (url.match(/\/api\/admin\/homepage-cards\/51$/)) return Promise.resolve(jsonResponse({ data: HOMEPAGE_CARD }))
      if (url.includes('/api/admin/homepage-cards')) {
        return Promise.resolve(jsonResponse({
          data: [HOMEPAGE_CARD],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/content-admin')
    await wrapper.find('.table-button').trigger('click')
    await flushPromises()

    const publishButton = wrapper.findAll('button').find((node) => node.text() === 'Publish')
    expect(publishButton).toBeTruthy()
    await publishButton.trigger('click')
    await flushPromises()

    const publishRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/homepage-cards/51/publish'))
    expect(publishRequest[1].method).toBe('POST')
    expect(publishRequest[1].credentials).toBe('include')
    expect(wrapper.text()).toContain('No public content in this status.')
  })

  it('renders the admin announcements manager from the admin API', async () => {
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.match(/\/api\/admin\/announcements\/71$/)) return Promise.resolve(jsonResponse({ data: ANNOUNCEMENT }))
      if (url.includes('/api/admin/announcements')) {
        return Promise.resolve(jsonResponse({
          data: [ANNOUNCEMENT],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/announcements')

    expect(wrapper.text()).toContain('Create, edit, publish, hide, and archive member announcements.')
    expect(wrapper.text()).toContain('Forum categories are live')

    const listRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/announcements?'))
    expect(listRequest[0]).toContain('content_status=draft')
    expect(listRequest[1].credentials).toBe('include')

    await wrapper.find('.table-button').trigger('click')
    await flushPromises()
    expect(wrapper.find('input[required]').element.value).toBe('Forum categories are live')
  })

  it('publishes the selected announcement and removes it from the draft queue', async () => {
    const published = { ...ANNOUNCEMENT, content_status: 'published' }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/announcements/71/publish')) return Promise.resolve(jsonResponse({ data: published }))
      if (url.match(/\/api\/admin\/announcements\/71$/)) return Promise.resolve(jsonResponse({ data: ANNOUNCEMENT }))
      if (url.includes('/api/admin/announcements')) {
        return Promise.resolve(jsonResponse({
          data: [ANNOUNCEMENT],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/announcements')
    await wrapper.find('.table-button').trigger('click')
    await flushPromises()

    const publishButton = wrapper.findAll('button').find((node) => node.text() === 'Publish')
    expect(publishButton).toBeTruthy()
    await publishButton.trigger('click')
    await flushPromises()

    const publishRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/announcements/71/publish'))
    expect(publishRequest[1].method).toBe('POST')
    expect(publishRequest[1].credentials).toBe('include')
    expect(wrapper.text()).toContain('No announcements in this status.')
  })

  it('lets a super admin promote a member to admin from the user directory', async () => {
    const SUPER = { ...ADMIN, permission_role: 'super_admin', can_manage_admin_privileges: true }
    const TARGET = {
      id: 22,
      name: 'Grace Hopper',
      email: 'grace@example.com',
      approval_status: 'approved',
      affiliation_type: 'alumni',
      permission_role: 'member',
    }
    const promoted = { ...TARGET, permission_role: 'admin' }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(SUPER))
      if (url.includes('/api/admin/users/22/promote-admin')) return Promise.resolve(jsonResponse({ data: promoted }))
      if (url.match(/\/api\/admin\/users\/22$/)) return Promise.resolve(jsonResponse({ data: TARGET }))
      if (url.includes('/api/admin/users')) {
        return Promise.resolve(jsonResponse({
          data: [TARGET],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)
    vi.spyOn(window, 'confirm').mockReturnValue(true)

    const wrapper = await mountAt('/app/users')
    await wrapper.find('.table-button').trigger('click')
    await flushPromises()

    const promoteButton = wrapper.findAll('button').find((node) => node.text() === 'Promote to admin')
    expect(promoteButton).toBeTruthy()
    await promoteButton.trigger('click')
    await flushPromises()

    const promoteRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/users/22/promote-admin'))
    expect(promoteRequest[1].method).toBe('POST')
    expect(promoteRequest[1].credentials).toBe('include')
    expect(wrapper.text()).toContain('Demote admin')
  })

  it('publishes the selected event and removes it from the draft queue', async () => {
    const ADMIN_EVENT = {
      id: 41,
      title: 'Demo Night',
      summary: 'Showcase.',
      description: 'Live demos.',
      starts_at: '2026-06-20T18:00:00.000000Z',
      content_status: 'draft',
      visibility: 'public',
      cancelled_at: null,
      reminder_days_before: 2,
      waitlist_open: false,
    }
    const published = { ...ADMIN_EVENT, content_status: 'published' }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/user')) return Promise.resolve(jsonResponse(ADMIN))
      if (url.includes('/api/admin/events/41/publish')) return Promise.resolve(jsonResponse({ data: published }))
      if (url.match(/\/api\/admin\/events\/41$/)) return Promise.resolve(jsonResponse({ data: ADMIN_EVENT }))
      if (url.includes('/api/admin/events')) {
        return Promise.resolve(jsonResponse({
          data: [ADMIN_EVENT],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = await mountAt('/app/events-admin')
    await wrapper.find('.table-button').trigger('click')
    await flushPromises()

    const publishButton = wrapper.findAll('button').find((node) => node.text() === 'Publish')
    expect(publishButton).toBeTruthy()
    await publishButton.trigger('click')
    await flushPromises()

    const publishRequest = fetchMock.mock.calls.find(([url]) => url.includes('/api/admin/events/41/publish'))
    expect(publishRequest[1].method).toBe('POST')
    expect(publishRequest[1].credentials).toBe('include')
    expect(wrapper.text()).toContain('No events in this status.')
  })
})
