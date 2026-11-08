# 06: TV Show page content

**What to build:** Fill in the TV Show page beside the Season accordion (built later): the hero with Name, original Name when different, year range, Season count, US content rating, genres and External ratings slot; the sticky Details panel (Created by, Network, Status, First aired, Last aired, Seasons and Episodes counts counting only numbered Seasons and aired Episodes, Rated, Genres, Original title); overview and tagline; click-to-play Trailer; the top-12 Cast with no full-cast page. Also the SEO basics (title, description, Open Graph `video.tv_show`, share image, `TVSeries` JSON-LD) and the Movie-style states: one response with no skeletons, stale, and source-unavailable with 'temporarily unavailable' panels. The right column is sticky at the top on desktop and stacks below `lg`.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 05 A TV Show page appears behind the flag

**Status:** ready-for-agent

- [ ] Hero, Details panel, overview, Trailer and Cast render from stored and appended data
- [ ] Counts exclude Specials and unaired Episodes; no next-episode date is shown
- [ ] SEO tags and `TVSeries` JSON-LD are present and correct
- [ ] A source-unavailable stored TV Show shows the 'temporarily unavailable' panels and hides the certification badge
- [ ] No Episode stills are shown anywhere
- [ ] Feature tests cover each state
