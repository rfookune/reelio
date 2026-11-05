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
