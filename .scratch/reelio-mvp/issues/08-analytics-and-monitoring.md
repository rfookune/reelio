# Analytics and error monitoring

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

How will Reelio measure its §2.3 success metrics and catch failures, without compromising its §11.4 privacy commitments?

- Product analytics tool (self-hosted vs SaaS vs server-side events only), and whether a cookie or consent banner is needed.
- The final MVP event list (PRD §12.1) and what properties each event carries without collecting personal data.
- Error monitoring for backend and frontend (e.g. Nightwatch, Sentry, or Laravel's built-ins).
- Operational metrics: outbound API latency and failures, cache hit rate.
