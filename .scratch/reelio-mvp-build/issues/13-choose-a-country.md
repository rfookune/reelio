# 13: Choose a country

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Availability follows the visitor's country: detected from the edge, overridable by an explicit choice that's remembered, and always named. See the spec's "Country and Availability" precedence rules.

**Blocked by:** [12: Where to watch on the Movie page](./12-where-to-watch-on-the-movie-page.md)

**Status:** resolved

- [x] Country precedence: signed-in `preferred_country`, then the guest country cookie, then the edge country header if supported, then US; detection is recomputed per request and never stored
- [x] A "Showing {country} ▾" selector lists only supported countries; choosing writes the guest cookie (strictly necessary, ignored unless it names a supported country) or the user's `preferred_country`
- [x] `users.preferred_country` (nullable) is editable in Profile settings
- [x] On sign-in or registration a guest's explicit choice is copied to the account only if the account has none
- [x] An unsupported detected country shows "Showing US availability. Your country isn't supported yet" with the selector
- [x] Feature tests cover precedence, the cookie validation, the copy on sign-in and the unsupported notice
