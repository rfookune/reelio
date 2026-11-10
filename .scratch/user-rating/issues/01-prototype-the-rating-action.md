# Prototype the rating action

Type: prototype
Blocked by: none
Status: ready-for-agent

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

(To be filled in when the prototype is resolved: the chosen design and why, then set `Status: resolved`.)
