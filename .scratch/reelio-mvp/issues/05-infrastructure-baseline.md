# Infrastructure baseline

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

Where will Reelio run and on what data infrastructure?

- Hosting: Laravel Forge on AWS (the PRD) vs Laravel Cloud vs another option.
- Database: PostgreSQL (the PRD) vs staying on the starter kit's SQLite.
- Cache and queue: Redis vs the starter kit's database drivers.
- Session store.
- Whether local development matches production (e.g. Postgres locally via Sail or Herd).

Decide with the beta's scale, cost, and the "Laravel way" in mind.
