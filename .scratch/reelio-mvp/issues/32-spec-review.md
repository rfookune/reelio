# Spec review

Type: grilling
Status: resolved
Blocked by: 31
Map: [Reelio MVP](../map.md)

## Question

Does the owner accept the draft [spec](../spec.md) as the locked, implementation-ready spec?

This is HITL: the owner answers; the agent doesn't answer for them. Work through:

- every item in the spec's **Owner to confirm** list (under Further Notes). Each has an assumed or recommended reading to accept or change;
- the dependencies awaiting approval, listed in the spec's **Dependencies** section;
- anything in the draft the owner reads differently from the source tickets.

Resolving it means: apply the answers to `spec.md`, clear the Owner to confirm list (or move anything deferred to Out of Scope), and set the spec to `Status: ready-for-agent`, ready for `/to-tickets`.

## Answer

The owner accepted every recommendation on 2026-11-04 and read nothing else in the draft differently. The [spec](../spec.md) is updated and set to `Status: ready-for-agent`.

1. **`appearance` cookie:** an invalid value is treated as no cookie, so the default, `dark`, applies.
2. **`/accessibility`:** server-rendered and indexable when the flag is on, like Privacy and Terms.
3. **Cap-cleared Movies** (cleared by the 170-day guard, not removed):
    - excluded from picker candidates, the sitemap and the paginated list query;
    - their Movie page tries the in-request refresh;
    - if that fails, the page is a 503 with `Retry-After` and `noindex`, offering Remove and Clear to signed-in users who hold facts. Never a 410.
4. **Analytics roll-up:** one row per day and event name with a count, and no `user_id` or properties, except that `movie_picker_used` keeps the total of its `pool_size`.
5. **`availability_link_clicked`:** recorded by a same-origin redirect route. It writes the event (signed-in users only), then 302s to the TMDB watch page built from `tmdb_id` and a supported country. The route is `noindex` and linked with `rel="nofollow"`.
6. **Failed jobs:** the daily `queue:prune-failed` stays. A build check finds how long Cloud's Queues dashboard keeps failed jobs, and the Privacy page publishes that figure. If Cloud keeps them indefinitely, the owner deletes them weekly.
7. **Undo, picker and country-selection paths:** set at build.
8. **`tagline`:** a nullable `movies` column.
9. **No PWA in the MVP.** It's a spec follow-up.
10. **Approved dependencies:**
    - `aws/aws-sdk-php` (Cloud's docs confirm managed queues require it, with Laravel 13.19 or newer; v13.40 is installed);
    - `resend/resend-php` (Laravel's Resend driver requires it);
    - `laravel/nightwatch` (the agent package, per Nightwatch's docs);
    - `@sentry/vue`.
11. **No glossary term for cap-cleared Movies.** It's an operational state, not a domain concept, so `GLOSSARY.md` is unchanged.
