# 05: Analytics event recording

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Reelio records first-party product events in its own database, privately and without slowing or breaking requests. `account_registered` is the first event; each feature ticket wires in its own. See the spec's "Analytics and monitoring" section.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md)

**Status:** ready-for-agent

- [ ] An `events` table with a unique UUID per event, a cascading `user_id` foreign key, the event name, JSON properties and a timestamp
- [ ] Events are recorded on the queue after the response, only for signed-in users, with no IP, user agent or anonymous id; a redelivered event is deduplicated by its UUID
- [ ] A recording failure never reaches the user and is reported (to the log now; Nightwatch once monitoring lands)
- [ ] `account_registered` (no properties) is recorded on registration
- [ ] Deleting an account deletes its events
- [ ] Feature tests cover recording, dedupe, fail-soft behaviour and the cascade
