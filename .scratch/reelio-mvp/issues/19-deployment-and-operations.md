# Deployment and operations

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

Given the [infrastructure baseline](./05-infrastructure-baseline.md) (Laravel Cloud, Postgres, Redis cache, database queue and sessions, local plus production with preview environments), how is Reelio deployed and kept running through the beta?

- Deployment pipeline: what runs before a deploy (tests, Pint, static analysis, frontend build), migrations on deploy, and rollback.
- Database backups: frequency, retention, and a restore drill, consistent with the published retention policy and account deletion.
- Health-check endpoint (PRD §11.3) and what it checks.
- Log hygiene: what is logged, what never is (no unnecessary personal data), and retention.
- How the 99.5% monthly availability target (PRD §2.3) is observed, and what happens when TMDB is down.
- Secrets handling for the TMDB key, mail provider and monitoring.

Error monitoring tooling is decided in [Analytics and error monitoring](./08-analytics-and-monitoring.md); this ticket covers the rest of operations.

## Comments

- From [Test strategy and tooling](./07-test-strategy.md) (resolved): the merge gate is settled: `composer ci:check` (now with `vp test`, Postgres and Redis) plus a browser-test job, both required on `main`. This ticket decides how that gate relates to deploys, and whether the manual `live` test group ever runs on a schedule.

- From [Country and Availability presentation](./11-country-and-availability.md) (resolved): verify on a deployed environment that Laravel Cloud forwards the edge country header (`CF-IPCountry` or equivalent); if not, country detection falls back to US until a source is chosen.
- From [Public pages: SSR, SEO metadata and sitemap scope](./14-public-pages-seo.md) (resolved): Inertia SSR is on for public pages, so production needs the SSR process running on Laravel Cloud (verify support, and how a crash falls back to client rendering), plus the daily sitemap job.

## Answer

Facts below come from Laravel Cloud's docs as of 2026-11-04; the Cloud-behaviour assumptions are listed under "Verify on first deploy".

**Plan and compute**

- **Growth plan** ($20/mo plus usage): 7-day log retention, unlimited preview environments, Postgres up to 4 CU. Starter's 1-day logs and single preview environment don't fit the operations below.
- **Scale-to-zero in production** for the App cluster and Postgres. This is a deliberate economy choice for the prototype phase, and moving to an always-on replica later is one toggle with no code change (decided by the owner, not by a metric). Preview environments also scale to zero.
- The App cluster must use a **new Flex size** (sub-500 ms wake), not a legacy Flex size (5 to 20 s). Inertia SSR is the App cluster's native "Use Inertia SSR" toggle, with the build command `npm run build:ssr`.
- The LCP target applies to warm requests. A first visit after idle accepts about 500 ms for the compute wake plus a few hundred ms for the Postgres wake.

**Cache and queues** (correcting [Infrastructure baseline](./05-infrastructure-baseline.md))

- **Cache is Laravel Valkey** (Redis-compatible, driver unchanged). Redis by Upstash is deprecated and sunset on 2027-08-31.
- **Queues are Cloud managed queues**, replacing the baseline's database queue in production (`QUEUE_CONNECTION=cloud`, set by Cloud). Queued jobs don't wake a sleeping environment, but managed queues run on their own compute and wake in under a second. Local development uses the `database` driver, and tests fake the queue. Sessions stay in the database.
- Two Flex queues: `default` (256 MiB, scale to zero, max 3 workers) for analytics events, mail, metadata and Availability refresh, purge and event roll-up; `imports` (512 MiB, scale to zero, max 1 worker) for the daily IMDb import and the sitemap build.
- Flex jobs are killed at 90 seconds. The IMDb import validates into a staging table and swaps, so a killed job leaves live data untouched and shows as a failed job. A build-time spike measures the real duration; if it doesn't fit, `imports` moves to Pro (about $5/month, always on) before launch.
- **Job rules:** idempotent (delivery is at-least-once); analytics events carry a client-generated UUID with a unique index; delays at most 15 minutes; payloads carry ids, not models; at most 90 seconds of work per job, with batch work split into chunk jobs; local and test queues behave the same way.
- Scheduled tasks are daily or hourly only, never every minute, so Cloud's scheduler doesn't hold the app awake. Long ones only **dispatch** a job.
- Failed jobs are managed in Cloud's Queues dashboard. `queue:failed`, `queue:retry` and Horizon aren't available. Dependency change for the build (needs approval): `aws/aws-sdk-php`.

