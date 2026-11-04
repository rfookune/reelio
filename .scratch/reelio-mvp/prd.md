# Reelio — Product Requirements Document

**Product:** Reelio  
**Version:** 1.0  
**Status:** Draft — Ready for technical planning  
**Date:** November 4, 2026  
**Platform:** Responsive web application / Progressive Web App  
**Product category:** Movie discovery, watchlist management, and streaming discovery

---

## 1. Executive Summary

### 1.1 Product Overview

Reelio is a modern movie discovery and personal watchlist application designed to help users discover movies, organize what they want to watch, and quickly decide what to watch next.

The application consolidates movie metadata, trailers, ratings, streaming availability, and personal watchlists into a single streamlined interface.

Rather than competing directly with comprehensive movie databases, Reelio focuses on making the process of choosing and tracking movies effortless.

### 1.2 Problem Statement

Movie enthusiasts frequently encounter several frustrations:

- Discovering interesting movies across multiple websites and streaming platforms.
- Forgetting movies recommended by friends or encountered online.
- Maintaining watchlists scattered across streaming services.
- Spending excessive time deciding what to watch.
- Determining which streaming service currently offers a particular movie.
- Comparing ratings from different sources.
- Remembering which movies they have already watched.

Existing services often provide extensive functionality but can feel cluttered, overly social, or optimized for a specific streaming platform.

### 1.3 Proposed Solution

Reelio provides a centralized movie discovery and tracking experience with five core capabilities:

1. **Discover:** Search and explore movies using genres, ratings, release years, popularity, and personalized filters.
2. **Save:** Maintain a personal watchlist accessible from any device.
3. **Decide:** Find something to watch through intelligent filtering and a movie picker.
4. **Watch:** Identify available streaming services and navigate to supported providers.
5. **Track:** Mark movies as watched, record personal ratings, and review viewing history.

### 1.4 Product Vision

*Your next favorite movie is only a few clicks away.*

Reelio should feel fast, visually appealing, intuitive, and enjoyable to use.

### 1.5 Product Principles

- **Simplicity first:** Avoid unnecessary configuration and overwhelming interfaces.
- **Discovery over data:** Present useful information without becoming a complicated movie database.
- **Visual experience:** Make movie artwork a central part of the interface.
- **Minimal friction:** Allow discovery without requiring an account.
- **Personal ownership:** Users control their watchlists and viewing history.
- **Provider independence:** Avoid dependence on a single streaming platform or movie data provider.

---

## 2. Product Goals and Success Metrics

### 2.1 Primary Goals

- Enable users to find relevant movies quickly.
- Provide a reliable, centralized personal watchlist.
- Simplify movie selection.
- Display accurate movie information and available streaming options.
- Encourage repeat usage through an enjoyable experience.

### 2.2 Non-Goals for MVP

The first release will not include:

- Movie or TV streaming playback.
- TV series tracking.
- Social networking or user following.
- Public user reviews.
- AI-generated movie recommendations.
- Automatic imports from streaming-platform watchlists.
- Native iOS or Android applications.
- Subscription billing.
- Complex recommendation algorithms.

### 2.3 Success Metrics

Initial targets are hypotheses to validate during a small public beta.

| Metric | Initial target |
|---|---|
| Search response time | Under 500 ms for cached API responses, excluding network latency |
| Movie detail page load | LCP under 2.5 seconds at the 75th percentile on target mobile connections |
| Watchlist action | One click for authenticated users |
| Search-to-watchlist conversion | 20% of users who open a movie detail |
| Weekly returning users | 30% of activated beta users |
| Movie picker usage | 15% of weekly active users |
| Core API availability | 99.5% monthly during beta |

---

## 3. Target Audience

### 3.1 Primary Persona — Casual Movie Enthusiast

A user who regularly watches movies and wants a convenient place to discover and save interesting titles.

**Needs:**
- Quickly find movies worth watching.
- Maintain a personal watchlist.
- Know where movies are streaming.
- Avoid spending excessive time browsing.

### 3.2 Secondary Persona — Movie Collector

