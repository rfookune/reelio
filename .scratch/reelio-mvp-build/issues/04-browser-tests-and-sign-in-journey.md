# 04: Browser test harness and the sign-in journey

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Browser tests run locally and in CI under the real CSP, and the first core journey (register, then log in) is covered at desktop and mobile sizes. Later tickets add their own journeys on this harness.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md), [02: App shell and visual language](./02-app-shell-and-visual-language.md), [03: Security headers and CSP](./03-security-headers-and-csp.md)

**Status:** ready-for-agent

- [ ] `pestphp/pest-plugin-browser` (approved) with its Playwright browser download works locally and runs as a second, required CI job
- [ ] The register-and-log-in journey runs at a desktop and a mobile viewport with `assertNoJavascriptErrors` (so CSP violations fail) and ends with `assertNoAccessibilityIssues()` at level 1
- [ ] Browser tests bind the in-memory source fakes once they exist; no browser test reaches TMDB or IMDb
- [ ] Auth forms use correct `autocomplete` values and never block paste

## Comments

- From 01: `phpunit.xml` forces Inertia SSR off (`INERTIA_SSR_ENABLED=false`) so feature tests don't depend on a running Vite dev server. Browser tests inherit that, so decide whether the journeys need SSR on (for example, to check CSP nonces in server-rendered HTML) and enable it for them explicitly. Browser test files must also extend `Tests\TestCase` to keep the stray-request guard and the per-test cache flush.
