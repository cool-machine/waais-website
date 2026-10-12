# Merge and Production Deployment Requirements

Pull requests targeting `main` must pass the GitHub Actions checks
`frontend-tests` and `backend-tests` against an up-to-date branch and receive
one approving review. These branch protections also apply to administrators.

Both Azure deployment workflows use the `production` GitHub environment.
Only `main` may deploy, and `cool-machine` must approve each deployment before
the deployment job starts. Administrator bypass is disabled. The owner may
approve deployments they initiate themselves.

The frontend's `AZURE_STATIC_WEB_APPS_API_TOKEN` belongs to the `production`
environment, rather than repository secrets. The backend Azure federated
credential trusts `repo:cool-machine/waais-website:environment:production`.
Keep these credentials behind the environment approval gate.

Branch and environment protections are GitHub settings configured separately
from the workflow files. Preserve them when changing deployment workflows.
