# 10: Original language filter

Status: resolved

Parent: [spec](../spec.md).

**Blocked by:** 03

**What to build:** A single-select of the Title's original language, from a curated list of about twelve (English, Spanish, French, German, Italian, Japanese, Korean, Hindi, Mandarin, Portuguese, Turkish, Swedish), each shown in its own native name. It works on both tabs. The label says "Original language" so that nobody expects dubbing or subtitles.

- [x] An Original language single-select appears in the filter bar for both tabs; choosing a language lists only Titles originally in it
- [x] The options are the curated list, shown by native name, and a language outside it is ignored
- [x] The label says "Original language" and nothing implies audio or subtitle availability
- [x] The choice is kept in the URL and counted among the active filters
- [x] The TMDB request-mapping test covers the language parameter
- [x] A feature test covers a language reaching the source on both tabs and the ignored value
