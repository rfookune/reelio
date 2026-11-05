# Privacy, terms and retention

Type: grilling
Status: resolved
Blocked by: 26
Map: [Reelio MVP](../map.md)

## Question

What do the Privacy and Terms pages say, and what is the published retention policy?

- What's collected and why: the account, Watchlist and Watched facts, first-party analytics ([Analytics and error monitoring](./08-analytics-and-monitoring.md)), the country inferred from the connection at the edge and not stored ([Country and Availability presentation](./11-country-and-availability.md)).
- Third parties: Laravel Cloud (hosting, access logs with IPs for 7 days, which may include search queries from `?q=`; checked at build per [Search experience](./23-search-experience.md)), Nightwatch, Sentry, Resend, TMDB, IMDb, JustWatch ([Deployment and operations](./19-deployment-and-operations.md)).
- Retention: account deletion, backups (up to 7 days), logs, analytics roll-up after 13 months, cached Catalog data ([Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md)).
- The Terms: non-commercial, no guarantee that Availability is current, the attribution notices ([TMDB terms](./01-tmdb-terms.md)), and the product name ([Product name](./20-product-name.md)).

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): trailers are click-to-play from `youtube-nocookie.com`. Nothing loads from YouTube until the visitor presses play, and YouTube may set cookies after that (the Movie page says so under the player). The Privacy page should say the same and list YouTube as a third party.
- From [Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md) (resolved): cached Catalog data holds no personal data. TMDB-derived data is never kept more than 6 months: Movies held by users are refreshed every 3 to 30 days, the cache lives at most 30 days, and Movies nobody holds are purged after 30 days unviewed. User facts are never deleted by any of this.
- From [Rate limiting and security headers](./28-rate-limits-and-security.md) (resolved): CSP violation reports go to Reelio's own `/csp-report`, which strips query strings and logs a warning to Nightwatch without the IP. Sentry runs with its "don't store IP addresses" setting on. Rate limits briefly hold a user id or IP as a counter key in Valkey, for at most an hour. Cloudflare (via Laravel Cloud's edge) sees every request for its firewall and rate limiting.
- From [Accessibility verification](./29-accessibility-verification.md) (resolved): the footer holds About, Privacy, Terms and Accessibility links, always in the same place. The accessibility statement gives a contact email for problems; if Privacy and Terms give a contact address too, use the same one, on the Houselight domain.

## Answer

The framing facts, checked in the code:

- **The database sessions table stores IPs and user agents.** The starter kit's `sessions` table has `ip_address` and `user_agent` columns, and Laravel's `DatabaseSessionHandler` fills them on every request, guests and crawlers included. `sessions.user_id` is indexed but has no foreign key, so deleting an account leaves its other sessions behind until they expire.
- **The remember-me cookie** lasts 400 days (`SessionGuard::$rememberDuration`).
- **The theme** is saved in `localStorage` and the `appearance` cookie, but only when the user picks one (`useAppearance`). `sidebar_state` goes with the sidebar ([Visual language and app shell](./06-visual-language-and-shell.md)).
- **Nothing is scheduled to clean up** expired password-reset tokens or failed jobs. Expired database sessions are removed only by Laravel's lottery garbage collection.
- **The hosting region was never decided.** Cloud's region list needs an API token; confirm the exact region code in the dashboard.
- **The owner is based in the USA.** UK and EU GDPR very likely still apply, because GB, IE, DE, FR, ES and IT are supported countries ([Country and Availability presentation](./11-country-and-availability.md)).

**Who's responsible, and where the data lives**

- The controller is **the owner, as a named individual in the USA**. The Privacy page gives the name, the country and a dedicated contact address on the final domain (e.g. `hello@<domain>`), not a personal one. The same address serves the Terms and the [accessibility statement](./29-accessibility-verification.md).
- **Hosting is in a US region** (Ohio, `us-east-2`, if the dashboard offers it), with **Nightwatch in the US** too, both chosen at app creation. Sentry stays in the EU ([Analytics and error monitoring](./08-analytics-and-monitoring.md)). UK and EU users' data is transferred to the US under the providers' data processing agreements, and the Privacy page says so.
- **No EU or UK representative is appointed for the beta.** GDPR and UK GDPR make a controller outside the EU or UK appoint a local representative unless processing is only "occasional" and unlikely to put people at risk. Reelio processes data continuously but at low risk (no sensitive data, no profiling). This is an **accepted risk** recorded in the spec, to be revisited before the beta grows or before any EU- or UK-targeted promotion.
- **Governing law** for the Terms is the owner's US state of residence (filled in when the copy is drafted), without overriding the consumer rights users have where they live.

**Sessions stop storing IPs and user agents**

- A small session handler extending Laravel's database handler leaves out `ip_address` and `user_agent`, and a migration drops both columns. This keeps "no IP stored" true ([Country and Availability presentation](./11-country-and-availability.md)) and keeps IPs out of the 7-day backups.
- `sessions.user_id` gets a foreign key with cascade delete, so deleting an account signs it out on every device.
- Valkey sessions were rejected: Valkey can evict keys under memory pressure, the same reason Redis was rejected for queues ([Infrastructure baseline](./05-infrastructure-baseline.md)).

**Lawful bases, cookies, no banner**

- **Providing the service** (the account, the Watchlist and Watched) rests on the contract with the user.
- **First-party analytics, security logs, error monitoring and rate limiting** rest on legitimate interests.
- Nothing relies on consent, so there's **no consent banner**:

    | Cookie or storage                        | Set when                            | Why                                                             |
    | ---------------------------------------- | ----------------------------------- | --------------------------------------------------------------- |
    | Session cookie, `XSRF-TOKEN`             | every visit                         | strictly necessary                                              |
    | Remember-me                              | only if "Remember me" is ticked     | requested by the user                                           |
    | `appearance` (cookie and `localStorage`) | only when the user picks a theme    | requested by the user                                           |
    | Country cookie                           | only when the user picks a country  | requested by the user                                           |
    | YouTube's cookies                        | only after the visitor presses play | requested by the user; the Movie page notes it under the player |

