# 37: Deploy pipeline, health and audits

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Production deploys happen only after every required check passes, the platform can tell when the app is ready, and vulnerable dependencies fail CI. See the spec's "Operations" and "Security" (audits) sections.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md), [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md)

**Status:** ready-for-human

- [x] `/health` runs one cheap database query and one cache read and returns 200 or 503 with no detail; TMDB is not checked; `/up` stays liveness-only
- [ ] A required CI job runs `composer audit` and `npm audit --audit-level=high`; Dependabot security alerts and updates are enabled
- [x] `aws/aws-sdk-php` (approved) is installed for Cloud managed queues; jobs use the `default` and `imports` queues as the spec assigns
- [ ] A GitHub Actions job runs after the required checks on `main` and POSTs Cloud's deploy hook with `?commit_hash=${{ github.sha }}`; push-to-deploy stays off
- [x] The deploy command is `php artisan migrate --force` and the build command `composer install --no-dev && npm ci && npm run build:ssr` (documented for the Cloud setup in Launch readiness)

## Implementation

Repository implementation is complete. The `tests` workflow includes dependency audits and deploys the checked commit on `main` only after `ci`, `browser`, and `audit` pass. Dependabot schedules cover Composer, npm and Actions. The owner confirmed Cloud managed queues for production; local development keeps the database driver.

The remaining unchecked items require GitHub and Cloud account settings: required checks, Dependabot alerts/security updates, the deploy hook secret, and disabling push-to-deploy. See [Launch readiness: Cloud deploy setup](./39-launch-readiness.md#cloud-deploy-setup-from-ticket-37) for the full setup and production readiness verification. These settings have not been changed or verified locally.

Validation: 709 Unit/Feature tests and 161 browser tests passed; frontend checks, TypeScript, Pint, PHPStan, Composer validation and dependency audits passed. Separate standards and spec reviews found no code issues; external settings remain an explicit handoff.