A user who watches movies frequently and enjoys maintaining a personal viewing history.

**Needs:**
- Organize watched and unwatched movies.
- Record personal ratings.
- Filter movies by genre, release year, and rating.
- Review viewing statistics.

### 3.3 Tertiary Persona — Indecisive Viewer

A user who knows they want to watch something but doesn't know what.

**Needs:**
- Discover recommendations with minimal effort.
- Filter by mood, genre, and runtime.
- Randomly select a movie from a watchlist.
- Find titles available on their streaming subscriptions.

---

## 4. Core User Journeys

### Journey A — Discover and Save

1. User opens Reelio.
2. User explores trending movies or searches for a title.
3. User selects a movie.
4. Reelio displays movie details, ratings, trailers, and streaming options.
5. User adds the movie to their watchlist.
6. If unauthenticated, Reelio offers sign-in and returns the user to the intended action.
7. Movie appears in the user's watchlist.

**Expected outcome:** A user can discover and save a movie in under one minute, excluding account creation.

### Journey B — Decide What to Watch

1. User opens the Watchlist page.
2. User selects "Pick for Me."
3. User optionally filters by genre, runtime, or streaming service.
4. Reelio randomly selects a matching movie.
5. User can accept the selection or generate another.
6. User opens movie details or streaming availability.

**Expected outcome:** The user selects a movie without manually reviewing the entire watchlist.

### Journey C — Track a Watched Movie

1. User opens a movie.
2. User marks it as watched.
3. Reelio records the viewing date.
4. User optionally provides a personal rating.
5. Movie appears in viewing history and is removed from the default unwatched watchlist view.

### Journey D — Find a Streaming Platform

1. User opens a movie detail page.
2. Reelio displays available providers for the selected country.
3. Providers are grouped by subscription, rental, and purchase availability where data is available.
4. User selects a supported provider link.
5. Reelio redirects to the provider or relevant discovery page.

---

## 5. Functional Requirements

Priority definitions:

- **P0:** Required for MVP.
- **P1:** Important for the next release.
- **P2:** Future enhancement.

### 5.1 Movie Discovery

**FR-001 — Discovery Homepage (P0)**

The homepage must display curated movie sections, including:

- Trending movies.
- Popular movies.
- Top-rated movies.
- Upcoming releases.
- Genre-based collections.

Each movie card must display a poster, title, release year, and available primary rating.

**Acceptance Criteria:**
- Movies are displayed in responsive grids or horizontal carousels.
- Each movie links to its detail page.
- Loading, empty, and error states are implemented.
- Duplicate titles within the same section are prevented.
- Missing posters use a consistent placeholder.

**FR-002 — Movie Search (P0)**

Users must be able to search movies by title.

Requirements:
- Debounced search input.
- Partial-title matching supported by the data provider.
- Paginated results.
- Search query preserved in the URL.
- Relevant title, poster, and release year displayed.
- Empty-state suggestions.

**FR-003 — Advanced Filtering (P1)**

Users can filter discovery results by:

- Genre.
- Release year or year range.
- Minimum rating.
- Runtime.
- Original language.
- Streaming provider, where supported.

Users can sort results by popularity, rating, release date, or title.

**FR-004 — Movie Detail Page (P0)**

Each movie must have a dedicated detail page displaying:

- Title and original title, when different.
- Poster and backdrop.
- Synopsis.
- Release date.
- Runtime.
- Genres.
- Primary rating and vote count.
- Cast and director.
- Official trailer, when available.
- Streaming availability.
- Watchlist and watched actions.

Related movie recommendations are P1.

**FR-005 — Trailers (P0)**

Display official trailers when available through the movie metadata provider.

The application must not download, rehost, or redistribute copyrighted trailers.

### 5.2 Watchlist Management

**FR-006 — Add to Watchlist (P0)**

Authenticated users can add movies to their personal watchlist.

Acceptance criteria:
- Adding an existing movie is idempotent.
- The interface updates immediately.
- The action is available on movie cards and detail pages.
- Failed requests restore the previous interface state.
- Unauthenticated users are prompted to authenticate.

