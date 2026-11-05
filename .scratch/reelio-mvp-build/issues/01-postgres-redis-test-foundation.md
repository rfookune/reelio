# 01: Postgres and Redis test foundation

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The test suite and local development run on the same stores as production, so every later ticket is tested against Postgres and Redis, and no test can reach the network by accident.

**Blocked by:** None (can start immediately)

**Status:** resolved

- [x] The Pest suite runs on Postgres with the Redis cache (no in-memory SQLite or array cache), and the existing starter-kit tests pass on it
- [x] `.env.example` matches the Herd setup (Postgres, Redis, `database` queue locally, Herd mail or the `log` mailer)
- [x] Stray HTTP requests are blocked for the whole suite
- ~~`vp test` runs Vitest with `@testing-library/vue` (approved dev dependency) and is part of `composer ci:check`, with one example test proving the wiring~~ Dropped: frontend logic is covered by Pest browser tests on the [04](./04-browser-tests-and-sign-in-journey.md) harness
- [x] CI runs `ci:check` with Postgres and Redis service containers and is green

## Comments

- **Vitest dropped (decided at build).** Vite+ bundles Vitest but no DOM environment, so `@testing-library/vue` would have needed an unapproved `happy-dom` or `jsdom`. The owner chose to cover client-side logic with Pest browser tests instead: in Vitest those tests would mostly mock Inertia's router and `useHttp`. The spec's Testing Decisions and dependency lists, and tickets 15, 17 and 21, were updated to match; 15 now waits on 04's browser harness.
- **Inertia SSR is off in the test suite** (`INERTIA_SSR_ENABLED=false` in `phpunit.xml`). With the Vite dev server running, feature tests otherwise called the SSR endpoint, which the stray-request guard blocks.
- **Redis isolation** relies on the suite owning Redis index 15, which it flushes before every test. It does not hold for `--parallel` runs.
- **CI verified** green on `8a06375` (run 41972274277), with the Postgres and Redis service containers.
