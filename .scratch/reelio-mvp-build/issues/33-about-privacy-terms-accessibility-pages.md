# 33: About, Privacy, Terms and Accessibility pages

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The four public pages exist, server-rendered, with copy drafted from the spec for the owner to review, and registration shows the Terms line. See the spec's "Privacy, terms and retention", "Accessibility" and "Public pages" sections.

**Blocked by:** [31: SEO, SSR and the indexing flag](./31-seo-ssr-and-indexing-flag.md)

**Status:** ready-for-agent

- [ ] `/about`: what Reelio is, the TMDB logo and notice, JustWatch's credit and IMDb's credit
- [ ] `/privacy`: a plain-English summary, then every section the spec lists (controller, US hosting and transfers, no representative, lawful bases, cookies table, retention table, rights, third-party table, country inference, YouTube after play, backups, Cloud access logs), "Last updated"
- [ ] `/terms`: free and permanently non-commercial, beta and 30 days' closure notice, no scraping or automated access, no guarantee of Availability or ratings, data ownership and attribution, limited liability, 13 or older, governing law of the owner's state, "Last updated"
- [ ] `/accessibility`: WCAG 2.2 AA target, last review date, known limitations, contact email
- [ ] Server-rendered Vue pages, no `v-html` or Markdown; the contact address and governing state are placeholders marked for the owner
- [ ] Under the registration button: "By creating an account you agree to the Terms and have read the Privacy Policy" with links, and the 13+ age line; no checkbox
- [ ] The owner reviews the drafted copy before this ticket closes
