# 18: Guest actions through sign-in

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A guest who presses a Watchlist or Watched control is taken to sign in or register, and the action completes on the page they were on, without waiting for email verification. See the spec's "Guest intent through sign-in" bullets.

**Blocked by:** [15: Save to the Watchlist](./15-save-to-the-watchlist.md)

**Status:** ready-for-agent

- [ ] A guest using a control stores a single-use pending action `{catalog id, action}` in the session (30-minute expiry, latest only), counted in the shared action limit
- [ ] The intended URL is the page the session last recorded on a page load, never a header or request parameter; one guard accepts only relative paths starting with a single `/` (not `//` or `/\`), else Discover
- [ ] After login or registration the pending action is applied idempotently, the user lands back on that page, and the normal toast shows
- [ ] No GET request changes data, and the pending action never travels in a URL
- [ ] Feature tests cover login and registration completion, expiry, single use, and the guard rejecting `//evil.com` and `/\evil.com`
