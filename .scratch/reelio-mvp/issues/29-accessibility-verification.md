# Accessibility verification

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

How is WCAG 2.2 AA checked?

- Automated checks (for example axe in the Pest browser journeys from [Test strategy and tooling](./07-test-strategy.md)) and which rules fail the build.
- A manual review checklist (keyboard, screen reader, zoom, reduced motion, contrast in both themes from [Visual language and app shell](./06-visual-language-and-shell.md)) and when it runs.
- The accessibility statement, if any.

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): the Where to watch tabs must follow the WAI-ARIA tabs pattern, including arrow-key movement (the prototype lacks it). Also check: the right column, which is sticky and, when taller than the viewport, pins at its bottom; the hero text contrast over the fading backdrop in both themes; the trailer's play button and its always-present Watch on YouTube link; the 410 page's Remove and Clear actions.
- From [Rate limiting and security headers](./28-rate-limits-and-security.md) (resolved): the 429 line ("You're going too fast. Try again in a moment.") appears inline on Search and in the picker, and as an error toast for Watchlist actions; check that screen readers announce it. Also check the styled 429 error page.
