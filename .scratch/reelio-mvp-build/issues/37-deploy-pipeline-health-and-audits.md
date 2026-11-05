# 37: Deploy pipeline, health and audits

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Production deploys happen only after every required check passes, the platform can tell when the app is ready, and vulnerable dependencies fail CI. See the spec's "Operations" and "Security" (audits) sections.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md), [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md)

**Status:** ready-for-agent

- [ ] `/health` runs one cheap database query and one cache read and returns 200 or 503 with no detail; TMDB is not checked; `/up` stays liveness-only
- [ ] A required CI job runs `composer audit` and `npm audit --audit-level=high`; Dependabot security alerts and updates are enabled
- [ ] `aws/aws-sdk-php` (approved) is installed for Cloud managed queues; jobs use the `default` and `imports` queues as the spec assigns
- [ ] A GitHub Actions job runs after the required checks on `main` and POSTs Cloud's deploy hook with `?commit_hash=${{ github.sha }}`; push-to-deploy stays off
- [ ] The deploy command is `php artisan migrate --force` and the build command `composer install --no-dev && npm ci && npm run build:ssr` (documented for the Cloud setup in Launch readiness)
