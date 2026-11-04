# OMDb ratings: coverage, terms and limits

Research for [issue 03](../issues/03-omdb-ratings.md). Sources were read on 2026-11-04. This is a reading of published terms and first-party statements, not legal advice.

## Answer

- **TMDB does not provide IMDb, Rotten Tomatoes or Metacritic ratings.** Movie details returns only TMDB's own `vote_average` and `vote_count`, plus an `imdb_id` string. `external_ids` returns identifiers only. The IMDb id (`tt…`) is the right join key for OMDb's `i=` parameter, and it comes with movie details, so no extra call is needed. Treat it as nullable.
- **OMDb fails the PRD's licensing test (FR-023: "authorized source under appropriate licensing terms"; "No ratings may be scraped").** There are three independent reasons.
  1. **OMDb's own terms don't allow Reelio's use.** The terms apply to the API: `swagger.json` sets `termsOfService` to `legal.htm`. They allow use "only for your personal use", ban building "a business utilizing the Contributions, whether or not for profit", and ban archiving, displaying or redistributing data beyond personal, non-commercial use. A public beta is not personal use, even if it is free.
  2. **OMDb has no rights in these scores to grant.** OMDb's operator says Rotten Tomatoes data was removed "to comply with a legal request from Fandango". He says he "cannot use/store the Rotten Tomatoes information", that he has had regular contact with Amazon's (IMDb) and Fandango's legal teams, and that he now acts "merely as a conduit" relaying third-party data under the DMCA §512(a) safe harbour. That position protects OMDb as a conduit, not Reelio as the site that displays the scores. OMDb's CC BY-NC 4.0 footer cannot help either: the licence covers only rights "the Licensor has authority to license", and trademarks are excluded.
  3. **The Rating sources' own terms forbid this kind of reuse without permission.** IMDb, Rotten Tomatoes (Fandango) and Metacritic (via its licensing partner's contract) all restrict their content to personal, non-commercial use or require a written licence. Each offers a licensing route.
- **OMDb tiers:** free at 1,000 requests a day. Patreon tiers are $1 a month (100,000 a day), $5 a month (500,000 a day plus the Poster API) and $10 a month ("Private server with no limits"). No tier mentions a commercial licence or any change to the terms. Paying OMDb changes the quota, not the rights.
- **Coverage and freshness are poor for exactly the films Reelio cares about.** In 2018 the operator said entries refresh "every 30 days", with a release-date override. User reports from 2018 to 2023 show `"N/A"` and an empty `Ratings: []` for new releases weeks after release, and IMDb vote counts lagging badly. The operator also says his data comes from sources "none of which are IMDb", so an "IMDb rating" from OMDb may not match IMDb.
- **"Keep only IMDb" isn't a cheap way out.** IMDb's free datasets are licensed for personal, non-commercial use only. They may not be republished to create "any kind of online/offline database of movie information (except for individual personal use)". The only clearly authorized IMDb route is IMDb's paid API on AWS Data Exchange, and its pricing is not published.
- **Licensed alternatives exist, but each costs money or rules out ads:**
  - **IMDb:** AWS Data Exchange. Ratings are included; the price comes from a quote.
  - **Rotten Tomatoes:** Fandango's Business Proposal form, or Fabric Origin. There is an "Affiliate Distribution Fee", a daily refresh is required, the logo display must be approved before launch, and no ad units may be sold against the data.
  - **Metacritic:** Fabric Origin, under a Fandom partnership. The licence fee is waived, but there may be no advertising against the content without Fandom's approval, and the content must be stored on U.S. servers.
  - **MDBList** publishes no terms or data licence, so it fails the same test as OMDb.
