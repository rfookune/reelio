# 32: Sitemap

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** While indexing is on, search engines get a daily sitemap of every Movie Reelio has details for. See the spec's sitemap bullet.

**Blocked by:** [31: SEO, SSR and the indexing flag](./31-seo-ssr-and-indexing-flag.md)

**Status:** ready-for-agent

- [ ] A daily job on `imports` (05:00 UTC slot) builds a sitemap index split at 50k URLs, listing every Movie row that has details (no Removed or cap-cleared Movies) with `lastmod` from `synced_at`
- [ ] It runs and is served only while the indexing flag is on; off means no sitemap
- [ ] `robots.txt` references the sitemap when the flag is on
- [ ] Feature tests cover inclusion, exclusion and the flag
