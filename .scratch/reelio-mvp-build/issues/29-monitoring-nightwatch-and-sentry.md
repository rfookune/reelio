# 29: Monitoring: Nightwatch and Sentry

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Backend and frontend errors reach the owner without collecting personal data, and search text never reaches logs or monitoring. See the spec's "Analytics and monitoring" and "Operations" (logs) sections.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md), [03: Security headers and CSP](./03-security-headers-and-csp.md)

**Status:** ready-for-agent

- [ ] `laravel/nightwatch` (approved) is installed and configured: requests sampled at 10%, errors, jobs and outbound requests at 100%, `q` redacted from request URLs, exception context redacted
- [ ] `@sentry/vue` (approved) reports browser errors only: no replay or tracing, IP collection off, PII scrubbing on, `userInfo` off, EU ingest (matching the CSP's `connect-src`), `q` stripped from URLs and breadcrumbs
- [ ] Application logs at `info` record path (with `q` redacted), status, `user_id` and exception class, and never bodies, passwords, tokens, emails, search text or IPs
- [ ] Authentication failures are a counted log line with no email or IP
- [ ] Earlier fail-soft paths (event recording, import failures, overdue alerts, TMDB 401) report through Nightwatch
- [ ] Tests cover the redaction of `q` and the log line shape
