# 04: Browser test harness and the sign-in journey

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Browser tests run locally and in CI under the real CSP, and the first core journey (register, then log in) is covered at desktop and mobile sizes. Later tickets add their own journeys on this harness.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md), [02: App shell and visual language](./02-app-shell-and-visual-language.md), [03: Security headers and CSP](./03-security-headers-and-csp.md)

**Status:** resolved

- [x] `pestphp/pest-plugin-browser` (approved) with its Playwright browser download works locally and runs as a second, required CI job
- [x] The register-and-log-in journey runs at a desktop and a mobile viewport with `assertNoJavascriptErrors` (so CSP violations fail) and ends with `assertNoAccessibilityIssues()` at level 1
- [x] Browser tests bind the in-memory source fakes once they exist; no browser test reaches TMDB or IMDb
- [x] Auth forms use correct `autocomplete` values and never block paste

## Comments

- From 01: `phpunit.xml` forces Inertia SSR off (`INERTIA_SSR_ENABLED=false`) so feature tests don't depend on a running Vite dev server. Browser tests inherit that, so decide whether the journeys need SSR on (for example, to check CSP nonces in server-rendered HTML) and enable it for them explicitly. Browser test files must also extend `Tests\TestCase` to keep the stray-request guard and the per-test cache flush.
- Implementation already landed in `ed769d7`. Verified on 2026-11-05 from a clean working tree at `95141e1`: `composer ci:check` passed (64 unit/feature tests, 320 assertions, formatting, lint, Vue typechecking and PHPStan); `composer test:browser` passed (4 tests, 75 assertions).
- The browser harness uses built assets, disables Playwright's CSP bypass, and collects `securitypolicyviolation` events as JavaScript errors before the first navigation. SSR stays off for these client-side journeys; nonce/header coverage lives in `tests/Feature/SecurityHeadersTest.php`.
- The journey covers registration, logout and login at 1440×900 and 390×844, with real clipboard paste, autocomplete checks and level 1 accessibility assertions. Password-reset email paste is also covered.
- Catalog and Rating source interfaces/fakes do not exist yet. Browser tests inherit `Tests\TestCase`, including `Http::preventStrayRequests()` and the Redis cache flush; bind the source fakes when those interfaces land in later tickets.
- The separate `browser` CI job installs Chromium with Playwright and runs `composer test:browser` against Postgres and Redis.
- On 2026-11-05, the owner confirmed that the browser CI job passes.
- Resolved at the owner's request with all acceptance criteria checked.
