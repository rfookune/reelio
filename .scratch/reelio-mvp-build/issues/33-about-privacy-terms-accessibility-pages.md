# 33: About, Privacy, Terms and Accessibility pages

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The four public pages exist, server-rendered, with copy drafted from the spec for the owner to review, and registration shows the Terms line. See the spec's "Privacy, terms and retention", "Accessibility" and "Public pages" sections.

**Blocked by:** [31: SEO, SSR and the indexing flag](./31-seo-ssr-and-indexing-flag.md)

**Status:** resolved

- [x] `/about`: what Reelio is, the TMDB logo and notice, JustWatch's credit and IMDb's credit
- [x] `/privacy`: a plain-English summary, then every section the spec lists (controller, US hosting and transfers, no representative, lawful bases, cookies table, retention table, rights, third-party table, country inference, YouTube after play, backups, Cloud access logs), "Last updated"
- [x] `/terms`: free and permanently non-commercial, beta and 30 days' closure notice, no scraping or automated access, no guarantee of Availability or ratings, data ownership and attribution, limited liability, 13 or older, governing law of the owner's state, "Last updated"
- [x] `/accessibility`: WCAG 2.2 AA target, last review date, known limitations, contact email
- [x] Server-rendered Vue pages, no `v-html` or Markdown; the contact address and governing state are placeholders marked for the owner
- [x] Under the registration button: "By creating an account you agree to the Terms and have read the Privacy Policy" with links, and the 13+ age line; no checkbox
- [x] The owner reviews the drafted copy before this ticket closes

## Note from ticket 13 (2026-11-06)

The cookies table needs the `country` cookie: first-party, strictly necessary, set only when a guest explicitly chooses a country in Where to watch, encrypted and HttpOnly, kept for a year. It holds one of the supported country codes. The detected country is never stored.

## Comments

- Resolution: implemented in `d7f4e0b`, with the placeholders read from the environment in `d776d3d`.
- Routes `about`, `privacy`, `terms` and `accessibility` use `Route::inertia` and render the Vue pages `About`, `Privacy`, `Terms` and `Accessibility`. They share `components/legal/` (`LegalPage`, `LegalTable`, `Placeholder`). The footer and the pages link through Wayfinder route functions.
- Placeholders live in `resources/js/lib/legal.ts` (contact email, owner name, governing state), each shown in a `<mark data-slot="owner-placeholder">`. `d776d3d` reads them from `VITE_CONTACT_EMAIL`, `VITE_OWNER_NAME` and `VITE_GOVERNING_STATE`; the owner reverted that locally and kept the literal placeholders, so fill them in before launch. Vite inlines `VITE_*` at build time, so a running dev server needs a restart.
- The Register page shows the Terms line under the button, with links and the 13+ line, and no checkbox.
- Still open for the owner before launch: the "last reviewed" date on `/accessibility` is the drafting date, not a real ticket 29 run; the Cloud access-log paragraph stays hedged until the build check says whether logs hold `?q=`; the `appearance` cookie's one-year lifetime is assumed; failed-job retention on Cloud is still the spec's build check.
- Tests: `tests/Feature/PublicPagesTest.php` (components and indexability), `tests/Feature/ServerRenderingTest.php` (SSR for the four pages) and `resources/js/pages/legalPages.ssr.test.ts` (copy, credits and the Register line). Feature tests that exercise SSR fail while `public/hot` exists, so stop the dev server before running them.
