# Deployment and operations

Type: grilling
Status: open
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
