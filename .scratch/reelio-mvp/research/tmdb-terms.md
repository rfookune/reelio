# TMDB terms, attribution and caching limits

Research for [issue 01](../issues/01-tmdb-terms.md). Sources were read on 2026-11-04. This is a reading of published terms, not legal advice.

## Answer

- **Yes, for the free beta.** TMDB can be Reelio's Catalog source under TMDB's free developer licence as long as Reelio earns no revenue from it. That means no ads, no fees, no premium tier, no affiliate or referral revenue, and no sponsorships.
- **Monetization needs a commercial licence.** Any revenue tied to TMDB Content, "directly or indirectly", counts as commercial use. Commercial use requires a separate written agreement with TMDB, which "may be subject to … payment of fees". Pricing is not public; you contact sales@themoviedb.org. This includes affiliate links on Streaming service buttons.
- **The commercial line has grey areas.** TMDB decides whether a use is commercial when you apply for the key, and can revise that later. Its examples of commercial use include an undefined "'destination' website" and "driving traffic … for a website". The site Terms of Use, which the API licence incorporates, also ban building "a business, whether or not for profit" and creating "an index" of TMDB Materials. Neither document mentions SEO. Reelio's plan (public, indexable movie pages plus a sitemap) is close enough to these clauses that we should **get TMDB's classification in writing before the public launch**.
- **TMDB attribution** has four parts:
  - an approved, unmodified TMDB logo, less prominent than Reelio's own mark;
  - the notice *"This website uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB."*, placed "prominently";
  - the name "TMDB" or "The Movie Database";
  - a link to https://www.themoviedb.org.

  Recommendation: a site-wide footer plus an About/Credits page.
- **Availability attribution:** TMDB's docs require crediting **JustWatch** as the source of Availability data, or TMDB will revoke API access. Neither TMDB nor JustWatch says how. Recommendation: a text credit ("Availability data by JustWatch") next to every Availability block. Use JustWatch's logo only if JustWatch grants permission.
- **Caching:** nothing obtained from TMDB may be cached "for longer than 6 months". That covers metadata, images and Availability alike. The terms don't distinguish a persisted `movies` row from a cache. If the licence ends, all TMDB Content, including cached content, must be purged promptly.
- **Images:** the terms don't ban rehosting outright. But a rehosted copy is still subject to the 6-month cap, and transforming it risks the "no derivatives" clause. Recommendation: hotlink `image.tmdb.org` at the sizes TMDB publishes, and don't rehost.
- **Other constraints that affect the design:**
  - No AI/ML use of TMDB Content without a licence. This rules out an LLM or "chatbot" feature.
  - No reselling or sublicensing, such as a public Reelio API.
  - No excessive bandwidth. The soft limit is about 40 requests per second; respect HTTP 429.
  - No misleading presentation of where data comes from, and no implied endorsement by TMDB.
  - TMDB may terminate the licence "at any time for any reason".
- **Combining with OMDb ratings:** the terms say nothing about it. Showing OMDb External ratings next to TMDB metadata appears to be allowed if each one is clearly labelled with its Rating source. Avoid a blended "Reelio score" built from TMDB data, because it risks the derivatives clause.

## Sources and how much weight they carry

