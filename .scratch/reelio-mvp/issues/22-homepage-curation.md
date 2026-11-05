# Homepage curation

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

What exactly does Discover show, and in what order?

- Which sections appear (trending, popular, top rated, upcoming, genre collections) and how many Movies each holds.
- Which genres get a collection, and whether the set is fixed or rotates.
- Which Movie the hero features and how often it changes.
- Whether Movies are deduplicated across sections (the PRD only requires it within a section).
- How the Upcoming row deals with `release_date` values outside the stated range ([Verify TMDB behaviours with a live key](./17-tmdb-live-checks.md)).
- Trending is global, not per country ([Country and Availability presentation](./11-country-and-availability.md)); page sizes are capped at 500.
