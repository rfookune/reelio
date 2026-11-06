# 09: Trailer

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The Movie page plays the Movie's trailer on demand: a cover from the TMDB backdrop, and nothing loads from YouTube until the visitor presses play. See the spec's "Movie page" trailer bullets and the trailer ranking under "Catalog".

**Blocked by:** [07: Movie page layout and details](./07-movie-page-layout-and-details.md)

**Status:** resolved

- [x] The adapter ranks YouTube videos only (keys must match `^[A-Za-z0-9_-]{11}$`): Trailer before Teaser, then official, then language match, then the latest published on or before the primary release date, else the earliest; `include_video_language` is passed explicitly; an adapter test shows _Interstellar_ picks "Trailer 4"
- [x] A click-to-play player: the backdrop cover with a play button labelled "Play trailer: {title}" and "{type} · {name}"; play swaps in the `youtube-nocookie` embed with `rel=0&autoplay=1`, `referrerpolicy="strict-origin-when-cross-origin"`, `title="Trailer: {title}"`, the `allow` attribute, at least 200×200 with no overlays
- [x] The caption "Plays from YouTube. YouTube may set cookies once you press play." and an always-present "Watch on YouTube" link (new tab, `rel="noopener noreferrer"`), both built from the key
- [x] No trailer hides the section; with the source unavailable the Trailer shows the "temporarily unavailable" panel
- [x] The Discover hero's "Watch trailer" button can reuse this player (wired in Discover)
