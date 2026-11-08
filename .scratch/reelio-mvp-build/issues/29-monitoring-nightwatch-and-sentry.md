# 29: Monitoring: Nightwatch and Sentry

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Backend and frontend monitoring use Laravel Nightwatch and Sentry's provided defaults, with minimal application integration and environment configuration. The accepted deviations below supersede the original spec's custom monitoring, logging and test requirements.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md), [03: Security headers and CSP](./03-security-headers-and-csp.md)

**Status:** resolved

- [x] `laravel/nightwatch` is installed and uses its package-provided configuration; no application `config/nightwatch.php` override or custom monitoring provider is retained. `.env.example` exposes the token and request/exception/command sample rates, and includes `nightwatch` in `LOG_STACK`.
- [x] `@sentry/vue` is installed and initialized in the browser with the configured DSN, environment and release. Sentry's default integrations, captured data and client reports are retained; no custom filtering, redaction or session-integration exclusions are applied.
- [x] The Sentry CSP ingest origin is configured explicitly through `SENTRY_INGEST_HOST`, rather than derived from the DSN.
- [x] Laravel's default logging and exception reporting are retained. Custom request-log and authentication-failure counter listeners are omitted; the proposed `info`-level/log-shape enforcement and global scrubbing policy are not requirements for this implementation.
- [x] Existing fail-soft paths continue to use their standard `report()` and logging calls, without additional application-specific monitoring hooks.
- [x] Dedicated automated monitoring tests are intentionally omitted at the user's request; the implementation's monitoring test files were removed. No replacement tests are required for resolving this ticket.

## Accepted deviations

- Prefer the vendors' defaults over application-specific monitoring behavior. No custom redaction callbacks, SDK-internal state access, exception-message scrubbing, or forced sampling promotion is used.
- The original requirements to strip `q`, suppress IP/user data, disable `userInfo`, and redact exception context are withdrawn. Monitoring data follows the SDK defaults and may include URLs/query strings, IPs, user details and exception messages.
- Jobs and outbound requests follow Nightwatch's default sampling behavior, including parent-trace sampling, rather than an application-enforced independent 100% rate.
- Sentry's `sendClientReports: false` override and `BrowserSession` exclusion were removed. Default session/release-health and SDK diagnostic behavior is accepted; no additional tracing or replay integration is explicitly installed.
- No custom request/authentication log format or counter is imposed, and no monitoring-specific automated test coverage is required.

## Comments

- Final resolution: the user completed the implementation, removed the dedicated monitoring tests and additional setup decisions, and requested that these deviations be recorded and the ticket marked resolved. The vendor-default implementation is accepted as complete.
- Live delivery, account-region settings, credentials and agent provisioning were not reverified as part of this ticket update. Earlier automated payload-test results describe the superseded implementation and are not validation claims for the user's final changes.
