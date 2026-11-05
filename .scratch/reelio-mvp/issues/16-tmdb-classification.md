# Confirm TMDB's classification of Reelio

Type: task
Status: open
Map: [Reelio MVP](../map.md)

## Question

Will TMDB classify Reelio, a free, permanently non-commercial public site with indexable movie pages and a sitemap, as non-commercial use?

This is HITL: the human emails TMDB (sales@themoviedb.org, or applies for the API key with an honest description) using the five questions listed in the [TMDB terms research](https://github.com/rfookune/reelio/blob/research/tmdb-terms/.scratch/reelio-mvp/research/tmdb-terms.md), then records TMDB's written answer here. A "commercial" answer is a **launch** blocker, not a spec blocker. [Public pages: SSR, SEO metadata and sitemap scope](./14-public-pages-seo.md) should design for either answer (e.g. the sitemap scope can be toggled) rather than wait on this.

## Comments

- From [Public pages: SSR, SEO metadata and sitemap scope](./14-public-pages-seo.md) (resolved): indexing ships behind a config flag that defaults to off. A favourable written answer from TMDB is what turns it on; an unfavourable one keeps it off.