**FR-007 — Remove from Watchlist (P0)**

Users can remove movies from their watchlist.

**FR-008 — Watchlist Page (P0)**

The watchlist page must support:
- Poster grid and list layouts.
- Sorting by date added, title, and release year.
- Filtering by watched status.
- Empty states.
- Pagination or incremental loading.

**FR-009 — Custom Lists (P2)**

Users can create named collections such as:
- Weekend Movies.
- Sci-Fi Favorites.
- Movies to Watch with Friends.
- Holiday Movies.

A movie can belong to multiple custom lists.

### 5.3 Viewing History

**FR-010 — Mark as Watched (P0)**

Users can mark movies as watched.

A movie can be marked as watched independently of whether it was previously added to a watchlist.

**FR-011 — Viewing Date (P1)**

Users can optionally record the date they watched a movie.

**FR-012 — Personal Rating (P1)**

Users can rate movies using a 1–10 scale, supporting half-point increments.

**FR-013 — Viewing Statistics (P2)**

Display:
- Total movies watched.
- Movies watched by month/year.
- Favorite genres.
- Average personal rating.
- Total viewing hours.

### 5.4 Movie Picker

**FR-014 — Random Movie Picker (P0)**

Users can randomly select a movie from their unwatched watchlist.

Requirements:
- Exclude watched movies by default.
- Respect applied filters.
- Provide a "Pick Again" action.
- Display the selected movie's essential details.
- Handle empty candidate sets.

**FR-015 — Advanced Movie Picker (P1)**

Allow filtering by:
- Genre.
- Maximum runtime.
- Release decade.
- Minimum rating.
- Streaming provider.
- Mood-based categories, if a reliable mapping is defined.

The MVP picker must use uniform random selection. Weighted recommendations are deferred.

### 5.5 Streaming Availability

**FR-016 — Streaming Provider Information (P0)**

Display streaming availability when supported by the selected data provider.

Requirements:
- Country-aware availability.
- Subscription, rental, and purchase categories.
- Provider names and logos.
- Attribution required by the provider.
- Clear messaging when information is unavailable.

The user's country defaults to a configured locale and can be changed manually.

**FR-017 — Streaming Service Preferences (P1)**

Users can select the streaming services they subscribe to.

These preferences can be used to filter movie discovery and watchlist results.

**FR-018 — Provider Links (P1)**

Where licensed deep links are available, users can navigate directly to a movie on the selected platform.

If only a general provider or discovery link is available, the interface must not imply it links directly to the title.

### 5.6 Authentication and Accounts

**FR-019 — Authentication (P0)**

Support:
- Email and password registration.
- Login and logout.
- Password reset.
- Email verification.

Google sign-in is P1.

**FR-020 — User Profile (P0)**

Users can manage:
- Display name.
- Email address.
- Password.
- Preferred country.
- Account deletion.

**FR-021 — Data Export (P1)**

Users can export their watchlist, watched status, and ratings in JSON or CSV.

### 5.7 External Ratings

**FR-022 — Primary Movie Rating (P0)**

Display the movie data provider's rating and vote count with explicit source labeling.

**FR-023 — Additional Rating Sources (P2)**

Potential integrations:
- IMDb.
- Rotten Tomatoes.
- Metacritic.

Additional ratings must only be displayed when available through an authorized source under appropriate licensing terms.

No ratings may be scraped or inferred from unrelated scores.

---

## 6. User Interface and Experience

### 6.1 Design Direction

Reelio should use a modern, cinematic design with:

- Dark-first visual styling.
- Large movie artwork.
- Clean typography.
- Minimal navigation.
- Subtle transitions.
- Responsive card layouts.
- Consistent spacing and visual hierarchy.

### 6.2 Suggested Visual Identity

| Element | Recommendation |
|---|---|
| Primary background | Deep navy / charcoal |
| Surface | Slightly lighter slate |
| Accent | Electric blue |
| Typography | Inter or Geist |
| Corners | Moderately rounded |
| Icons | Lucide |
| Default theme | Dark |
| Secondary theme | Light |

