# 39: Launch readiness

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The owner sets up production and verifies everything that can't be tested in CI, then records the results. HITL: done by the owner with the agent's help. See the spec's "Further Notes" checklists.

**Blocked by:** [11: IMDb ratings import and display](./11-imdb-ratings-import-and-display.md), [29: Monitoring: Nightwatch and Sentry](./29-monitoring-nightwatch-and-sentry.md), [35: Performance budget checks](./35-performance-budget-checks.md), [36: Accessibility sweep](./36-accessibility-sweep.md), [37: Deploy pipeline, health and audits](./37-deploy-pipeline-health-and-audits.md)

**Status:** ready-for-human

- [ ] Cloud environment created: Growth plan, US region, new Flex App cluster with scale-to-zero and SSR toggle, serverless Postgres with 7-day PITR, Valkey, `default` and `imports` managed queues (Pro for `imports` if the import measurement requires), Resend, Nightwatch (US, spend cap), secrets in Secrets Manager, preview environments configured as the spec says
- [ ] Every first-deploy checklist item verified and noted (edge country header, real unspoofable client IP, SSR and fallback, scheduler wake, Flex size, Nightwatch sees `cloud` jobs, edge asset caching vs the limit, HSTS, edge rate limit with Throttle, frame and nosniff headers, Sentry IP setting)
- [ ] Every build check resolved and noted (Nightwatch backend-only, Sentry cookies, Sentry and Resend retention, Cloud access logs and `?q=`, Cloud region code, Cloud failed-job retention), with the Privacy page updated to match
- [ ] The restore drill run and written up as a runbook, including re-deleting accounts found in `account_deleted` lines
- [ ] The breach runbook written
- [ ] The manual accessibility checklist run with a dated entry; PageSpeed Insights (mobile) results recorded for Discover, a Movie page and Search
- [ ] Launch still waits on the map's TMDB classification, IMDb permission and register clearance tickets for the features they gate

## Note from ticket 13 (2026-11-06)

The edge country header's name is `config('catalog.country_header')`, set by `CATALOG_COUNTRY_HEADER` (default `CF-IPCountry`). If Cloud sends a different header, set the variable; no code change is needed. Without the header every visitor without a choice sees US, with no "not supported" notice.

## Cloud deploy setup from ticket 37

- [ ] Disable push-to-deploy on the production environment, which tracks `main`; enable its deploy hook and save the URL as the GitHub Actions repository secret `LARAVEL_CLOUD_DEPLOY_HOOK`. The `tests` workflow deploys the checked commit only after `ci`, `browser`, and `audit` succeed.
- [ ] Set the Cloud build command to `composer install --no-dev && npm ci && npm run build:ssr` and deploy command to `php artisan migrate --force`.
- [ ] Set the Cloud readiness path to `/health` (empty 200 on a working database/cache, empty 503 otherwise); leave `/up` as liveness. Confirm a failed readiness check keeps the previous release serving.
- [ ] Set `QUEUE_CONNECTION=cloud` and provision the `default` and `imports` queues. Jobs already target these names; the AWS SDK is installed for the managed driver. Local development retains `database`.
- [ ] Require the `ci`, `browser`, and `audit` checks in the GitHub rule for `main`. Enable Dependabot alerts and Dependabot security updates in repository Settings > Advanced Security. Composer, npm and Actions update schedules are configured in `.github/dependabot.yml`; that file alone does not enable security alerts or security updates.

These account settings and the deploy hook secret must be verified by the owner; ticket 37's local implementation does not verify or change them.
