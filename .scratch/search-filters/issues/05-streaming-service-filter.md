# 05: Streaming service filter

Status: resolved

Parent: [spec](../spec.md). Uses the glossary terms **Streaming service** and **Availability**.

**Blocked by:** 03

**What to build:** A multi-select of Streaming services in the filter bar. Several chosen services mean "any of them", counting only subscription and free offers (never rent or purchase), in the visitor's country, as the existing Availability reads it. The options are the services in that country that offer the active tab's kind: the top twelve by the source's display priority are shown and the rest sit behind a "More" control with a search box. The list is cached per country and kind.

- [x] A Streaming service multi-select appears in the filter bar for both tabs; choosing services lists only Titles available on any of them by subscription or free offer
- [x] The options are limited to the visitor's country and the active tab's kind, with the top twelve shown and the rest behind "More" with a search box
- [x] The visitor's country follows the existing rule: chosen country, else detected, else the default
- [x] A service that isn't offered in the country is ignored, and the number of selected services is capped
- [x] The selection is kept in the URL and counted among the active filters
- [x] The TMDB request-mapping test covers the any-of join, the watch region and the subscription-and-free restriction
- [x] Feature tests cover the options per country and kind, the any-of filter reaching the source, and the ignored and capped values

## Revised: the services search

The "More" control with a search box was replaced after the same prototype (see ticket 03, "Revised UI"): the twelve recommended services show, with any chosen beyond them, and a compact field in the row, "Search all N services", ranks every service by how well its name matches and lists the best twenty. The ranking is `resources/js/lib/streamingServiceSearch.ts`.