### 6.3 Navigation

**Desktop navigation:**
- Discover
- Search
- Watchlist
- Watched
- Profile

The Movie Picker is accessible through a prominent action on the Watchlist page.

**Mobile navigation:**
- Discover
- Search
- Watchlist
- Profile

Watched movies can be accessed through the Watchlist page on mobile.

### 6.4 Required Screens

| Screen | MVP |
|---|---|
| Discovery homepage | Yes |
| Search results | Yes |
| Movie details | Yes |
| Watchlist | Yes |
| Watched movies | Yes |
| Random movie picker | Yes |
| Login / registration | Yes |
| Account settings | Yes |
| Advanced filters | Later |
| Statistics dashboard | Later |
| Custom collections | Later |

### 6.5 UX Requirements

- Responsive layouts for mobile, tablet, and desktop.
- Keyboard-accessible interactive elements.
- Visible focus indicators.
- Accessible labels and meaningful image alternatives.
- Skeleton loaders for asynchronous content.
- Clear empty and error states.
- Optimistic watchlist interactions with rollback.
- Respect reduced-motion preferences.
- WCAG 2.2 AA as the accessibility target.

---

## 7. Technical Architecture

### 7.1 Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12+ |
| Language | PHP 8.3+ |
| Frontend | Vue 3 |
| Frontend language | TypeScript |
| Application framework | Inertia.js |
| Styling | Tailwind CSS v4 |
| UI components | shadcn-vue |
| Database | PostgreSQL |
| Cache | Redis |
| Queue | Laravel Queues |
| Authentication | Laravel session authentication |
| External movie data | TMDB |
| Testing | Pest + Vitest |
| Deployment | Laravel Forge / AWS |
| Storage | S3-compatible object storage if needed |

### 7.2 Architecture Overview

The application follows a modular Laravel monolith architecture.

**Frontend → Laravel application → Domain services → Database / Cache / External APIs**

Primary backend modules:

- Movie Catalog
- Movie Search
- Watchlist
- Viewing History
- Streaming Availability
- Movie Picker
- User Accounts

External movie metadata must be accessed through an abstraction layer rather than directly throughout controllers and components.

### 7.3 External Movie Data

**Primary proposed provider: The Movie Database (TMDB).**

TMDB can supply movie discovery, metadata, artwork references, cast, trailers, and provider information.

Before public launch, confirm the current API terms, attribution requirements, caching restrictions, and whether the intended commercial or noncommercial use is permitted.

**Provider abstraction:**

```php
interface MovieProvider
{
    public function search(string $query, int $page = 1): array;

    public function discover(array $filters): array;

    public function find(int|string $externalId): MovieData;

    public function trending(): array;

    public function watchProviders(
        int|string $externalId,
        string $country
    ): array;
}
```

This interface is illustrative; exact method signatures should be finalized during technical design.

### 7.4 External API Strategy

- All TMDB API calls originate from the backend.
- API credentials must never be exposed to the browser.
- Frequently requested movie data should be cached within provider-permitted limits.
- API failures must produce graceful fallback states.
- External requests must have defined timeouts and bounded retries.
- Rate limits must be respected.
- Provider-specific identifiers must be stored with their source.

### 7.5 Authentication Strategy

For the initial Inertia application, use Laravel's session-based authentication with CSRF protection.

Laravel Sanctum is not necessary solely for the Inertia frontend. Introduce token-based API authentication only if separate mobile applications or third-party API consumers become requirements.

---

## 8. Data Model

### 8.1 Users

**users**

| Field | Type |
|---|---|
| id | UUID or BIGINT |
| name | VARCHAR |
| email | VARCHAR, unique |
| password | VARCHAR |
| email_verified_at | TIMESTAMP, nullable |
| preferred_country | CHAR(2) |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

### 8.2 Movies

**movies**