- **Recommendation: defer IMDb, Rotten Tomatoes and Metacritic External ratings from the MVP. Do not use OMDb.** Ship FR-022 (TMDB's rating, labelled as TMDB) as the only External rating. Design the Rating source boundary so other Rating sources can be added later. If the human wants these ratings at launch, the blocker is a business decision: request quotes from IMDb (AWS Data Exchange) and Fabric Origin or Fandango. Note that their no-advertising clauses conflict with the "may later be monetized" plan, as TMDB's own terms already do.

## Sources and how much weight they carry

| Source | Status | Notes |
|---|---|---|
| [OMDb Terms of Use](https://www.omdbapi.com/legal.htm), last updated 2015-03-12 | **Binding on API users.** [`swagger.json`](https://www.omdbapi.com/swagger.json) `info.termsOfService` points to it. | Written for a user-contribution site, but it says it applies to the "Site and Services" equally. |
| [OMDb home and docs](https://www.omdbapi.com/), [API key page](https://www.omdbapi.com/apikey.aspx), [`swagger.json`](https://www.omdbapi.com/swagger.json) | First-party documentation | The footer and swagger both say "CC BY-NC 4.0". No response schema is published. |
| [OMDb Patreon tiers](https://www.patreon.com/omdb) | First-party pricing | Read from the page's embedded tier data. Prices are USD per month (`"pay_per_name":"month"`). |
| [OMDb "Rating Changes" Patreon post](https://www.patreon.com/posts/rating-changes-8417367), 2017-03-13 (read via [Wayback snapshot 2024-01-17](http://web.archive.org/web/20240117145714/https://www.patreon.com/posts/rating-changes-8417367)) | First-party statement by the operator | Patreon returns 403 to direct fetches. |
| [OMDb GitHub issue tracker](https://github.com/omdbapi/OMDb-API/issues), comments by the `omdbapi` account | First-party statements by the operator | The tracker is linked from OMDb's own changelog. Comments by other users are anecdotal evidence only. |
| [TMDB movie details](https://developer.themoviedb.org/reference/movie-details), [external IDs](https://developer.themoviedb.org/reference/movie-external-ids) | First-party API reference | |
| [IMDb Conditions of Use](https://www.imdb.com/conditions) | Binding on users of IMDb services | No date is shown. |
| [IMDb Non-Commercial Datasets](https://data.imdb.com/non-commercial-datasets/), [Can I use IMDb data in my software?](https://help.imdb.com/article/imdb/general-information/can-i-use-imdb-data-in-my-software/G5JTRESSHJBBHTGX) | First-party licence terms for the datasets | |
| [IMDb API: getting access](https://data.imdb.com/documentation/api-documentation/getting-access/), [AWS Marketplace trial listing](https://aws.amazon.com/marketplace/pp/prodview-3n67c76ppu2yy) | First-party product information | No prices are published. |
| [Rotten Tomatoes Terms of Use](https://www.rottentomatoes.com/policies/terms-of-use), last updated 2026-01-06 | Binding on users of RT services | |
| [Rotten Tomatoes Licensing](https://www.rottentomatoes.com/help_desk/licensing) | First-party licensing and brand guidance | |
| [Fabric Origin: Rotten Tomatoes Data Services terms](https://www.fabricdata.com/developer/standard-terms-of-service-for-use-rotten-tomatoes-data-services), [Metacritic terms](https://www.fabricdata.com/developer/metacritic-tos) | Licensed reseller's standard contracts. They state the partnerships with Fandango and Fandom. | Quotes below were checked against the raw page text. |
| Metacritic's own terms: the footer links to [Fandom Terms of Service](https://www.fandom.com/terms-of-service-pp1) | **Not retrieved** (HTTP 403/402 to automated fetches) | See [What could not be established](#what-could-not-be-established). |
| [CC BY-NC 4.0 legal code](https://creativecommons.org/licenses/by-nc/4.0/legalcode.en) | Licence text | |
| [MDBList API docs](https://docs.mdblist.com/docs/api), [mdblist.com](https://mdblist.com/) | First-party | No terms or licence statement found. |
| [Wikidata:Licensing](https://www.wikidata.org/wiki/Wikidata:Licensing), [P444 review score](https://www.wikidata.org/wiki/Property:P444) | First-party | |

## 1. Does TMDB provide these ratings? The join key

- **Movie details** (`GET /3/movie/{movie_id}`) returns `imdb_id` (string), `vote_average` (number) and `vote_count` (integer). It has no IMDb, Rotten Tomatoes or Metacritic score field. It supports `append_to_response` ([reference](https://developer.themoviedb.org/reference/movie-details)).
- **External IDs** (`GET /3/movie/{movie_id}/external_ids`) returns `id`, `imdb_id`, `wikidata_id`, `facebook_id`, `instagram_id` and `twitter_id`. These are identifiers only; there are no ratings ([reference](https://developer.themoviedb.org/reference/movie-external-ids)).
- **OMDb** looks titles up by `i`: "A valid IMDb ID (e.g. tt1285016)". Since 2019-04-08 it supports eight-digit IMDb ids ([OMDb docs and changelog](https://www.omdbapi.com/)).
- **Conclusion:** TMDB's `imdb_id` is the correct join key, and it already comes with movie details, so a separate `external_ids` call isn't needed. Neither TMDB endpoint promises the field will be filled, and TMDB data is community-edited. A Movie without an `imdb_id` simply has no IMDb-keyed External ratings. Never fall back to OMDb's title search (`t=` or `s=`): a title match can attach the wrong film's scores.

This answer holds whatever Rating source is chosen. IMDb's own datasets and API also use `tconst`, the same `tt…` id ([IMDb datasets](https://data.imdb.com/non-commercial-datasets/)).

## 2. OMDb's terms, tiers and limits

### 2.1 Keys and daily limits

- The API key form offers two account types: "Patreon" and "FREE! (1,000 daily limit)" ([apikey.aspx](https://www.omdbapi.com/apikey.aspx)). Calls without a key return `{"Response":"False","Error":"No API key provided."}` (observed directly on 2026-11-04).
- OMDb's [Patreon tiers](https://www.patreon.com/omdb), in USD per month:

  | Tier | Price | Stated benefits (verbatim) |
  |---|---|---|
  | Free | $0 | — |
  | Basic | $1 | "Basic API Access", "100,000 requests per day" |
  | Standard | $5 | "Access to the Poster API", "500,000 requests per day" |
  | Pro | $10 | "Private server with no limits", "Also get access to the Poster API" |
  | Sponsor | $25 (25 slots) | "Get a credit/link on the homepage", "And everything else!" |

- **No tier mentions commercial use, a licence, an SLA or any change to the Terms of Use.** The Patreon page's own description says: "The 'Open' Movie Database is now private!? Currently, yes. … I plan on making the API 100% FREE once the traffic gets under control."
- **SLA:** none. The Terms say: "WE DO NOT WARRANT THAT THE SITE OR SERVICES WILL BE AVAILABLE FOR USE", and liability is capped at "$50" (§14, §15). OMDb is a one-person project ("API by Brian Fritz").

### 2.2 Licence: two documents that conflict

| Document | What it says | Effect on Reelio |
|---|---|---|
| Site footer and `swagger.json` `info.license` | "All content licensed under CC BY-NC 4.0" | CC BY-NC allows sharing for purposes "not primarily intended for or directed towards commercial advantage or monetary compensation" ([CC §1(i)](https://creativecommons.org/licenses/by-nc/4.0/legalcode.en)), with attribution. A free beta might qualify, but a future monetized Reelio would not. |
| [Terms of Use](https://www.omdbapi.com/legal.htm), made the API's `termsOfService` in `swagger.json` | §10: "The Site is made available to you only for your personal use, and you may not use the Site or any Contributions or Materials in connection with any commercial endeavors."<br>§4.1: licence "solely for personal, non-commercial purposes".<br>§4.2.3: no "copy, download, reproduce, duplicate, archive, distribute, upload, publish, … display … or retransmit Contributions except as permitted in Section 4.1".<br>§4.2.4: no creating or distributing "an index of any Contributions unless authorized by us in writing".<br>§4.2.5: "You may not build a business utilizing the Contributions, whether or not for profit." | A public website showing the data to other people is not "personal use". §4.2.5 rules out the "it's free" argument. |

**Ambiguity, stated rather than resolved:** the CC licence and the Terms of Use conflict, and OMDb has never reconciled them. A lawyer might read the CC licence as the more specific grant for the data. But it would still not cover IMDb, Rotten Tomatoes or Metacritic scores, because a CC licence grants only rights "that the Licensor has authority to license", and "Patent and trademark rights are not licensed" ([CC §1(g), §2(b)(2)](https://creativecommons.org/licenses/by-nc/4.0/legalcode.en)). OMDb doesn't own these scores (§3), so neither document can authorize them.

### 2.3 Attribution

- **CC BY-NC** requires crediting the licensor, keeping notices, linking to the licence and indicating changes ([CC §3(a)](https://creativecommons.org/licenses/by-nc/4.0/legalcode.en)).
- **The Terms** require keeping "all trademark, copyright and other proprietary notices" (§4.2.1).
- **OMDb's own credit line** reads "API by Brian Fritz … This site is not endorsed by or affiliated with IMDb.com."
- No specific attribution format is published for API consumers.

### 2.4 Caching and storage

- OMDb publishes no caching rule. The Terms ban "archive" and "duplicate" outside personal, non-commercial use (§4.2.3), and ban creating "an index" without written permission (§4.2.4). Read strictly, any persisted copy in a public product is outside the licence.
- **Rate limit versus caching:** at 1,000 calls a day on the free key, an uncached call per Movie detail view would run out almost immediately. Any OMDb design would need caching that the Terms don't clearly permit. A paid tier raises the quota but changes no terms.

## 3. Is anyone authorized? OMDb and the three Rating sources

### 3.1 OMDb's own account of where the ratings come from

- **2017-03-13**, Patreon post "Rating Changes": "Here's why the Rotten Tomatoes ratings are returning 'N/A': After becoming all too familiar with Amazon (who owns IMDb) and Fandango's legal team on a regular basis, I've been looking into other options for returning movie ratings. With this proposed change, I would act merely as a conduit and relay an unmodified response from other service providers which is protected under the 512(a) 'safe harbor' of the DMCA." ([post](https://www.patreon.com/posts/rating-changes-8417367), [snapshot](http://web.archive.org/web/20240117145714/https://www.patreon.com/posts/rating-changes-8417367))
- **2017-08-02**, the operator on [issue #5](https://github.com/omdbapi/OMDb-API/issues/5): "The Rotten Tomatoes data was removed to comply with a legal request from Fandango (who owns Rotten Tomatoes). That's why the overall rating structure was changed to relay data from a 3rd party source which I do not control … I cannot use/store the Rotten Tomatoes information, but you might be able to! They still provide an API that you can request access to use".
- **2017-09-07**, same thread: "I no longer store ratings (due to legal issues) and merely relay them from a 3rd party of which I have a few sources for like Wikidata".
- **2018-08-16**, [issue #88](https://github.com/omdbapi/OMDb-API/issues/88): "I get data from a lot of different sources, none of which are IMDb."
- **OMDb's home page** says "all content and images on the site are contributed and maintained by our users". This is hard to square with the statements above.

**Reading:** by its operator's own account, OMDb has no licence from IMDb, Fandango or Metacritic's owner. It relies on a conduit/safe-harbour theory after legal pressure from two of the three. Section 512(a) is a defence available to the service provider acting as a conduit. It does not authorize a downstream site like Reelio to publish the data. Under the PRD (FR-023), OMDb is therefore **not an authorized Rating source** for any of the three scores.

### 3.2 IMDb

- **[Conditions of Use](https://www.imdb.com/conditions):** the licence is "to access and make personal and non-commercial use of the IMDb Services". "The IMDb Services or any portion of such services may not be reproduced, duplicated, copied, sold, resold, visited, or otherwise exploited for any commercial purpose without express written consent of IMDb." "You may not use data mining, robots, screen scraping, or similar data gathering and extraction tools on this site". For "non-personal (including commercial) use", contact IMDb's licensing department.
- **[Non-commercial datasets](https://data.imdb.com/non-commercial-datasets/)** (`title.ratings.tsv.gz` has `averageRating` and `numVotes`, refreshed daily) are "available … for personal and non-commercial use. You can hold local copies of this data". The [help article](https://help.imdb.com/article/imdb/general-information/can-i-use-imdb-data-in-my-software/G5JTRESSHJBBHTGX) adds conditions:
  - The data "must not be altered/republished/resold/repurposed to create any kind of online/offline database of movie information (except for individual personal use)".
  - It requires the credit "Information courtesy of IMDb (https://www.imdb.com). Used with permission."
  - "If the information/data you want is not present in our datasets, it means it's not available for non-commercial usage."
  - IMDb "reserves the right to withdraw permission … at any time".
- **Reading:** a public website republishing IMDb ratings to other people is very likely outside "individual personal use", even with no revenue. This is not settled, because IMDb doesn't define "personal". The licensed route is IMDb's API on AWS Data Exchange (§5).

### 3.3 Rotten Tomatoes (Fandango)

- **[Terms of Use](https://www.rottentomatoes.com/policies/terms-of-use)** (last updated 2026-01-06):
  - "Our Site and other Services are for your personal and non-commercial use only."
  - "Any other use of the Services, including reproduction, modification, distribution, or republication, any form of data extraction or data mining, or other commercial exploitation of any kind, without prior written permission of Fandango is strictly prohibited."
  - The Terms also bar "systematic retrieval of data … to create or compile, directly or indirectly, a collection, compilation, database or directory, without Fandango's prior written consent".
  - They require Fandango's prior written consent for deep links to pages other than the home page.
- **[Licensing page](https://www.rottentomatoes.com/help_desk/licensing):** "If you would like to use any of our trademarks/logos or integrate Rotten Tomatoes® data into your application (via APIs, data feeds) please submit a request with details of your proposed usage via our [Business Proposal Form](https://fandango.az1.qualtrics.com/jfe/form/SV_0ieuKEYpn4S7nnM)". It also says: "Brand attribution is required in every location where Rotten Tomatoes content is displayed", "Rotten Tomatoes scores and reviews must link back to the corresponding movie pages", and the Fresh, Rotten and Certified Fresh logo rules apply.
- **Reading:** Rotten Tomatoes scores require Fandango's written permission. Fandango has acted against OMDb specifically (§3.1). This is the highest-risk of the three.

### 3.4 Metacritic

- Metacritic's footer links its terms to [Fandom's Terms of Service](https://www.fandom.com/terms-of-service-pp1), which this research **could not retrieve** (HTTP 403/402).
- Indirect evidence: Fabric Origin's [Metacritic licence](https://www.fabricdata.com/developer/metacritic-tos) states that "FO has a partnership with Fandom, Inc. ('Fandom') who owns and operates Metacritic.com". It lists the "Metacritic metascore™" as licensed "Metacritic Content", describing it as "an integral component in the expression of the metacritic™ brand". Fandom licenses the Metascore as content; it does not treat it as free to reuse.
- OMDb's operator does not claim a Metacritic licence either (§3.1).
- **Reading:** treat Metacritic as unauthorized via OMDb. Confirm Fandom's terms by hand if this route is ever revisited.

### 3.5 Legal ambiguity worth naming

A bare number such as "83%" may not be protected by copyright in the US. But Reelio's exposure doesn't depend on that point:

- the Rating sources' terms are contract terms on access and use;
- the names and marks (Tomatometer®, metascore™, IMDb®) are trademarks, so source attribution itself touches brand rules;
- OMDb's own terms bind Reelio as an OMDb user;
- the PRD's bar is "authorized source under appropriate licensing terms", not "probably defensible".

The "facts aren't copyrightable" argument doesn't meet that bar, and this research does not rely on it.

## 4. Coverage, freshness and missing values

No OMDb key was obtained for this research, so coverage was **not measured**. The evidence is the operator's statements and user reports on OMDb's tracker:

- **Refresh policy (operator, 2018-05-10, [#60](https://github.com/omdbapi/OMDb-API/issues/60)):** "To mitigate the traffic to the sources I get data from movies are only refreshed every 30 days. if the currentDate >= releaseDate it will trump the 30-day rule and update the entry."
- **New releases are often empty:** user reports between 2018 and 2023 show `imdbRating: "N/A"`, `Metascore: "N/A"` and `Ratings: []` for released films:
  - Avengers: Infinity War, at release ([#60](https://github.com/omdbapi/OMDb-API/issues/60));
  - Enola Holmes 2 ([#280](https://github.com/omdbapi/OMDb-API/issues/280), 2022);
  - titles still "N/A" almost a month after release ([#305](https://github.com/omdbapi/OMDb-API/issues/305), 2023);
  - "The Mandalorian" with no rating the day after its premiere, and Joker's IMDb rating "missing 90,000 votes" weeks after release ([#158](https://github.com/omdbapi/OMDb-API/issues/158), 2019);
  - films with low vote counts returning "N/A" even though IMDb shows a rating ([#96](https://github.com/omdbapi/OMDb-API/issues/96)).

  The operator has not published a current refresh policy. These issues remain open.
- **Rotten Tomatoes freshness:** in 2017 a user reported that RT scores for new films were not updated after "around 10 days" ([#5](https://github.com/omdbapi/OMDb-API/issues/5)). The operator forced a manual update in reply.
- **Accuracy of the "IMDb" label:** because OMDb sources from places "none of which are IMDb" (§3.1), its `imdbRating` and `imdbVotes` can differ from what IMDb shows. Showing them labelled "IMDb" risks misattribution.
- **Missing-value representation.** OMDb publishes no response schema; `swagger.json` defines parameters only. The following is from first-party examples in the tracker and from a third-party, empirically derived OpenAPI spec ([PR #339](https://github.com/omdbapi/OMDb-API/issues/339), 2026-04-01, unofficial):
  - Every field is a string, including numbers ([#276](https://github.com/omdbapi/OMDb-API/issues/276)).
  - Scalar fields (`imdbRating`, `imdbVotes`, `Metascore`) use the string `"N/A"` when missing.
  - `Ratings` is an array of `{"Source","Value"}` objects. It is `[]` when no rating is available, and **partial** arrays are common, for example IMDb only ([#290](https://github.com/omdbapi/OMDb-API/issues/290)).
  - `Source` strings are `"Internet Movie Database"`, `"Rotten Tomatoes"` and `"Metacritic"`.
  - `Value` formats are `"7.7/10"`, `"83%"` and `"67/100"`; `imdbVotes` looks like `"416,247"` ([#102](https://github.com/omdbapi/OMDb-API/issues/102)).
  - Only the Rotten Tomatoes critics score (Tomatometer) is returned. There is no audience score ([#178](https://github.com/omdbapi/OMDb-API/issues/178)).
  - The legacy `tomatoes=true` fields (`tomatoMeter` and so on) return `"N/A"` ([#102](https://github.com/omdbapi/OMDb-API/issues/102)), and the parameter is no longer documented.
  - Errors come back as `{"Response":"False","Error":"…"}`, for example `"No API key provided."` (observed). A user also reports "Error: Daily Request Limit Reached!" ([#109](https://github.com/omdbapi/OMDb-API/issues/109)).

## 5. Licensed alternatives

| Rating source | Authorized route | Cost | Terms that matter for Reelio |
|---|---|---|---|
| IMDb | **IMDb API via AWS Data Exchange** ([getting access](https://data.imdb.com/documentation/api-documentation/getting-access/)): "exclusively available through AWS Data Exchange". Both API products list "IMDb Ratings". | Not published. There is a free trial listing ([AWS Marketplace](https://aws.amazon.com/marketplace/pp/prodview-3n67c76ppu2yy)); production pricing comes from a quote. "Subscription requests will be processed by IMDb staff within five business days." | Display and attribution terms are in the vendor EULA, which was not reviewed. |
| IMDb | Non-commercial datasets | Free | Personal, non-commercial use only. No republishing as "any kind of online/offline database". **Not suitable for a public site** (§3.2). |
| Rotten Tomatoes | **Fandango directly** ([Business Proposal Form](https://fandango.az1.qualtrics.com/jfe/form/SV_0ieuKEYpn4S7nnM)), or **Fabric Origin** as an authorized reseller ([RT Data Services terms](https://www.fabricdata.com/developer/standard-terms-of-service-for-use-rotten-tomatoes-data-services)) | Fabric: "Affiliate shall pay Company an Affiliate Distribution Fee … in an amount as set forth in the Deal Terms". The amount is not published. | Covers "Movie Tomatometer Score" and "Movie Audience Score". Requires:<br>• refresh "at least once every day" and "not archive or display" removed data;<br>• "prominently display the Rotten Tomatoes logos", with the display approved "prior to public launch";<br>• no use "to sell any type of ad unit to a third party". |
| Metacritic | **Fabric Origin** under its Fandom partnership ([Metacritic terms](https://www.fabricdata.com/developer/metacritic-tos)) | "Fandom agrees to waive any license fees for Your use of the Metacritic Content", subject to §4. Fabric's own API fee is not published (contact ask@fabricdata.com). | Without Fandom's approval you may not "use the Metacritic Content … to sell any advertising" or "deliver any advertising against the Metacritic Content". Content must be "stored on servers located within the U.S.", "shall not be permanently co-mingled" with other data, and must be purgeable. Required link text applies to critic quotes. |
| Several | **MDBList** ([API docs](https://docs.mdblist.com/docs/api)). The site shows IMDb, Letterboxd, Rotten Tomatoes, Metacritic, Trakt and TMDB ratings. | Free: 1,000 requests a day. Paid: €1–5 for 10,000–250,000 a day. | No terms of use or data licence is published. **Fails the authorization test for the same reason as OMDb.** |
| Rotten Tomatoes / Metacritic | **Wikidata** `P444` "review score", qualified by `P447` "review score by". This is the source OMDb's operator named. | Free. Structured data "is released into the public domain under Creative Commons Zero" ([Wikidata:Licensing](https://www.wikidata.org/wiki/Wikidata:Licensing)). | CC0 waives Wikidata's rights, not Fandango's or Fandom's, and it is not authorization from the Rating source. Data is entered by volunteers, so it is often missing or stale for new releases. **Legally ambiguous; does not meet the PRD bar.** |

## 6. Risk assessment

| Option | Licensing risk | Product risk | Verdict |
|---|---|---|---|
| OMDb for all three | **High.** Breaches OMDb's own terms (personal use, no business, no archiving). Relays data the operator says he has no rights to. Fandango and Amazon have already pressed OMDb. Reelio would be the visible publisher. | One-person service with no SLA and a $50 liability cap. Ratings often missing or stale for new releases. The IMDb figure may not match IMDb. | **Reject.** |
| OMDb for IMDb only | High: same OMDb terms breach, and IMDb's Conditions forbid commercial or non-personal reuse. | Same staleness; "IMDb" label accuracy issue. | **Reject.** |
| IMDb datasets for IMDb only | Medium to high: republishing on a public site falls outside "individual personal use" by IMDb's own wording. | Good: refreshed daily, full coverage. | Not authorized for Reelio as written. Ask IMDb in writing if wanted. |
| Licensed feeds (IMDb ADX, Fabric or Fandango, Fabric for Metacritic) | Low once contracted | Cost unknown. Fandango and Fabric must approve the display. RT needs a daily refresh. No ads against RT or Metacritic content. Metacritic needs U.S. storage. | The only compliant way to ship these ratings. A business decision. |
| Defer; TMDB rating only (FR-022) | Low: already covered by the TMDB licence ([TMDB terms research](https://github.com/rfookune/reelio/blob/research/tmdb-terms/.scratch/reelio-mvp/research/tmdb-terms.md)) | Less information on Movie pages than the scope change wanted. | **Recommended for the MVP.** |

## 7. Design implications

These apply whenever extra Rating sources are added, whether licensed feeds or a reversed decision.

- **Join key:** store TMDB's `imdb_id` on the Movie, from movie details, as a nullable unique string. It keys IMDb, OMDb and many licensed feeds. Fabric and Fandango feeds may use their own ids, so plan for one external-id mapping per Rating source rather than assuming `imdb_id` everywhere. No `imdb_id` means no lookup; never fall back to a title search.
- **External rating shape:** one row per (Movie, Rating source), with:
  - the value normalised to a number;
  - the scale (`10`, `100`, or percent);
  - an optional vote or review count;
  - the source's own display string where its brand rules require it;
  - a link back to the source page (required by Rotten Tomatoes and Metacritic);
  - `fetched_at`.

  Map source strings such as OMDb's `"Internet Movie Database"` to Glossary Rating sources (IMDb, Rotten Tomatoes, Metacritic, TMDB) at the boundary.
- **Missing values:** represent "no External rating from this Rating source" as **no row**, never as a stored `"N/A"` or zero. The UI shows nothing for that source; it should not imply a low score. Partial results are normal (IMDb present, Rotten Tomatoes absent).
- **Caching is per Rating source, because the contracts conflict:**
  - TMDB: at most 6 months ([TMDB terms](https://github.com/rfookune/reelio/blob/research/tmdb-terms/.scratch/reelio-mvp/research/tmdb-terms.md)).
  - RT via Fabric: refresh at least daily, and remove withdrawn data.
  - Metacritic via Fabric: U.S. servers, segregated from other data, purgeable.
  - IMDb datasets: local copies allowed, but not for this use.
  - OMDb: no archiving.

  So the Rating source boundary needs a per-source TTL and purge policy, not one global cache rule. This feeds the map's "Cache lifetimes and metadata refresh" item.
- **Rate limits:** if OMDb were ever used, the free key's 1,000 calls a day would require aggressive caching, which its terms don't permit. Licensed feeds have contractual refresh floors (RT: daily). Batch refreshes in a scheduled job rather than calling the source per page view.
- **Attribution in the UI:**
  - Every External rating needs its Rating source's name, and for Rotten Tomatoes the approved logo, next to the value.
  - Link to the source's page for that Movie.
  - IMDb's dataset terms require the credit "Information courtesy of IMDb … Used with permission."

  This fits PRD §10.2 ("External ratings must retain source attribution").
- **Monetization coupling:** the RT and Metacritic licences forbid ads against their content without approval. TMDB's terms already require a paid licence for any revenue. Record this next to the TMDB licence constraint on the map.

## What could not be established

- **Metacritic's (Fandom's) own Terms of Service:** blocked to automated fetches. Only the indirect evidence of Fabric's Fandom licence is used. Confirm by hand at https://www.fandom.com/terms-of-service-pp1 if this route is revisited.
- **Measured OMDb coverage:** no key was used, so there is no hit rate for recent releases. Only the operator's refresh policy and user reports are cited.
- **Prices:** IMDb on AWS Data Exchange, the Fabric Origin API and Fandango direct licensing all quote prices privately.
- **IMDb API EULA:** display and attribution terms for the AWS Data Exchange product were not reviewed. The EULA appears only on subscription.
- **OMDb's current data sources:** the operator's statements date from 2017–2018. No later statement was found.
