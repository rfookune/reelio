# 02: App shell and visual language

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Every page sits in Reelio's own shell: the charcoal palette, Inter, dark by default with a theme toggle, a top bar with pill nav on desktop and a bottom nav on mobile, and a footer with the TMDB notice. The starter kit's sidebar, Dashboard and Welcome pages are gone, and `/` shows a placeholder Discover page. See the spec's "Visual language and app shell", "Accessibility" and "Starter-kit changes" sections.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Charcoal tokens for both themes, including the new `control` token used only for control borders, replace the starter-kit tokens; the root view's inline background colours match, so neither theme flashes on load
- [ ] Inter (400/500/600/700) is loaded through the Vite font helper and set in `@theme inline`; Inter 400 and 600 are preloaded; `fontaine` (approved dev dependency) generates metric-adjusted fallbacks; Instrument Sans is gone
- [ ] The theme defaults to `dark`; a sun/moon toggle in the top bar (mobile too) and the existing Appearance settings page both write the `appearance` cookie
- [ ] Desktop: a top bar with the wordmark, a pill nav (Discover, Search, Watchlist, Watched; active item filled) and the theme toggle plus Profile avatar menu (settings, log out); no sidebar or breadcrumbs
- [ ] Mobile: a fixed, safe-area-aware bottom nav (Discover, Search, Watchlist, Profile); the top bar shrinks to the wordmark and toggle
- [ ] A footer on every page, clear of the bottom nav, with the TMDB logo and exact notice linked to themoviedb.org, and About, Privacy, Terms and Accessibility links in a fixed order (targets may 404 until those pages exist); the wordmark stays more prominent than the TMDB logo
- [ ] Reusable missing-poster tile (raised colour, Lucide film icon, muted title) and shimmer skeleton (static under `prefers-reduced-motion`) replace `ui/skeleton`
- [ ] Shell accessibility: "Skip to content" first; focus moves to the page's single `<h1>` on each page visit (not partial reloads); one polite live region; `scroll-padding` matching the sticky bars; 2px accent focus ring with 2px offset; 24px minimum targets and 44px nav and toggle targets on mobile
- [ ] Removed: the sidebar layouts and components, `ui/sidebar`, `AuthCardLayout`, `AuthSplitLayout`, the Dashboard and Welcome pages and their tests, and `sidebar_state`; kept and re-themed: the user menu, Appearance settings, settings pages (inside the new shell) and `AuthSimpleLayout`
- [ ] `/` renders a placeholder Discover page inside the shell; existing auth and settings tests pass