| Field | Type |
|---|---|
| id | BIGINT |
| provider | VARCHAR |
| provider_movie_id | VARCHAR |
| title | VARCHAR |
| original_title | VARCHAR, nullable |
| overview | TEXT, nullable |
| release_date | DATE, nullable |
| runtime | INTEGER, nullable |
| poster_path | VARCHAR, nullable |
| backdrop_path | VARCHAR, nullable |
| original_language | VARCHAR, nullable |
| vote_average | DECIMAL, nullable |
| vote_count | INTEGER |
| metadata_synced_at | TIMESTAMP, nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

Unique index: `(provider, provider_movie_id)`.

The local movie record represents a cached reference to external metadata, not an authoritative replacement for the provider.

### 8.3 User Movies

**user_movies**

| Field | Type |
|---|---|
| id | BIGINT |
| user_id | Foreign key |
| movie_id | Foreign key |
| is_watchlisted | BOOLEAN |
| watchlisted_at | TIMESTAMP, nullable |
| watched_at | TIMESTAMP, nullable |
| personal_rating | DECIMAL(3,1), nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

Unique index: `(user_id, movie_id)`.

For the MVP, this structure supports one watched date per movie. Multiple viewings can be supported later through a separate viewing-events table.

A watched movie may remain watchlisted if the user explicitly chooses to keep it there. The default watchlist view excludes watched titles.

### 8.4 User Streaming Services

**user_streaming_services** — P1

| Field | Type |
|---|---|
| id | BIGINT |
| user_id | Foreign key |
| provider | VARCHAR |
| provider_service_id | VARCHAR |
| created_at | TIMESTAMP |

Unique index: `(user_id, provider, provider_service_id)`.

### 8.5 Future Tables

- `lists`
- `list_movies`
- `movie_viewings`
- `user_preferences`
- `movie_provider_snapshots`

Do not create future tables before their corresponding features are implemented.

---

## 9. API and Application Routes

For the MVP, use Laravel web routes with Inertia pages and JSON endpoints for interactive operations.

### 9.1 Public Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/` | Discovery homepage |
| GET | `/search` | Movie search |
| GET | `/movies/{movie}` | Movie details |
| GET | `/discover` | Discovery catalog |
| GET | `/api/movies/search` | Search JSON |
| GET | `/api/movies/{movie}/providers` | Streaming availability |

### 9.2 Authenticated Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/watchlist` | User watchlist |
| POST | `/watchlist/{movie}` | Add movie |
| DELETE | `/watchlist/{movie}` | Remove movie |
| GET | `/watched` | Viewing history |
| PUT | `/movies/{movie}/viewing` | Update watched status/rating |
| DELETE | `/movies/{movie}/viewing` | Clear viewing record |
| POST | `/watchlist/pick` | Random movie selection |
| GET | `/settings` | Account settings |

All state-changing routes require authentication, authorization, and CSRF protection.

### 9.3 API Response Conventions

- Standardized validation errors.
- Consistent pagination metadata.
- Explicit provider failure states.
- Stable movie identifiers.
- Appropriate HTTP status codes.
- No exposure of provider credentials.

---

## 10. Business Rules

### 10.1 Watchlist Rules

- Each user can have only one primary watchlist entry per movie.
- Adding a duplicate movie must not create a duplicate record.
- Removing a movie from the watchlist must not erase its viewing history or personal rating.
- Watched movies are excluded from the default unwatched view.
- Users can manually restore watched movies to their watchlist.

### 10.2 Rating Rules

- Personal ratings range from 1.0 to 10.0.
- Half-point increments are supported.
- Ratings are optional.
- A rating does not automatically mark a movie as watched.
- External ratings must retain source attribution.

### 10.3 Movie Picker Rules

- Default candidate set: unwatched movies on the user's watchlist.
- Apply all selected filters before selection.
- Select uniformly at random from the resulting candidate set.
- Never return a movie outside the candidate set.
- Display a clear message when no movies qualify.

### 10.4 Streaming Availability Rules

- Streaming availability is country-specific.
- Availability data must include a freshness indicator when practical.
- A missing provider does not necessarily mean a movie is unavailable.
- Reelio must not guarantee that a title is currently playable.
- Provider attribution and linking must comply with data licensing terms.

### 10.5 Data Ownership Rules

