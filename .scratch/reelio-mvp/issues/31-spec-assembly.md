# Spec assembly

Type: task
Status: open
Blocked by: 22, 23, 24, 25, 26, 27, 28, 29, 30
Map: [Reelio MVP](../map.md)

## Question

Assemble every resolved decision on [Reelio MVP](../map.md) into the implementation-ready spec at `.scratch/reelio-mvp/spec.md`, ready for `/to-tickets`.

AFK: the agent writes it from the closed tickets, lists the P1/P2 follow-ups, the launch blockers ([TMDB classification](./16-tmdb-classification.md), [IMDb permission](./18-imdb-permission.md), [Houselight register clearance](./21-houselight-register-clearance.md)), and the dependency approvals the build needs (`aws/aws-sdk-php`), then hands the draft to the owner for review.

## Comments

- From [Rate limiting and security headers](./28-rate-limits-and-security.md) (resolved): bring its deploy checklist into the spec next to the deploy checks from [Deployment and operations](./19-deployment-and-operations.md): the client IP is real and unspoofable, assets don't count toward the edge limit, the HSTS and edge rate-limit settings, and Sentry's IP setting. It adds no dependencies; `spatie/laravel-csp` was rejected in favour of Reelio's own middleware.
