# Test strategy and tooling

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

How will the MVP be tested, and with which tools?

- Whether to add Vitest for Vue component and composable tests, and its scope (the PRD's §14.2 list).
- Browser end-to-end coverage of the core journeys: Pest 5's browser testing vs another runner, and which journeys qualify.
- How the Catalog and Rating sources are faked in tests (HTTP fakes vs an in-memory fake implementation vs recorded fixtures).
- What must be green in CI before merging.

## Comments

- From [Infrastructure baseline](./05-infrastructure-baseline.md) (resolved): the test suite runs on Postgres (not in-memory SQLite) with Redis cache locally; Cloud preview environments exist for pre-merge checks.

## Answer

**Layers.**

| Layer                    | Tool                                                              | Covers                                                                                                                                                                                                                                                                                        |
| ------------------------ | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Feature tests (the bulk) | Pest 5 on Postgres, Redis cache                                   | Every behaviour reachable through a request: Inertia props, authorization, validation, the user–movie lifecycle actions, picker candidates, explicit source-failure states.                                                                                                                   |
| Frontend logic           | Vitest via Vite+'s bundled `vp test`, `@testing-library/vue`      | Only client-side logic that makes its own decisions: debounced and cancelled search, optimistic Watchlist toggle with rollback, the Undo window, theme persistence, client-side trailer choice if any. Display-only components get no Vitest tests. The PRD's §14.2 list is narrowed to this. |
| Browser end-to-end       | Pest's browser plugin (`pestphp/pest-plugin-browser`, Playwright) | Five journeys only: discover → details → save; pick from the Watchlist; mark as watched → see it in Watched; see Availability on a detail page; register and log in. Each at a desktop and a mobile viewport (the bottom nav is a separate path), with `assertNoJavascriptErrors`.            |
| Live checks              | A Pest `live` group, excluded by default                          | Real TMDB and the real IMDb dataset URL, to catch fixture drift. Run by hand before launch and when fixtures are re-recorded; never in CI.                                                                                                                                                    |

**Faking the sources.**

1. Feature and browser tests bind in-memory fakes of Reelio's own Catalog source and Rating source interfaces in the container. The fakes can be set up to return given Movies or to fail, so explicit failure states are testable. No feature test knows TMDB's JSON shape.
2. The TMDB adapter is tested with `Http::fake()` against recorded TMDB JSON fixtures (captured with a live key): parsing, director and trailer selection, timeouts, bounded retries, error responses.
3. The IMDb import is tested against a tiny gzipped TSV fixture: a normal row, a missing rating, a malformed row.
4. `Http::preventStrayRequests()` is on for the whole suite.

**CI merge gate.**

- `composer ci:check` stays the main job (vp check, vue-tsc, Pint, PHPStan level 7, Pest) and gains `vp test`, plus Postgres and Redis service containers.
- Browser tests run as a second job, so the Playwright install doesn't slow the main one.
- `main` is branch-protected: both jobs must be green to merge.
- No coverage threshold. What gets tested is decided per change by the `testing-best-practices` rules.
- Cloud preview environments are for manual checks, not a merge gate.

**Carry into the spec.**

- `phpunit.xml` still sets in-memory SQLite and the array cache; switch it to Postgres and Redis to match [Infrastructure baseline](./05-infrastructure-baseline.md).
- New dev dependencies approved by this decision, added when the build starts: `@testing-library/vue` and `pestphp/pest-plugin-browser` (plus its Playwright browser download, locally and in CI).
- Starter-kit tests for removed surfaces (dashboard, welcome page) go with them, per [Visual language and app shell](./06-visual-language-and-shell.md).

- From [Images and performance budget](./27-images-and-performance.md) (resolved): the browser tests gain structural image checks (aspect boxes or dimensions, a single high-priority image on the hero pages, lazy images below the fold). CI gains a JavaScript budget check: at most 150 KB gzipped of initial JavaScript for Discover and a Movie page, read from Vite's build output with no new dependency.
