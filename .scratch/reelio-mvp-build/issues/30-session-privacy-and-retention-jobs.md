# 30: Session privacy and retention jobs

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Reelio stores no IPs or user agents in sessions, signs a deleted account out everywhere, keeps Back from showing a signed-out user's lists, and enforces its published retention with daily jobs. See the spec's "Privacy, terms and retention" section.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md)

**Status:** ready-for-agent

- [ ] A session handler extending Laravel's database handler stops writing `ip_address` and `user_agent`; a migration drops both columns and adds a cascading foreign key on `sessions.user_id`
- [ ] Account deletion hard-deletes the user and everything with it, and logs an `account_deleted` line carrying only the `user_id`
- [ ] Inertia `clearHistory` on sign-out and `encryptHistory` on private pages
- [ ] Daily jobs in the 05:00 UTC slot: delete unverified accounts 30 days after registration that hold no Watchlist or Watched facts; `auth:clear-resets`; `queue:prune-failed --hours=168`
- [ ] Feature tests cover the session columns, the cascade, the deletion log line and the unverified-account rule