| Source | Status | Notes |
|---|---|---|
| [TMDB API Terms of Use](https://www.themoviedb.org/api-terms-of-use), last updated 2023-10-20 | **Binding contract.** §10.H: "These terms and conditions constitute the entire agreement". | TMDB may change them at any time by posting. Continued use counts as acceptance (§10.H). |
| [TMDB Terms of Use](https://www.themoviedb.org/terms-of-use) (site), last updated 2024-05-10 | **Incorporated.** API Terms §1.B makes the API licence "expressly conditioned on Your: Ongoing compliance with the TMDB Terms of Use". | It is unclear which of its clauses reach API consumers (see §4.2). |
| [Developer FAQ](https://developer.themoviedb.org/docs/faq) | Guidance | Its wording differs from the Terms in places. The Terms govern. |
| [Watch Providers reference](https://developer.themoviedb.org/reference/movie-watch-providers), updated 2025-10-01 | Guidance with an enforcement threat | This is where the JustWatch attribution requirement lives. |
| [Logos & Attribution](https://www.themoviedb.org/about/logos-attribution), [Image basics](https://developer.themoviedb.org/docs/image-basics), [Rate limiting](https://developer.themoviedb.org/docs/rate-limiting), [API for Business](https://www.themoviedb.org/api-for-business) | Guidance | |
| [JustWatch API docs](https://apis.justwatch.com/docs/api) | JustWatch's **own Partner API contract terms** | These are not binding on TMDB-sourced data. Treated here as a model only. |
| TMDB support-forum replies by staff (Travis Bell, 2018–2020) | Informal background | They quote the **pre-2023** terms ("reasonable periods"), which the 6-month cap replaced. **Not relied on for permission.** |

## 1. Non-commercial vs commercial use

### 1.1 What the terms say

- The free licence excludes commercial use entirely: "The license in Paragraph 1.A above does not permit any commercial use of TMDB, the TMDB APIs, or TMDB Content." ([API Terms §2.A](https://www.themoviedb.org/api-terms-of-use))
- Definition: "Selling, leasing, or sublicensing the TMDB APIs, access to the TMDB APIs, or TMDB Content, or deriving revenues from the use or provision of TMDB, the TMDB APIs, or TMDB Content, for commercial or monetary gain, **directly or indirectly**, is … considered a commercial use and is only permitted under a separate written agreement between You and TMDB." (§2.A)
- TMDB decides, and can change its mind: "TMDB reserves the right to, in its sole discretion, determine whether Your use is commercial at the time that you apply for the license … TMDB reserves the right to monitor Your use or Your Application to make or revise its determination." (§2.A)
- The terms list these as commercial uses, "by no means exhaustive" (§2.A):
  - charging users a fee for an Application that integrates TMDB;
  - selling an Application that brings in TMDB Content;
  - "Using TMDB, the TMDB APIs, or TMDB Content on or in connection with a **'destination' website**, search engine, or interactive query-response system (including … LLM … or chatbots) … or for **driving traffic or generating revenue for a website** … (including from advertising displayed on or by the website…)";
  - "Operating a website that generates revenue through charging users for access to content, or through **recommend content, such as movies**…";
  - training or validating ML/AI systems on TMDB content.
- The restrictions list says the same thing again: you must not "derive revenues from the use or provision of TMDB … whether for direct commercial or monetary gain or otherwise, except as expressly permitted in a written agreement" (§1.C).
- Commercial use without an agreement "constitutes a material breach" (§2, "Commercial Use Requires as Commercial API Key").
- The FAQ gives a simpler test: "Your project is considered commercial if the primary purpose is to create revenue for the benefit of the owner." It also says: "Our API is free to use for non-commercial purposes as long as you attribute TMDB as the source of the data and/or images." ([FAQ](https://developer.themoviedb.org/docs/faq))

### 1.2 What a commercial licence involves

- It is a separate written agreement with TMDB, which "may be subject to, among other things, payment of fees" ([API Terms §2](https://www.themoviedb.org/api-terms-of-use)).
- To get one, email `sales@themoviedb.org` and include your country ([FAQ](https://developer.themoviedb.org/docs/faq)), or use the contact form on [API for Business](https://www.themoviedb.org/api-for-business). The form asks for name, email, location, company name, company URL, and what you want to do with the API.
- **Pricing, tiers, SLA and contract terms are not published anywhere I could find.** The FAQ says: "We do not currently provide an SLA". It does not say whether a commercial agreement includes one.

### 1.3 Reading for Reelio

- **Free beta, no revenue of any kind:** this fits the free licence on both the Terms' revenue test and the FAQ's "primary purpose" test.
- **"May later be monetized":** §2.A covers "intended use". When applying for the key, describe the use honestly; don't present a planned commercial product as a hobby. Concealing the Application's identity is separately prohibited (§1.C).
- **Things that would trigger a licence:**
  - display ads, in any form;
  - a paid tier, even if the TMDB-backed features stay free (TMDB Content would still be "in connection with" a revenue-generating site);
  - **affiliate or referral links on Availability / Streaming service links**, which are revenue derived "indirectly";
  - sponsorships;
  - selling data;
  - any AI/LLM feature.
- **Ambiguous even with no revenue** (the main grey area): §2.A lists "a 'destination' website" and "driving traffic … for a website" as commercial examples. The term "destination website" is not defined. Read literally, a public, SEO-indexed site with a page for every movie could be called a destination website, even with no revenue. In context these examples sit under a heading about deriving revenue, which supports a narrower reading, but the text does not settle it. See also §4.2 on the site Terms' "index" and "business, whether or not for profit" clauses.

**Recommendation:** before the public launch, email TMDB with an accurate description:
- free;
- no ads;
- account-gated watchlists;
- public, indexable movie pages with a sitemap;
- OMDb External ratings shown alongside TMDB data;
- JustWatch-sourced Availability with links out to Streaming services;
- possible future monetization.

Ask for written confirmation that this is non-commercial, and ask what a commercial licence would cost when the time comes. Treat a "commercial" answer as a launch blocker.

## 2. Attribution

### 2.1 TMDB

**Binding text** ([API Terms §3](https://www.themoviedb.org/api-terms-of-use)):

> You must use the TMDB logo to identify Your use of TMDB, the TMDB APIs, or TMDB Content. Any use of any TMDB logos in Your Application must be less prominent than the logos or marks that primarily describe or identify Your Application and must make it clear that use of any TMDB logos does not imply any endorsement, certification, or other approval by TMDB. In addition, You must place the following notice prominently in or on Your Application:
> "This [website, program, service, application, product] uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB."

The licence itself is "expressly conditioned on … Giving TMDB attribution for all TMDB Content" (§1.B), so failing to attribute ends the licence.

**FAQ guidance, which differs from the Terms in two places** ([FAQ](https://developer.themoviedb.org/docs/faq)):
- **Wording:** the FAQ gives a shorter notice: "This product uses the TMDB API but is not endorsed or certified by TMDB." *Conflict:* use the Terms' wording, because the Terms are the contract.
- **Placement:** the FAQ says "the attribution must be within your application's 'About' or 'Credits' type section". The Terms say "prominently in or on Your Application". *Conflict:* these aren't mutually exclusive, so do both.
- **Logos:** "we require you to use one of our approved logos". The logo "should not be modified in color, aspect ratio, flipped or rotated". It "can be white, black or any of the approved colors".
- **Name:** refer to TMDB only as "TMDB" or "The Movie Database". "Any other name is not acceptable."
- **Link:** link back to `https://www.themoviedb.org`.
- **Approved logos and colours:** these are on [Logos & Attribution](https://www.themoviedb.org/about/logos-attribution). There are five SVG variants (primary full, short and long; alt long and short). The colours are `#0d253f`, `#01b4e4` and `#90cea1`. The page says every application "is required to properly attribute TMDB as the source", and gives no per-page or per-item placement rule.

**Implications for Reelio:**

1. A **site-wide footer** shows an approved TMDB SVG logo (unmodified) and the Terms-version notice ("This website uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB."). The logo links to https://www.themoviedb.org.
2. The same notice and logo go on an **About/Credits page**.
3. The TMDB logo must be visibly smaller and less prominent than the Reelio wordmark. Brand work has to respect this.
4. Copy never says "official", "partner", "powered by TMDB" in a way that implies endorsement, or anything else that implies endorsement.
5. When TMDB's own score is shown as an External rating, its Rating source label is "TMDB".

### 2.2 Availability (JustWatch via TMDB)

**Binding requirement**, from the [Watch Providers reference](https://developer.themoviedb.org/reference/movie-watch-providers):

> Powered by our partnership with JustWatch … **JustWatch Attribution Required.** In order to use this data you must attribute the source of the data as **JustWatch**. If we find any usage not complying with these terms we will revoke access to the API.

The same page says the endpoint "is *not* going to return full deep links, but rather, it's just enough information to display what's available where. You can link to the provided TMDB URL to help support TMDB and provide the actual deep links to the content." The response has a per-country `link` field (e.g. `https://www.themoviedb.org/movie/550-fight-club/watch?locale=AE`) and, per Streaming service, `provider_name`, `logo_path` and `display_priority`. It has **no** JustWatch URL and no Streaming service deep link.

**What the sources don't say:**
- TMDB does not specify the wording, logo, placement or linking for the JustWatch credit.
- I found **no JustWatch-published requirement for data obtained through TMDB**.
- JustWatch's [API docs](https://apis.justwatch.com/docs/api) cover its own **Partner API**, which needs a contract and a partner token. Those docs require a "Branded Link … next to every widget / 'where to watch' integration". The link must lead to the country-specific JustWatch title page, with the anchor text "JustWatch" or a logo `img` whose alt text contains "JustWatch" (gold logo preferred). These rules bind contracted JustWatch partners, not TMDB API users. They are useful only as a model of what JustWatch considers good attribution.
- Nothing I found grants permission to use the JustWatch logo for TMDB-sourced data.

**Implications for Reelio:**

1. Every Availability block (on movie pages, and anywhere else Availability is shown) carries a visible text credit, e.g. "Availability data by JustWatch". Don't use the JustWatch logo unless JustWatch permits it.
2. Out-links: in practice, the **TMDB `link` is the only link the API provides** for a movie's Availability. The PRD's "links to Streaming services" can't be deep links from this endpoint. Options are:
   - link to the TMDB watch page (TMDB's suggestion);
   - link to the Streaming service's home or search page, built by Reelio;
   - link to JustWatch's title page, but that URL isn't in the response.

   This is a product decision for the Availability ticket.
3. Streaming service logos come from TMDB's image CDN (`logo_path`). They are TMDB Content, so the 6-month and hotlinking rules below apply. The Streaming services' own trademark rules are outside TMDB's terms; showing their logos only to identify them is the normal practice, but the terms are silent on it.

## 3. Caching, storage and images

### 3.1 The 6-month cap

**Binding text** ([API Terms §1.C](https://www.themoviedb.org/api-terms-of-use)): "You must not: … Cache, for longer than 6 months, any information obtained through or from TMDB or the TMDB APIs."

- **Scope:** "any information". Metadata, images and Availability are treated the same; there is no per-kind limit.
- **Caching vs storing:** the Terms don't distinguish them, so the safe reading is that a persisted `movies` row built from TMDB data is a cache and falls under the cap.
- **Re-fetching:** the Terms don't say whether re-fetching restarts the 6 months. The natural reading is that a refreshed record is information newly "obtained", so a rolling refresh complies. This is an interpretation, not stated text.
- **TMDB IDs in user data:** whether a bare TMDB ID in a user's watchlist row counts as cached "information" is **ambiguous**. If the parent Movie record is refreshed or purged on schedule, the question rarely matters in practice.
- **Older staff replies:** in 2018–2020, staff said "Yes, you can cache data on your end … The crux of this point is more around the idea that you don't own the data" ([forum, 2018](https://www.themoviedb.org/talk/5ba32c77925141523700e0c9)). Those replies interpret the *older* "reasonable periods" clause and are superseded by the explicit 6-month cap.

**On termination** (§1.D): "You must immediately cease all use of the TMDB APIs, TMDB Content, and any TMDB API key(s), and you must promptly delete or otherwise purge all TMDB Content, including any cached content." TMDB "may terminate the license at any time for any reason", and termination is automatic if TMDB decides you are in breach.

**Implications for Reelio:**

1. Every TMDB-derived record (Movie metadata, image paths, Availability) carries a `fetched_at` / `refreshed_at` timestamp.
2. A scheduled job refreshes or purges anything approaching 6 months old. The 6 months is a **legal ceiling**; the product TTLs, which will be much shorter for Availability (data that changes daily), are a separate open item on the map ("Cache lifetimes and metadata refresh").
3. A movie TMDB has deleted, which can no longer be refreshed, must be purged or hidden before it hits the cap. The user's watchlist entry then needs a defined degraded state.
4. Keep Catalog source data **separate and purgeable** from user-owned data (accounts, watchlist and watched state), so that a termination purge doesn't destroy user data. This also supports the PRD's Catalog source abstraction.

### 3.2 Images: CDN vs rehosting

- **Documented path:** image URLs are built from `base_url` + size + `file_path`, with sizes taken from `/configuration`. Example: `https://image.tmdb.org/t/p/w500/1E5baAaEse26fej7uHcjOgEE2t2.jpg` ([Image basics](https://developer.themoviedb.org/docs/image-basics)). Serving from TMDB's CDN is the intended use.
- **Restriction** (§1.C): "Use TMDB as an image hosting service for banner advertisements, graphics, etc." This targets hotlinking TMDB for unrelated graphics; it doesn't forbid showing a movie's own artwork.
- **Rehosting:** the Terms don't ban it explicitly. But:
  - a rehosted copy is "information obtained … from TMDB", so the 6-month cap applies;
  - resizing, cropping or compositing (for example, generated Open Graph cards) risks "Make derivatives of the TMDB APIs or TMDB Content" (§1.C). The site Terms also ban derivative works "even if You intend to provide or distribute the derivative works … free of charge".
- **Ownership:** "We do not claim ownership of any of the images or data in the API. We comply with the Digital Millennium Copyright Act (DMCA)" ([FAQ](https://developer.themoviedb.org/docs/faq)). The artwork belongs to third parties, mostly studios. If Reelio rehosts it, Reelio becomes the host that receives takedown notices.
- **Staff comment (2018):** a staff reply said "you're free to have a local cache" of images ([forum, 2018](https://www.themoviedb.org/talk/5accf23b0e0a267f9a00fc6d)). This predates the current terms; it is background only.
- **CDN cache headers:** the image CDN currently sends `cache-control: public, max-age=31919000` (about a year), observed 2026-11-04. That governs browser caching of TMDB's own responses. It is **not** permission for Reelio to store images that long.

**Implication:** hotlink `image.tmdb.org` at the sizes `/configuration` publishes. Don't rehost or transform images. For Open Graph images (PRD §11.5), point `og:image` at the TMDB poster or backdrop URL, not at a generated composite. If performance ever requires a proxy, treat it as a cache that expires within 6 months, does no transformation, and can be purged.

## 4. Other restrictions that affect the design

### 4.1 Combining TMDB data with other sources (OMDb)

- The API Terms are **silent** on combining TMDB Content with other data. There is no exclusivity clause.
- Two clauses are relevant:
  - "Make derivatives of the TMDB APIs or TMDB Content" is prohibited (§1.C); "derivatives" is undefined;
  - you must not use the APIs "in a manner that is confusing or misleading as to the source or origin of Your Application" (§1.C).
- **Implications:**
  1. Show OMDb-sourced External ratings (IMDb, Rotten Tomatoes, Metacritic) beside TMDB metadata, each labelled with its own Rating source. That appears to be allowed, since presenting the data side by side doesn't modify TMDB Content.
  2. Don't compute a blended or "Reelio" score from the TMDB rating.
  3. Don't overwrite TMDB fields with OMDb values in a way that hides where each value came from. Store the source next to each External rating, as PRD §7.4 already requires.
  4. OMDb's own terms are covered by [issue 03](../issues/03-omdb-ratings.md).

### 4.2 SEO and indexing of TMDB-derived pages

- The API Terms **don't mention SEO, crawling, sitemaps or indexing.**
- Two clauses still bear on public indexable pages:
  - §2.A's commercial examples include "a 'destination' website, search engine" and "driving traffic … for a website" (see §1.3);
  - the [site Terms of Use §3.B](https://www.themoviedb.org/terms-of-use), which the API licence incorporates through §1.B, say you "must not create, recreate, distribute or advertise an index of other Users' Contributions or the Materials", must not "build a business, whether or not for profit", and must not create derivative works "even if … free of charge". Each of these applies "unless expressly authorized by Us in writing (please see, for example, Sections 2.A and 2.B of our API Terms of Use…)".
- **Ambiguity:** the site Terms seem aimed at site users rather than API licensees. Their "unless authorized in writing" carve-out points to the API Terms, which suggests the API Terms are that authorization. Still, §1.B conditions the API licence on "ongoing compliance" with the site Terms, and nothing excludes API use. A sitemap listing every TMDB-derived movie page is the closest Reelio comes to "an index".
- **Implication:** include public indexable pages and the sitemap explicitly in the written classification request to TMDB (§1.3). Until TMDB confirms, a conservative option is to defer the sitemap, or limit it to pages users have actually reached, and keep `noindex` as a quick switch. Private pages are `noindex` regardless, per PRD §11.5.

### 4.3 AI / machine learning

- TMDB reserves "the right … to use the TMDB APIs or TMDB Content in connection with, or for training, a machine learning or artificial intelligence based Application" (§1.A).
- Licensees must not "Use the TMDB APIs or TMDB Content in connection with, including for training, a machine learning (ML) or artificial intelligence (AI) based Application" (§1.C).
- **Implication:** no LLM-powered recommendations, chat, summaries or "why watch" features over TMDB data without a licence. Classic, non-ML logic is fine: Reelio's random picker, filters, and TMDB's own `/recommendations` endpoint. It's unclear whether a home-grown ML recommender counts; assume it does. Record this as a constraint on the P1/P2 follow-ups.

### 4.4 Operational restrictions

- **Bandwidth:** you must not use "an excessive amount of bandwidth" or degrade TMDB, judged "in Our sole discretion" (§1.C). The rate-limit doc says limits "sit somewhere in the 40 requests per second range. This limit could change at any time so be respectful … and respect the 429" ([Rate limiting](https://developer.themoviedb.org/docs/rate-limiting)). It also mentions upper limits "to help mitigate needlessly high bulk scraping". **Implications:**
  - fetch on demand and cache, rather than bulk-crawling `/discover` to mirror the catalog;
  - throttle outbound calls and back off on 429.
- **No reselling or sublicensing** of TMDB Content or API access (§1.C). **Implication:** don't expose a public Reelio API that re-serves TMDB data.
- **Identity:** you must not cloak the Application's identity when using or applying for the API (§1.C). **Implications:** register the key with Reelio's real name and URL, and update the registration if the working name changes.
- **API key in the browser:** the terms don't mention it. Keeping it server-side, as PRD §7.4 already requires, follows from good practice rather than any clause.
- **Prohibited content:** no use with libellous, obscene or otherwise offensive material, or anything illegal (§1.C; [FAQ](https://developer.themoviedb.org/docs/faq)).
- **No SLA; TMDB may change, limit or discontinue the API without notice** (§5; [FAQ](https://developer.themoviedb.org/docs/faq)). **Implication:** graceful degradation and the Catalog source abstraction (PRD §16) are necessary, not optional.
- **The terms can change at any time** by posting, and continued use counts as acceptance (§10.H). **Implication:** someone should re-check the terms page, last updated 2023-10-20, before launch and periodically after.
- **Governing law:** California, with venue in Santa Clara County (§10.C).

## 5. Open questions for TMDB (to put in the pre-launch email)

1. Is Reelio as described (free, no ads, public indexable movie pages and a sitemap, OMDb ratings shown alongside, JustWatch Availability) non-commercial under §2.A? In particular, is it a "destination website"?
2. Does the site Terms' "index" clause restrict a sitemap of TMDB-derived pages for an API licensee?
3. Does refreshing a stored record restart the 6-month cap? May a bare TMDB ID be kept in user data longer than that?
4. What form should the JustWatch credit take? Is there an approved JustWatch logo for TMDB API users?
5. What would a commercial licence cost if Reelio later adds ads, a premium tier or affiliate links?
