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
- From [Accessibility verification](./29-accessibility-verification.md) (resolved): the spec carries the manual accessibility checklist (with a dated log of runs), the new accessibility sweep browser test, and a new `control` colour token that amends the palette in [Visual language and app shell](./06-visual-language-and-shell.md) (dark `#6B7280`, light `#7D808A`, used only for control borders).
- From [Privacy, terms and retention](./30-privacy-terms-and-retention.md) (resolved): the spec also carries:
    - the hosting region decision (US, with Nightwatch in the US), which [Deployment and operations](./19-deployment-and-operations.md) never set;
    - the session changes (no IP or user agent columns, cascading `sessions.user_id`), amending [Infrastructure baseline](./05-infrastructure-baseline.md);
    - three new daily jobs in the 05:00 UTC slot (unverified-account cleanup, `auth:clear-resets`, `queue:prune-failed`);
    - the Privacy and Terms outlines, the registration-page line, `clearHistory` and `encryptHistory`, and the breach runbook;
    - build checks for Sentry's and Resend's retention;
    - the accepted risk of having no EU or UK representative.
