# Infrastructure baseline

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

Where will Reelio run and on what data infrastructure?

- Hosting: Laravel Forge on AWS (the PRD) vs Laravel Cloud vs another option.
- Database: PostgreSQL (the PRD) vs staying on the starter kit's SQLite.
- Cache and queue: Redis vs the starter kit's database drivers.
- Session store.
- Whether local development matches production (e.g. Postgres locally via Sail or Herd).

Decide with the beta's scale, cost, and the "Laravel way" in mind.

## Answer

| Area                  | Decision                                                                                                                                                                                                                                                                     |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Hosting               | Laravel Cloud (not Forge on AWS). The PRD's Forge/AWS choice served no stated requirement; a solo-run free beta gains nothing from owning servers.                                                                                                                           |
| Database              | PostgreSQL in production and local development; the test suite runs on Postgres, not in-memory SQLite.                                                                                                                                                                       |
| Cache                 | Redis (Cloud's managed cache) in production and local development.                                                                                                                                                                                                           |
| Queue                 | Database driver; failed jobs in the database. Redis was rejected for queues: a cache instance can evict keys under memory pressure and silently drop jobs, and the job load (daily IMDb import, metadata refresh/purge, emails) is tiny. Switching later is a config change. |
| Workers and scheduler | Cloud-managed workers and scheduler. No Horizon. The scheduler is independent of the queue driver.                                                                                                                                                                           |
| Sessions              | Database (starter kit default): durable across cache restarts.                                                                                                                                                                                                               |
| Email                 | A hosted mail API (Resend, Postmark or SES); provider chosen once the domain is known (see [Reelio brand clearance](./15-brand-clearance.md)). Local: Herd mail or the log driver.                                                                                           |
| Environments          | Local and production, plus Cloud preview environments. No staging.                                                                                                                                                                                                           |
| Local setup           | Herd with its Postgres and Redis services, not Sail or Docker. `.env.example` updated to match.                                                                                                                                                                              |
| Object storage        | None for the MVP: TMDB images are hotlinked and there are no uploads.                                                                                                                                                                                                        |

**Carry into the spec.**

- The IMDb import is long-running: give it a generous timeout and a `retry_after` longer than that timeout, or it runs twice.
- Redis holds only disposable cache. Anything that must persist, or be purged under TMDB's 6-month cap, lives in Postgres tables.
- Backups, health check, log hygiene and the deployment pipeline are now specified by [Deployment and operations](./19-deployment-and-operations.md).

## Comments


- From [Deployment and operations](./19-deployment-and-operations.md) (resolved): two corrections. The cache is Laravel Valkey (Redis by Upstash is deprecated), and production queues are Cloud managed queues, not the database driver (queued jobs don't wake a scale-to-zero environment; local development keeps `database`). Production also scales to zero for now.
