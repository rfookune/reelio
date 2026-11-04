# TMDB terms, attribution and caching limits

Type: research
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

Under TMDB's current API terms, can Reelio, a free public beta that may later be monetized, use TMDB as its Catalog source? Specifically:

- What separates permitted non-commercial use from commercial use, and what does a commercial licence involve?
- What attribution is required (logo, wording, placement), including for Availability data, which TMDB sources from JustWatch?
- How long may metadata, images and Availability be cached or stored locally, and may images be served from TMDB's CDN only or may they be rehosted?
- Are there other restrictions that would shape the design, such as on combining TMDB data with other sources (relevant to [OMDb ratings](./03-omdb-ratings.md)) or on SEO and indexing of TMDB-derived pages?

## Answer

Sources: [TMDB API Terms](https://www.themoviedb.org/api-terms-of-use) (2023-10-20, the binding contract), the site [Terms of Use](https://www.themoviedb.org/terms-of-use) (2024-05-10, made a licence condition by API Terms §1.B), and TMDB's developer docs.

- **Yes for the free beta:** TMDB can be the Catalog source under the free developer licence while Reelio earns nothing from it.
- **Monetization needs a written commercial agreement**, and that agreement may carry fees. This covers revenue earned "directly or indirectly": ads, a paid tier, affiliate links on Streaming service links, sponsorships, and AI features. TMDB publishes no pricing; you contact sales@themoviedb.org. TMDB decides when you apply for the key whether a use is commercial, and can change that decision later.
- **Grey area: SEO.** The terms list "'destination' website" and "driving traffic … for a website" as examples of commercial use, and neither phrase is defined. The site Terms ban creating "an index" of TMDB Materials and building "a business, whether or not for profit". Nothing mentions SEO directly. Before public launch, get TMDB's classification of Reelio in writing, naming the indexable movie pages and the sitemap.
- **TMDB attribution:** use an approved, unmodified TMDB logo, less prominent than Reelio's mark. Show the notice "This website uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB." Link to themoviedb.org, and use the name "TMDB" or "The Movie Database". Put this in a site-wide footer and on an About/Credits page. The FAQ's shorter wording and "About only" placement conflict with the Terms; the Terms win.
- **Availability attribution:** JustWatch must be credited as the source, or TMDB revokes API access. The format is not specified, so put a text credit next to every Availability block and don't use the JustWatch logo without permission. The API returns only a TMDB watch-page `link`, with no deep links to Streaming services.
- **Caching:** nothing obtained from TMDB may be cached for more than 6 months. This covers metadata, images, Availability and persisted Movie rows. Every TMDB-derived record needs a fetch timestamp and must be refreshed or purged before that limit. If the licence ends, which TMDB can do "for any reason", all TMDB Content must be purged. So keep Catalog data separable from user data.
- **Images:** hotlink `image.tmdb.org` at the sizes TMDB publishes. Rehosting is not expressly banned, but copies fall under the 6-month cap. Resizing or making composite images (such as generated OG cards) risks the "no derivatives" clause.
- **Combining with OMDb:** the terms say nothing on this. Showing each External rating labelled with its Rating source appears to be allowed. Don't build a blended score from TMDB data.
- **Other constraints:**
    - No AI/ML use of TMDB Content.
    - No reselling of TMDB data, including through a public API.
    - Respect the roughly 40 req/s limit and any 429 response; fetch on demand rather than bulk-crawling.
    - Don't imply TMDB endorses Reelio.
    - There is no SLA, and the terms can change at any time.

Full findings: branch research/tmdb-terms, file .scratch/reelio-mvp/research/tmdb-terms.md (https://github.com/rfookune/reelio/blob/research/tmdb-terms/.scratch/reelio-mvp/research/tmdb-terms.md)
