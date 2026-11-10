# 10: Original language filter

Status: ready-for-agent

Parent: [spec](../spec.md).

**Blocked by:** 03

**What to build:** A single-select of the Title's original language, from a curated list of about twelve (English, Spanish, French, German, Italian, Japanese, Korean, Hindi, Mandarin, Portuguese, Turkish, Swedish), each shown in its own native name. It works on both tabs. The label says "Original language" so that nobody expects dubbing or subtitles.

- [ ] An Original language single-select appears in the filter bar for both tabs; choosing a language lists only Titles originally in it
- [ ] The options are the curated list, shown by native name, and a language outside it is ignored
- [ ] The label says "Original language" and nothing implies audio or subtitle availability
- [ ] The choice is kept in the URL and counted among the active filters
- [ ] The TMDB request-mapping test covers the language parameter
- [ ] A feature test covers a language reaching the source on both tabs and the ignored value