- Users can only modify their own watchlist and viewing history.
- Public movie metadata may be shared across user accounts.
- Deleting an account removes or anonymizes personal records according to the published retention policy.

---

## 11. Performance and Nonfunctional Requirements

### 11.1 Performance

- Lazy-load poster images.
- Use appropriately sized image variants.
- Cache eligible external metadata.
- Avoid repeated provider requests for the same movie.
- Paginate large result sets.
- Minimize unnecessary frontend JavaScript.
- Use database indexes for user/movie lookups.

### 11.2 Security

- HTTPS required in production.
- Secure, HTTP-only session cookies.
- CSRF protection.
- Rate limiting on authentication and search endpoints.
- Server-side authorization policies.
- Validation of all incoming requests.
- External API credentials stored in environment secrets.
- Safe handling of third-party URLs.
- Protection against open redirects.

### 11.3 Reliability

- Graceful handling of TMDB outages.
- Defined cache expiration policies.
- Monitoring for API failures and application exceptions.
- Database backups.
- Health-check endpoint.
- Logging without unnecessary personal information.

### 11.4 Privacy

- Collect only necessary account information.
- No third-party advertising SDKs in MVP.
- No selling user viewing history.
- Provide account deletion.
- Document third-party data sharing.
- Obtain consent where required for nonessential analytics or cookies.

### 11.5 SEO

Public discovery and movie pages should support:
- Descriptive page titles.
- Meta descriptions.
- Open Graph images.
- Canonical URLs.
- Sitemap generation.
- Structured movie metadata where appropriate.

Private watchlist and account pages must not be indexed.

---

## 12. Analytics and Observability

### 12.1 Product Events

| Event | Description |
|---|---|
| `movie_searched` | Search submitted |
| `movie_viewed` | Movie details opened |
| `movie_watchlisted` | Movie added to watchlist |
| `movie_unwatchlisted` | Movie removed |
| `movie_marked_watched` | Movie marked as watched |
| `movie_rated` | Personal rating submitted |
| `movie_picker_used` | Picker invoked |
| `streaming_provider_clicked` | Provider link selected |
| `account_registered` | New account created |

### 12.2 Operational Monitoring

Monitor:
- External API response times and failures.
- Cache hit rates.
- Search latency.
- Queue failures.
- Authentication failures.
- Database performance.
- Frontend errors.

Analytics must avoid collecting raw sensitive account information or unnecessary personal data.

---

## 13. MVP Release Plan

### Milestone 1 — Foundation

**Estimated effort: 6–10 hours**

Deliverables:
- Laravel + Vue + Inertia project.
- Tailwind and component library setup.
- Database configuration.
- Authentication.
- Application layout and navigation.
- TMDB provider abstraction.

### Milestone 2 — Movie Discovery

**Estimated effort: 10–16 hours**

Deliverables:
- Homepage discovery sections.
- Search.
- Movie detail pages.
- Movie cards.
- Trailer integration.
- Metadata caching.
- Loading and error states.

### Milestone 3 — Watchlist and History

**Estimated effort: 8–14 hours**

Deliverables:
- Add/remove watchlist actions.
- Watchlist page.
- Mark as watched.
- Watched movie history.
- Basic sorting and filtering.
- Authorization tests.

### Milestone 4 — Decision and Streaming Features

**Estimated effort: 6–10 hours**

Deliverables:
- Random movie picker.
- Streaming provider display.
- Country selection.
- Empty-state handling.
- Provider attribution.

### Milestone 5 — Polish and Launch

**Estimated effort: 8–14 hours**

Deliverables:
- Mobile responsiveness.
- Accessibility review.
- Performance optimization.
- Automated tests.
- Production deployment.
- Error monitoring.
- Privacy and terms pages.
- Beta testing.

**Total estimated MVP effort: 38–64 development hours.**

This is a planning estimate for an experienced developer, excluding major unexpected provider, licensing, or infrastructure issues.

---

## 14. Testing and Acceptance Strategy

### 14.1 Backend Testing

