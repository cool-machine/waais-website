export const approvalStatuses = [
  'none',
  'draft',
  'submitted',
  'needs_more_info',
  'approved',
  'rejected',
  'suspended',
]

export const affiliationTypes = [
  'alumni',
  'student',
  'faculty_staff',
  'partner_guest',
  'other',
]

export const permissionRoles = [
  'public',
  'pending_user',
  'member',
  'admin',
  'super_admin',
]

export const contentStatuses = [
  'draft',
  'pending_review',
  'published',
  'hidden',
  'archived',
]

export const contentVisibilities = [
  'public',
  'members_only',
  'mixed',
]

export const modelNotes = [
  'Approval status, affiliation type, and permission role are separate fields.',
  'Only super admins can promote users to admin or remove admin privileges.',
  'Published public content can be hidden or archived before any hard deletion policy exists.',
]
