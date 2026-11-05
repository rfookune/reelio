# 02: App shell and visual language

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Every page sits in Reelio's own shell: the charcoal palette, Inter, dark by default with a theme toggle, a top bar with pill nav on desktop and a bottom nav on mobile, and a footer with the TMDB notice. The starter kit's sidebar, Dashboard and Welcome pages are gone, and `/` shows a placeholder Discover page. See the spec's "Visual language and app shell", "Accessibility" and "Starter-kit changes" sections.

**Blocked by:** None (can start immediately)

**Status:** resolved

- [x] Charcoal tokens for both themes, including the new `control` token used only for control borders, replace the starter-kit tokens; the root view's inline background colours match, so neither theme flashes on load
- [x] Inter (400/500/600/700) is loaded through the Vite font helper and set in `@theme inline`; Inter 400 and 600 are preloaded; `fontaine` (approved dev dependency) generates metric-adjusted fallbacks; Instrument Sans is gone
- [x] The theme defaults to `dark`; a sun/moon toggle in the top bar (mobile too) and the existing Appearance settings page both write the `appearance` cookie
- [x] Desktop: a top bar with the wordmark, a pill nav (Discover, Search, Watchlist, Watched; active item filled) and the theme toggle plus Profile avatar menu (settings, log out); no sidebar or breadcrumbs
- [x] Mobile: a fixed, safe-area-aware bottom nav (Discover, Search, Watchlist, Profile); the top bar shrinks to the wordmark and toggle
- [x] A footer on every page, clear of the bottom nav, with the TMDB logo and exact notice linked to themoviedb.org, and About, Privacy, Terms and Accessibility links in a fixed order (targets may 404 until those pages exist); the wordmark stays more prominent than the TMDB logo
- [x] Reusable missing-poster tile (raised colour, Lucide film icon, muted title) and shimmer skeleton (static under `prefers-reduced-motion`) replace `ui/skeleton`
- [x] Shell accessibility: "Skip to content" first; focus moves to the page's single `<h1>` on each page visit (not partial reloads); one polite live region; `scroll-padding` matching the sticky bars; 2px accent focus ring with 2px offset; 24px minimum targets and 44px nav and toggle targets on mobile
- [x] Removed: the sidebar layouts and components, `ui/sidebar`, `AuthCardLayout`, `AuthSplitLayout`, the Dashboard and Welcome pages and their tests, and `sidebar_state`; kept and re-themed: the user menu, Appearance settings, settings pages (inside the new shell) and `AuthSimpleLayout`
- [x] `/` renders a placeholder Discover page inside the shell; existing auth and settings tests pass

## Comments

- **Token names (decided at build).** Tailwind utilities use the spec's names: `background`, `surface`, `raised`, `line`, `control`, `muted` (text), `accent` (blue text, links, focus ring), `accent-fill` with `accent-fill-foreground`, `imdb` and `tmdb`. The spec's `text` token is `foreground`, to avoid `text-text`. shadcn's `accent` and `muted` meant hover and background fills, which collided with these names, so `ui/*` and the app components were translated to `raised` and `muted`. The non-colliding shadcn names stay as aliases onto the palette, so vendored components keep working: `card` and `popover` map to surface, `primary` to accent fill, `secondary` to raised, `border` to line, `input` to control, and `ring` to accent. A `destructive` pair was added, since the spec has none. Components added later with `shadcn add` need the same translation.
- **Focus ring.** It is an unlayered `:focus-visible` rule, so it wins over the vendored `outline-none` utilities. The shadcn 3px focus rings were removed so controls don't show two indicators. Programmatically focused `<h1>`s and `#main-content` show no ring.
- **Theme state.** The `appearance` cookie is now the single source of truth on the client too. `localStorage` is no longer used, so the server and client can't disagree. The toggle's icon and label switch on the `dark` class with CSS, so they never flash or mismatch between renders. `HandleAppearance` also accepts only `light`, `dark` or `system`; any other value falls back to `dark` and is never echoed into the inline script. Without that check, an unknown value rendered light on the server and dark on the client. This settles that item of [03](./03-security-headers-and-csp.md) early, with its test in `AppearanceTest`.
- **Focus on page visits** uses Inertia's `navigate` event (types `visit` and `history`, not `initial`). Inertia skips that event for same-URL visits, so partial reloads and actions that redirect back keep focus where it is. Later URL-changing updates that shouldn't move focus, such as Search's `?q=`, must use `replace: true`.
- **Guests and mobile.** Signed-out visitors see "Log in" where the avatar goes, and the mobile Profile item goes to log in. The avatar menu is desktop-only, so the settings nav also has Log out.
- **Auth pages.** `AuthSimpleLayout` has the skip link, the live region and the footer, so every page has them. The TMDB logo and the notice form one link to themoviedb.org. The logo is TMDB's unmodified `blue_short` SVG, self-hosted, because 03's CSP is `img-src 'self'`.
- **Toasts.** Sonner's mobile breakpoint is 600px, but the bottom nav shows until `md` (768px). Both Sonner offsets therefore follow one CSS variable, which keeps toasts above the nav below `md`.
- **Unbuilt routes.** Search, Watchlist, Watched and the footer pages use literal paths until their pages land. Hover-prefetching a 404 fails silently.
- **Verification.** The shell was driven by hand in headless Chrome at 1280×800 and 390×844, in both themes:
    - "Skip to content" is the first tab stop;
    - focus lands on the `<h1>` after a visit and after Back;
    - the toggle flips the theme and writes the cookie;
    - mobile nav and toggle targets are 44px or more;
    - Inter 400 and 600 load from the build;
    - the console is clean.

    Automated browser coverage waits for [04](./04-browser-tests-and-sign-in-journey.md) and [36](./36-accessibility-sweep.md). `MissingPoster` and `ShimmerSkeleton` have no consumers until Discover ([19](./19-discover-homepage.md)).