Use Pest for:
- Movie provider adapters.
- Watchlist CRUD operations.
- Viewing status updates.
- Authorization policies.
- Movie picker filtering.
- Duplicate prevention.
- API failure handling.
- Rating validation.

External provider responses should be mocked in automated tests.

### 14.2 Frontend Testing

Use Vitest for:
- Movie card components.
- Search interactions.
- Watchlist controls.
- Filtering.
- Movie picker state.
- Loading and error states.

Browser-based end-to-end tests should cover the most important user journeys.

### 14.3 MVP Exit Criteria

The MVP is ready for public beta when:

- A visitor can discover and search for movies.
- A visitor can open movie details and watch trailers where available.
- A user can register and authenticate.
- A user can add and remove watchlist entries.
- A user can mark movies as watched.
- A user can use the random movie picker.
- Streaming availability is displayed where supported.
- Account data is protected by authorization.
- Core workflows function on mobile and desktop.
- Automated tests pass.
- Provider attribution and usage rights have been reviewed.
- No known critical security or data-loss defects remain.

---

## 15. Future Roadmap

### Version 1.1 — Better Discovery

- Advanced filtering.
- Streaming subscription preferences.
- Personal ratings.
- Similar movie recommendations.
- More sorting options.
- Enhanced movie picker.

### Version 1.2 — Personal Organization

- Custom watchlists.
- Viewing statistics.
- Multiple viewing dates.
- Data import/export.
- Shareable lists.
- Google sign-in.

### Version 2.0 — Personalized Experience

- Preference-based recommendations.
- AI-assisted discovery.
- Natural-language searches such as "fun sci-fi movies under two hours."
- Collaborative movie picking.
- Shared watchlists.
- Notifications when saved movies become available on selected streaming services, subject to licensed data access.

### Potential Long-Term Expansion

- TV series support.
- Native mobile applications.
- Browser extension for saving movies.
- Integrations with authorized third-party watchlist providers.
- Public profiles and optional social discovery.

---

## 16. Risks and Dependencies

| Risk | Impact | Mitigation |
|---|---|---|
| Movie data provider changes | High | Provider abstraction and API monitoring |
| Movie metadata licensing | High | Review usage terms before launch |
| Streaming availability inaccuracies | Medium | Attribution, country context, freshness messaging |
| IMDb/Rotten Tomatoes access restrictions | Medium | Defer additional ratings until authorized |
| Similar competing applications | Medium | Focus on simple UX and movie selection |
| Scope creep | High | Strict P0/P1/P2 separation |
| Brand-name conflicts | High | Trademark and domain clearance before public launch |
| External API outages | Medium | Caching and graceful degradation |

---

## 17. Open Product Decisions

The following decisions should be finalized before implementation:

1. **Authentication:** Require an account to save movies, or allow temporary browser-local watchlists?
2. **Ratings:** Use TMDB ratings exclusively for MVP, or investigate licensed alternatives?
3. **Country support:** Launch with US streaming availability only, or support international markets immediately?
4. **Movie picker:** Restrict to saved movies or also allow discovery from the broader movie catalog?
5. **Personal ratings:** Include in MVP or defer to Version 1.1?
6. **Branding:** Confirm whether Reelio can be used publicly without unacceptable trademark or naming risk.
7. **Monetization:** Keep the product free indefinitely or leave room for a future premium tier?

### Recommended Initial Decisions

- Require authentication for persistent watchlists.
- Use TMDB as the initial movie metadata source, subject to licensing verification.
- Support US availability first, with configurable country support.
- Keep the initial movie picker limited to unwatched watchlist entries.
- Defer personal ratings to Version 1.1.
- Treat Reelio as a working name until brand clearance is complete.
- Launch as a free product without subscription infrastructure.

---

## 18. Definition of Product Success

Reelio succeeds when a user can open the application, discover a movie worth watching, save it for later, and quickly decide what to watch when the time comes.

The MVP should not attempt to become another IMDb or Letterboxd.

Its defining experience should be:

**Discover something great. Save it. Watch it. Repeat.**

---

**End of PRD — Reelio v1.0**