**Deploy pipeline**

- Push-to-deploy is **off**. A GitHub Actions job runs after the required checks on `main` (`ci:check`, `vp test`, the browser job) and POSTs the deploy hook with `?commit_hash=${{ github.sha }}`.
- Cloud's build command: `composer install --no-dev && npm ci && npm run build:ssr`. Deploy command: `php artisan migrate --force`. No `queue:restart`, `optimize:clear` or `storage:link` (Cloud handles them).
- A failed health check leaves the previous release serving traffic.
- **Migrations are backward compatible** with the previous release (add before use, remove in a later deploy). `migrate:rollback` is never run in production. A destructive migration step needs explicit owner approval.
- **Rollback** is a redeploy of the previous commit through the deploy hook.
- A weekly scheduled GitHub Action runs the manual `live` test group against TMDB with a read-only token stored as a repo secret, and emails the owner on failure.

**Health**

- `/up` (already routed) for startup and liveness: it checks the app only, never the database.
- A new `/health` for readiness: one cheap database query and one cache read, returning 200 or 503 with no detail. TMDB is deliberately not checked, because a TMDB outage must not take the site out of rotation.

**Availability target**

- The PRD's 99.5% target (§2.3) is **dropped for the prototype phase**: scale-to-zero and no uptime monitor mean it can't be measured or promised. Reliability is watched through Nightwatch and Cloud's metrics, with the email alerts from [Analytics and error monitoring](./08-analytics-and-monitoring.md). A full outage with no traffic raises no alert, which is accepted for now. The target returns when the owner moves to an always-on replica.
- No external uptime monitor in the beta.

**Backups**

- Serverless Postgres **point-in-time recovery with a 7-day window**, set when the cluster is created.
- **One restore drill before launch** (restore to a new cluster, check the data, delete it), repeated after any large schema change, written up as a runbook.
- A deleted account can survive in backups for up to 7 days; the privacy page says so.
- **Restores must not resurrect deleted accounts:** account deletion writes an `account_deleted` log line carrying only the `user_id`. The runbook's final step searches the logs and re-deletes every user deleted since the restore point. Growth keeps logs for 7 days, the same as the backup window, and the two must stay equal if either changes.

**Logs**

- Production logs at `info`. Application logs record request path, status, `user_id` and exception class, and never request bodies, passwords, tokens, email addresses, search text or IP addresses. Exception context is redacted.
- No log drain in the beta. Cloud's own access logs are platform-generated, may contain IPs, and are kept for the plan's retention (7 days on Growth); the privacy page says so.

**Secrets and mail**

- The TMDB token, mail key and Nightwatch/Sentry credentials live in Cloud's Secrets Manager, attached per environment, never in the repo. Rotation means updating the secret and redeploying.
- Preview environments get the TMDB token (read-only), the log mail driver and their own database, and no other production credentials.
- Mail goes through Cloud's **Resend** integration (the provider choice doesn't depend on the final domain; only the verified sender domain does, see [Product name](./20-product-name.md)).

**Verify on first deploy** (a one-time smoke checklist)

- The edge country header reaches the app (`CF-IPCountry` or equivalent), per [Country and Availability presentation](./11-country-and-availability.md); if not, detection falls back to US.
- The SSR toggle serves server-rendered public pages, and a crash falls back to client rendering.
- The scheduler wakes the app for its daily jobs without holding it awake.
- The App cluster is a new Flex size, with a sleep timeout set.
- Nightwatch sees jobs on the `cloud` queue driver; if not, Cloud's Queues dashboard is the failed-job alert source.

- From [Search experience](./23-search-experience.md) (resolved): the search query lives in `?q=`, so request-path logging must redact `q`. Check at build whether Laravel Cloud's edge access logs hold full query strings.

- From [Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md) (resolved): every scheduled task dispatches from one daily slot at 05:00 UTC, with weekly tasks on Mondays and no hourly tasks.
    - Movie refreshes are one unique job per Movie on `default`, dispatched by a self-continuing planner.
    - Background TMDB calls share a 20 requests/s throttle in Valkey.
    - New owner alerts: a Movie with user facts more than 14 days overdue for refresh; the IMDb table more than 3 days old.
