# Visual language and app shell

Type: prototype
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

What should Reelio look and feel like?

Build a rough, throwaway prototype to react to:

- The dark-first cinematic palette (navy/charcoal background, slate surface, electric-blue accent), the typeface (Inter vs Geist), and a light theme.
- The app shell: desktop top nav (Discover, Search, Watchlist, Watched, Profile) vs the starter kit's sidebar, and a mobile bottom nav (Discover, Search, Watchlist, Profile).
- The movie card: poster, title, year, External rating, the watchlist action, and the missing-poster placeholder.
- The skeleton loading style.

Record which starter-kit layout pieces are kept, replaced, or removed.

## Answer

Chosen from the prototype's variant C ("Backdrop hero + list rows"), using variant A's movie card on the Watchlist and Watched screens. Prototype (throwaway, never merged): branch `prototype/visual-language-and-shell`, route `/prototype/shell` (local only), with `?variant=C&screen=watchlist|watched&theme=…&loading=shimmer`.

**Visual language.**

| Element              | Decision                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Palette              | **Charcoal**, not navy: neutral graphite surfaces, with blue only in the accent. Dark: background `#101215`, surface `#181B20`, raised `#20242B`, line `#2C3139`, text `#ECEEF2`, muted `#9AA1AD`, accent `#4D8DFF` (text, links, focus ring), accent fill `#2860EE` with white text. Light: background `#F4F4F5`, surface `#FFFFFF`, raised `#E8E8EB`, line `#D9D9DE`, text `#111216`, muted `#565964`, accent `#1F5BDB`. Every text pairing meets WCAG AA (body text ≥ 16:1, muted ≥ 6.3:1, accent ≥ 5.3:1, white on accent fill 5.25:1). |
| Rating source labels | Each External rating is shown as its source name in the source's colour, then the score ("IMDb 8.5", "TMDB 8.1"). Dark: IMDb `#F5C518`, TMDB `#5FD4F5`. Light: IMDb `#7A5C00`, TMDB `#00708F`.                                                                                                                                                                                                                                                                                                                                              |
| Typeface             | **Inter** (400/500/600/700, served from Bunny Fonts the way the starter kit loads fonts now). It replaces Instrument Sans. Use tabular numbers for years, scores and dates.                                                                                                                                                                                                                                                                                                                                                                 |
| Theme                | **A user choice, defaulting to Dark.** The navbar has a sun/moon toggle (on mobile too), and the starter kit's Appearance settings page (Light / Dark / System) stays. The choice persists in the starter kit's existing `appearance` cookie. The default changes from `system` to `dark`.                                                                                                                                                                                                                                                  |
| Corners and icons    | Moderately rounded: 8px on posters, 12px on rows, 16px on the hero, pill-shaped nav and toggle. Icons are Lucide.                                                                                                                                                                                                                                                                                                                                                                                                                           |
| Skeletons            | **Shimmer**: one soft highlight sweeps across blocks shaped like the content they replace. It is static under `prefers-reduced-motion`.                                                                                                                                                                                                                                                                                                                                                                                                     |

**App shell (desktop).**

- A top bar with no sidebar.
- The wordmark sits on the left.
- In the centre is a pill-shaped segmented nav: Discover, Search, Watchlist, Watched. The active item is filled.
- On the right are the theme toggle and the Profile avatar.
- On Discover, the top bar floats over a blurred glow taken from the hero backdrop.

**App shell (mobile).**

- A fixed bottom nav with four items: Discover, Search, Watchlist, Profile. It respects the safe area.
- The top bar shrinks to the wordmark and the theme toggle.
- Watched is reached through a Watchlist / Watched switch at the top of both screens. The switch shows on every screen size.

**Footer.** The TMDB logo and notice, plus About, Privacy and Terms links. It sits at the end of every page and is padded clear of the mobile bottom nav.

**Discover.**

- A hero: a full-bleed backdrop of one featured Movie with:
    - the title set large;
    - year, runtime and genres;
    - both External ratings;
    - the overview;
    - "Add to Watchlist" (accent fill) and "Watch trailer" buttons.
