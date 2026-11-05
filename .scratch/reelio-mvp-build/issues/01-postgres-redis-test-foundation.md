# 01: Postgres and Redis test foundation

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The test suite and local development run on the same stores as production, so every later ticket is tested against Postgres and Redis. Frontend logic tests have a home, and no test can reach the network by accident.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] The Pest suite runs on Postgres with the Redis cache (no in-memory SQLite or array cache), and the existing starter-kit tests pass on it
- [ ] `.env.example` matches the Herd setup (Postgres, Redis, `database` queue locally, Herd mail or the `log` mailer)
- [ ] Stray HTTP requests are blocked for the whole suite
- [ ] `vp test` runs Vitest with `@testing-library/vue` (approved dev dependency) and is part of `composer ci:check`, with one example test proving the wiring
- [ ] CI runs `ci:check` with Postgres and Redis service containers and is green
