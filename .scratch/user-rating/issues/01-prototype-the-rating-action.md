# Prototype the rating action

Type: prototype
Blocked by: none
Status: resolved

## Question

What should the control for setting, changing and clearing a **User rating** look and feel like on a Title's page? Read [the spec](../spec.md) first; the rules are settled there (whole number 1–10, unrated is `null`, any time, independent of Watchlist and Watched, signed-in users only, labelled "Your rating" and distinct from the External ratings).

Open design questions for the prototype to answer:

- Form of the input: ten tappable numbers, a stepper, a slider, a select, or stars mapped onto 1–10?
- How the user clears a rating (second tap on the current value, a separate "Clear" action, or both), and how that reads to a screen reader.
- How "unrated" looks, so it is never mistaken for a low score.
- Where it sits on the Movie and TV Show pages relative to the External ratings and the Watchlist and Watched actions, without crowding the hero on a phone.
- Whether saving is instant or confirmed, and what feedback the user gets (compare the existing Watchlist and Watched actions).
- Keyboard and screen-reader behaviour.

## Notes

- Throwaway, per the `prototype` skill: it exists to answer the question above and is then deleted. Do not build the real endpoint, migration, or tests.
- Reuse existing components and conventions where they fit (the Watchlist and Watched controls, the External ratings component, the shared Title hero) and check mobile width.
- Try two or three variants on one page, so they can be compared side by side.

## Answer

The control is a **Rate button that opens a slider panel**, placed in the **actions row** after the Watchlist and Watched actions, with the rated state shown as a **Watched-style chip with a one-tap clear**.

- **Unrated:** an outlined "Rate" button with a grey star. There is no "Your rating" label above it; the button is explicit enough. It must never look like a score.
- **Rated:** a chip with a filled accent star, "Rated **7**/10" and a × button, mirroring the Watched chip. Clicking the text reopens the panel to change the value; the × clears the rating in one tap.
- **Panel:** a native 1–10 slider with the current value shown, and Save. It closes on Escape and on an outside click.
- **Placement:** the actions row won over beside the External ratings, a sidebar card and a floating button, because the rating belongs with the user's other personal actions and does not crowd the hero or overlap content.
- **Rejected for the rated state:** an outlined star and score (too quiet), a solid accent pill (outweighs the primary actions) and a score with a ten-pip meter (repeats what the number says).

Not settled by the prototype, to decide when building ticket 04:
- The panel's Clear link duplicates the chip's ×; recommend dropping it from the panel.
- Phone width, keyboard and screen-reader behaviour were not exercised.
- The TV Show page has Season and Episode controls, so its placement needs its own decision.
- The accent colour is shared with other controls; a distinct colour was not tried.

The prototype itself is on the throwaway branch `prototype/user-rating-action` (last commit `f2b75f7`); it is not on `main`.
