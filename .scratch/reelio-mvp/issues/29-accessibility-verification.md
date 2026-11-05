# Accessibility verification

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

How is WCAG 2.2 AA checked?

- Automated checks (for example axe in the Pest browser journeys from [Test strategy and tooling](./07-test-strategy.md)) and which rules fail the build.
- A manual review checklist (keyboard, screen reader, zoom, reduced motion, contrast in both themes from [Visual language and app shell](./06-visual-language-and-shell.md)) and when it runs.
- The accessibility statement, if any.

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): the Where to watch tabs must follow the WAI-ARIA tabs pattern, including arrow-key movement (the prototype lacks it). Also check: the right column, which is sticky and, when taller than the viewport, pins at its bottom; the hero text contrast over the fading backdrop in both themes; the trailer's play button and its always-present Watch on YouTube link; the 410 page's Remove and Clear actions.
- From [Rate limiting and security headers](./28-rate-limits-and-security.md) (resolved): the 429 line ("You're going too fast. Try again in a moment.") appears inline on Search and in the picker, and as an error toast for Watchlist actions; check that screen readers announce it. Also check the styled 429 error page.

## Answer

The framing facts:

- **Pest's browser plugin** has `assertNoAccessibilityIssues()` (axe-core). Its default level, 1, fails on "serious" and "critical" issues; levels run 0 (critical only) to 3 (minor).
- **The palette** from [Visual language and app shell](./06-visual-language-and-shell.md), recomputed: every text pairing passes AA on bg, surface and raised in both themes (muted on raised is the lowest at 5.71:1; accent on raised 4.80:1). The `line` token is only 1.15 to 1.43:1, which fails 1.4.11 wherever it is a control's only boundary. Accent fill on raised is 2.96:1.
- **No Vue template lint exists without ESLint:** Oxlint's accessibility rules only cover JSX, and `eslint-plugin-vuejs-accessibility` needs ESLint, which the project doesn't use.

**Automated checks (fail the build)**

- `assertNoAccessibilityIssues()` at **level 1** (serious and critical):
    - at the end of each of the five browser journeys ([Test strategy and tooling](./07-test-strategy.md)), at desktop and mobile;
    - in a new **accessibility sweep** browser test, at desktop and mobile, in both themes (set through the `appearance` cookie). It visits every page in its main states:
        - Discover;
        - Search: no query, results and no results;
        - a Movie page and a dropped Movie (410);
        - the Watchlist (empty and with Movies) and Watched;
        - the picker dialog open;
        - the auth pages and the settings pages;
        - the 404, 429 and 500 pages.
- Level 2 isn't used: it adds axe best-practice rules (landmarks, heading order) that aren't WCAG criteria and clash with dialogs and toasts rendered outside the main page. Those are left to the manual review.
- **No template lint.** axe in the browser tests is the automated layer, with no new dependencies.

**Design rules**

- **Control borders (1.4.11):** a new **`control`** colour token, used only for borders that identify a control (inputs, the search field, checkboxes, the genre multi-select). `line` stays for dividers.

    | Theme | `control` | Contrast on bg / surface / raised |
    | ----- | --------- | --------------------------------- |
    | Dark  | `#6B7280` | 3.88 / 3.57 / 3.22                |
    | Light | `#7D808A` | 3.59 / 3.94 / 3.22                |

- **State indicators** (toggle on-states, the active nav pill) need 3:1 against what's next to them. Accent fill on raised is 2.96:1, so an active state never sits on a raised surface.
- **Focus hidden behind sticky bars (2.4.11):** `scroll-padding-top` and `scroll-padding-bottom` on the page match the sticky top bar and the mobile bottom nav. Toasts sit above the bottom nav.
- **Target size (2.5.8):** every control is at least 24×24 CSS px. The most-tapped controls are 44×44 on mobile: the bookmark toggle, the card "⋯" menu, the nav items, the theme toggle and Pick for me. The floating bookmark sits on a solid chip, so it has contrast over any poster.
- **Page changes:** "Skip to content" is the first focusable element. On each page visit (not partial reloads, prefetches or Watchlist actions), focus moves to the page's single `<h1>` (`tabindex="-1"`), and the `<Head>` helper ([Public pages](./14-public-pages-seo.md)) updates the title. Every page has exactly one `<h1>`.
- **What gets announced (4.1.3):** one polite live region in the shell.
    - **Search:** after results render it announces "20 results for 'inter'", "No movies match 'inter'", or the error line. Loading isn't announced.
    - **Picker:** each reveal and each Pick again announces "Picked: {title} ({year})".
    - **429 and error lines** ([Rate limiting and security headers](./28-rate-limits-and-security.md)): announced the same way. The toasts keep their own polite region ([Watchlist interactions](./13-watchlist-interactions.md)).
- **Alt text:**

    | Image                                                    | Alt text                                 |
    | -------------------------------------------------------- | ---------------------------------------- |
    | Posters on cards and in lists (the title is right there) | empty                                    |
    | Hero backdrops and the trailer cover                     | empty                                    |
    | Detail hero poster                                       | empty, because the `<h1>` sits beside it |
    | Streaming service logos                                  | the service name                         |
    | Cast photos                                              | empty, because names show                |
    | TMDB, IMDb and JustWatch logos                           | their names                              |

    The trailer iframe gets `title="Trailer: {title}"`. The play button is labelled "Play trailer: {title}".

- **Language of parts (3.1.2):** `<html lang="en">`. Wherever the original title shows, it carries `lang="{original_language}"`.
- **Accessible authentication (3.3.8):** paste is never blocked. Fields carry the right `autocomplete` values (`email`, `current-password`, `new-password`, `one-time-code`). There's never a CAPTCHA or cognitive test (Cloud's Challenge action is already ruled out). Passkeys stay as the low-effort route.

**Manual review**

The checklist goes in the spec. The owner runs all of it before launch, then the relevant items whenever a release adds or reshapes a UI component. Each run is recorded as a dated entry in the checklist file.

- **Keyboard only:** the five journeys, including the Where to watch tabs' arrow keys ([Movie detail page](./24-movie-detail-page.md)), dialog focus trap and return, Undo, and focus after removing a card.
- **Screen readers:** VoiceOver with Safari on macOS and on iOS, on the same journeys. NVDA isn't required, since there's no Windows machine.
- **Zoom:** 200% text, and 400% (320 CSS px reflow) with no sideways scrolling except in Discover's carousel rows.
- **Text spacing (1.4.12).**
- **Reduced motion:** the shimmer, the picker reveal and the hero fade are all still.
- **Contrast over images** in both themes: the hero text over the fading backdrop, the bookmark chip, the trailer play button.
- **Specific surfaces:** the sticky right column on the Movie page (including when it's taller than the viewport), the 410 page's Remove and Clear actions, the trailer's play button and its Watch on YouTube link, the 429 page and messages.

**Accessibility statement**

- A short `/accessibility` page, linked from the footer next to About, Privacy and Terms, always in the same place (3.2.6).
- It states the target, WCAG 2.2 AA, and the date of the last review.
- It lists known limitations: the YouTube player is YouTube's own, and the Availability data comes from TMDB and JustWatch.
- It gives a contact email for problems. The address waits on the Houselight domain ([Clear Houselight on the official registers](./21-houselight-register-clearance.md)).
