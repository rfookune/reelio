# 03: Security headers and CSP

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Every response carries Reelio's security headers, and the nonce-based CSP is enforced from day one in every environment, so later features are built under it. See the spec's "Security" section.

**Blocked by:** [02: App shell and visual language](./02-app-shell-and-visual-language.md)

**Status:** ready-for-agent

- [ ] One app middleware sets the CSP exactly as in the spec, plus `Referrer-Policy: strict-origin-when-cross-origin`, the Permissions-Policy (deny camera, microphone, geolocation, payment, usb; passkey features untouched; autoplay, fullscreen and encrypted-media delegated to `https://www.youtube-nocookie.com`) and `Cross-Origin-Opener-Policy: same-origin`; no COEP
- [ ] Scripts use a fresh per-request nonce through `Vite::useCspNonce()`, including the root view's inline appearance script; while the Vite dev server runs, its origin and websocket are allowed
- [ ] `POST /csp-report` needs no CSRF token or sign-in, always returns 204, strips query strings from `document-uri`, `referrer` and `blocked-uri`, keeps only the directive, those fields and the source location, logs a warning without an IP, and is limited to 10/min per IP
- [ ] The `appearance` cookie is accepted only as `light`, `dark` or `system`; any other value is treated as absent (default `dark`) and is never echoed into the page
- [ ] In production the session cookie is `Secure`, `HttpOnly` and `SameSite=Lax`
- [ ] Feature tests assert the headers on a public page, a private page and an error page, a different nonce per request, the report endpoint's redaction and limit, and the cookie validation
