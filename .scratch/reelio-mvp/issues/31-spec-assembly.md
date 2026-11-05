# Spec assembly

Type: task
Status: resolved
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

## Answer

The draft spec is at [spec.md](../spec.md), assembled on 2026-11-04 from every resolved ticket. It's in the `/to-spec` template, with `Status: needs-triage` until the owner reviews it.

- **Final state only:** each section states the answer as later tickets amended it. For example: Cloud managed queues and Valkey, `synced_at`, catalog-id action endpoints, Removed Movie, and the YouTube-only trailer tiebreak. Every section links its source ticket.
- **Contents:**
    - 90 user stories, including the owner and operator;
    - module, data, route and per-surface decisions;
    - three test seams (HTTP, the source fakes, and `Http::fake()` for the TMDB adapter);
    - the follow-up and out-of-scope lists.
- **Further Notes carry:**
    - the three launch blockers and what each one gates;
    - the rename ticket;
    - build checks and a first-deploy checklist (from 11, 14, 19, 23, 28 and 30);
    - runbooks (restore drill, breach, manual accessibility, PageSpeed);
    - accepted risks.
- **Dependencies:** approved: `@testing-library/vue`, `pestphp/pest-plugin-browser` and `fontaine`. Still needing approval: `aws/aws-sdk-php`, and three that decisions imply but nobody approved: `resend/resend-php` (Laravel's Resend driver requires it), Nightwatch's package and `@sentry/vue`.
- **Owner to confirm:** eleven gaps no ticket settled, each with the reading the spec assumes:
    - the invalid `appearance` fallback;
    - whether `/accessibility` is server-rendered and indexable;
    - how cap-cleared Movies are treated;
    - the shape of the analytics roll-up;
    - how `availability_link_clicked` is recorded;
    - `queue:prune-failed` vs Cloud queues;
    - the undecided endpoint paths;
    - `tagline`;
    - PWA;
    - dependency approvals;
    - a glossary term for cap-cleared Movies.

Next: [Spec review](./32-spec-review.md), a grilling ticket in which the owner settles that list and approves the dependencies. The spec then becomes `ready-for-agent`, ready for `/to-tickets`.
