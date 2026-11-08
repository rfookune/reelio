# How much Episode data to store and when to refresh it

Type: grilling
Blocked by: 01, 02
Status: open

## Question

Given the model decision (TV-only columns live in `tv_show_details`; Seasons and Episodes in their own tables keyed to a Title) and TMDB's data shape: which Episode and Season fields Reelio stores, whether Episodes are fetched on first view or all at once, how airing TV Shows get new Episodes, the equivalent of the Movie cap guard and purge for TV Shows, and the failure states (fresh, stale, unavailable) for TV Shows.