**Published retention, and the jobs that make it true**

| Data                                                                                  | Kept                                                                                                                                                                                   |
| ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Account, Watchlist and Watched                                                        | until the user deletes the account (hard-deleted at once), then up to 7 days in backups                                                                                                |
| Unverified accounts with no Watchlist or Watched facts                                | deleted 30 days after registration by a new daily job. Accounts holding facts are spared, because actions need only `auth` ([Watchlist interactions](./13-watchlist-interactions.md)). |
| Sessions                                                                              | 2 hours idle; remember-me up to 400 days or until sign-out                                                                                                                             |
| Password-reset tokens                                                                 | 60 minutes; `auth:clear-resets` runs daily                                                                                                                                             |
| Pending guest action, Undo snapshot                                                   | inside the session (30 minutes, and the latest action only)                                                                                                                            |
| Analytics events                                                                      | 13 months, then rolled into anonymous counts; deleted with the account                                                                                                                 |
| Failed jobs (mail payloads can hold email addresses)                                  | 7 days; `queue:prune-failed --hours=168` runs daily                                                                                                                                    |
| App logs, Cloud's access logs (with IPs), CSP reports, the `account_deleted` log line | 7 days                                                                                                                                                                                 |
| Rate-limit counters                                                                   | at most 1 hour                                                                                                                                                                         |
| Nightwatch                                                                            | 14 days                                                                                                                                                                                |
| Sentry and Resend                                                                     | the provider's plan retention. A **build check** confirms each figure before it's published.                                                                                           |
| Catalog data                                                                          | holds no personal data ([Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md))                                                                                   |

The new daily jobs join the existing 05:00 UTC scheduler slot ([Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md)).

**Users' rights**

- Display name, email, password, preferred country and account deletion are self-serve in settings.
- Requests to access or receive a copy of one's data go to the contact address. The owner answers within one month with a JSON export of the account, Watchlist and Watched. Self-serve export stays the P1 follow-up (FR-021).

**Third parties on the Privacy page**

| Third party                                             | What it receives                                                                             |
| ------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Laravel Cloud (AWS for hosting, Cloudflare at the edge) | every request; all stored data                                                               |
| Resend                                                  | the user's email address and the emails sent to it                                           |
| Laravel Nightwatch                                      | server request and error data with the user id, redacted (no search text, no IP in app logs) |
| Sentry                                                  | browser errors; IP not stored                                                                |
| TMDB's image server (`image.tmdb.org`)                  | every visitor's IP, because posters and backdrops load straight from it                      |
| YouTube (`youtube-nocookie.com`)                        | only after the visitor presses play                                                          |
| TMDB's API, IMDb, JustWatch                             | no personal data: server calls carry only Movie ids, and the IMDb dataset is downloaded      |

Fonts are self-hosted ([Images and performance budget](./27-images-and-performance.md)).

**Minimum age**

- **13 or older**, stated in the Terms and in the line under the registration form. There's no age field.
- The UK Children's Code covers services likely to be used by under-18s, whatever age the Terms set. Reelio already meets its expectations: privacy by default, no profiling, no location tracking, no nudges.

**The Terms**

- Free and permanently non-commercial.
- A beta: features may change, and the service may close with 30 days' email notice, during which users can request their data.
- **Acceptable use:** no scraping, bulk extraction or automated access. This backs the [IMDb permission](./18-imdb-permission.md) email's promise (no bulk download, no API) and TMDB's non-commercial licence. Accounts can be closed for abuse.
- No guarantee that Availability or ratings are current or correct.
- Movie data and images belong to TMDB, IMDb and the rights holders, with the attribution notices ([TMDB terms](./01-tmdb-terms.md), [OMDb ratings](./03-omdb-ratings.md)).
- Liability limited as far as the law allows.
- The product name, Houselight ([Product name](./20-product-name.md)).
- Governing law: the owner's US state, as above.

**Agreeing to the Terms, and changes to them**

- Under the registration button: "By creating an account you agree to the Terms and have read the Privacy Policy", with links. No checkbox.
- Each page shows "Last updated". Material changes are emailed to verified users 14 days before they take effect. The git history is the record.

**Pages**

- `/privacy` and `/terms` are server-rendered public Vue pages, not Markdown, because `v-html` is a lint error ([Rate limiting and security headers](./28-rate-limits-and-security.md)). They're linked from the footer (with About and Accessibility) and from the registration page.
- Each opens with a short plain-English summary, then the full sections.
- The spec carries a section-by-section outline with every fact above. The copy is drafted at build and the owner reviews it.
- **No professional legal review** for the free beta; revisit if usage grows. The pages don't claim compliance with any particular law.

**Shared devices**

- Inertia's `clearHistory` on sign-out and `encryptHistory` on private pages, so Back can't show a signed-out user's Watchlist again.

**Breach response**

A short runbook in the spec:

1. Contain the breach and rotate secrets.
2. Assess what data was involved.
3. If there's a risk to people, notify the UK ICO and the relevant EU authorities within 72 hours. As a controller outside the EU and UK with no representative, Reelio reports to them directly.
4. If the risk is high, email affected users without delay. US state breach laws can also require notifying residents, for example when an email address and password are exposed together.
5. Log every incident, including ones nobody had to be told about.

**Spec follow-up**

- Appointing an EU and a UK representative, if the beta grows.