- Below the hero, each section is a grid of list rows:
    - each row has a small poster, title, year / runtime, both External ratings, and a bookmark toggle at the end;
    - only Trending is numbered, because it is an ordered list.

**Movie card on Watchlist and Watched** (variant A's card):

- a poster grid, 2 to 6 columns;
- the poster with a bookmark toggle floating on it, so it works on touch without hover (filled with the accent when the Movie is on the Watchlist);
- the title below, clamped to 2 lines;
- the year;
- one External rating: IMDb, falling back to TMDB, else "Not yet rated";
- on Watched, a "Watched <date>" line.

Poster and title share one link target, so the card is one tab stop plus the toggle.

**Missing poster.** A tile in the raised colour with a Lucide film icon and the title in muted text. It is used everywhere a poster would be.

**Starter-kit layout pieces.**

| Piece                                                                                                  | Fate                                                                                                                                               |
| ------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AppSidebarLayout`, `AppSidebar`, `AppSidebarHeader`, `NavMain`, `NavFooter`, `Breadcrumbs`            | **Removed.** There is no sidebar or breadcrumb trail. (Under SSR the starter kit's `ui/sidebar` also caused hydration mismatches at mobile width.) |
| `ui/sidebar` components                                                                                | **Removed** once nothing uses them.                                                                                                                |
| `AppHeaderLayout`, `AppHeader`                                                                         | **Replaced** by the Reelio top bar + bottom nav shell. `AppLayout` points at it.                                                                   |
| `AppShell`, `AppContent`                                                                               | **Replaced** by the new shell; the sidebar branch goes.                                                                                            |
| `NavUser`, `UserMenuContent`, `UserInfo`                                                               | **Kept**, behind the Profile avatar menu (settings, log out).                                                                                      |
| `AppLogo`, `AppLogoIcon`                                                                               | **Replaced** by a Reelio wordmark (still a placeholder until [Reelio brand clearance](./15-brand-clearance.md) is done).                           |
| `AppearanceTabs`, `useAppearance`, `HandleAppearance`                                                  | **Kept**; the default becomes `dark`, and the navbar toggle writes the same cookie.                                                                |
| `settings/Layout` and the settings pages                                                               | **Kept**, rendered inside the new shell.                                                                                                           |
| `AuthLayout` → `AuthSimpleLayout`                                                                      | **Kept**, re-themed. `AuthCardLayout` and `AuthSplitLayout` are **removed** as unused.                                                             |
| `Dashboard` page, `PlaceholderPattern`                                                                 | **Removed**. Discover is the home page.                                                                                                            |
| `Welcome` page                                                                                         | **Removed**. Discover is the homepage (PRD §6.4 "Discovery homepage").                                                                             |
| `ui/skeleton`                                                                                          | **Replaced** by the shimmer style.                                                                                                                 |
| Theme tokens in `app.css`, `app.blade.php` inline background colours, Instrument Sans in `vite.config` | **Replaced** by the charcoal tokens and Inter.                                                                                                     |

**Carry into the spec.**

- Set the font in `app.css`'s `@theme inline` (and the `fonts` entry in `vite.config`). `@theme inline` compiles the literal family into `.font-sans`, so overriding `--font-sans` at runtime does nothing. The prototype was caught out by this.
- Update the inline `html` background colours in `app.blade.php` to the charcoal values, or each theme flashes on load.
- The hero backdrop is the likely LCP element on Discover. Size and preload it under the images and performance budget (still in the fog).
- The prototype's skeleton, focus ring (2px accent, 2px offset) and reduced-motion handling are the reference for the real components.

- From [Images and performance budget](./27-images-and-performance.md) (resolved): Inter 400 and 600 are preloaded through the Vite font helper's `preload` option, and `fontaine` (a new dev dependency, approved) generates metric-adjusted fallbacks. Every image box has a fixed aspect ratio filled with the raised colour, and failed images fall back to the missing-poster tile. The root layout preconnects to `image.tmdb.org`.
