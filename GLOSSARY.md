# Reelio

Reelio helps people discover movies, save the ones they want to see, decide what to watch next, and find where to stream it.

## Language

### Movie data

**Title**:
A Movie or a TV Show; the one word for either when the difference doesn't matter. People see "movie" or "TV show", never "Title".
_Avoid_: Media, content, item, work

**Name**:
The text a Title is called, as Reelio displays it.
_Avoid_: Title

**Movie**:
A film Reelio shows, identified by Reelio's own id and linked to its Catalog source entry; a Movie exists in Reelio once someone has opened or saved it.
_Avoid_: Film, provider movie

**Removed Movie**:
A Movie the Catalog source no longer has; Reelio keeps users' Watchlist, Watched and User rating facts about it but can show none of its details.
_Avoid_: Shell, dropped movie, deleted movie

**Removed Title**:
A Removed Movie or a Removed TV Show; the one word for either when the difference doesn't matter.
_Avoid_: Shell, dropped title, deleted title

**Catalog source**:
The external service Reelio takes movie metadata, artwork, and Availability from (TMDB today).
_Avoid_: Provider, movie provider, data provider

**Rating source**:
An organization whose score for a Movie or a TV Show Reelio displays, always shown with its name (e.g. TMDB, IMDb).
_Avoid_: Provider, review site

**External rating**:
A score for a Movie or a TV Show published by a Rating source, displayed with that source's attribution.
_Avoid_: Score, critic rating, primary rating

**Discover**:
The public homepage of curated Movie sections, the same for every visitor.
_Avoid_: Home feed, browse, explore

**Release stage**:
Where a Movie is in its release, in a visitor's country: In theater, At home or Coming soon; estimated from its release dates, not from showtimes. A TV Show has none.
_Avoid_: Status, release status, availability

**Browse**:
Finding Titles by narrowing them with filters (Streaming service, genre, Release stage and so on) instead of searching by Name; Browse and a search by Name are never combined.
_Avoid_: Discover, explore, filter search

### TV data

**TV Show**:
A programme made of Seasons of Episodes that Reelio shows, linked to its Catalog source entry.
_Avoid_: Show, series, TV series, programme

**Season**:
A numbered group of a TV Show's Episodes.
_Avoid_: Series, volume

**Episode**:
One installment of a TV Show, within a Season; the unit a user records having seen.
_Avoid_: Installment, chapter

### Streaming

**Streaming service**:
A platform where a Movie or a TV Show can be watched, such as Netflix or Apple TV.
_Avoid_: Provider, streaming provider, platform

**Availability**:
A Streaming service offering a Movie or a TV Show in a specific country, as a subscription stream, a free offer, a rental, or a purchase. A TV Show has Availability as a whole, never per Season or Episode.
_Avoid_: Watch provider, streaming option

### A user's Movies

**Watchlist**:
The Movies and TV Shows a user wants to watch, or watch again.
_Avoid_: Queue, saved movies, list, library

**Date added**:
When a Title was last put on a user's Watchlist; re-adding it after a removal sets a new one.
_Avoid_: Saved date, watchlisted at

**Watched**:
A user's record of having seen a Movie or an Episode; set by marking it as watched, independently of the Watchlist.
_Avoid_: Viewing history, history, seen, viewing record

**Watched date**:
The most recent time a user saw a Movie or an Episode; one per Movie or Episode.
_Avoid_: Viewing date, watched at

**Watched progress**:
A user's record of which Episodes of a TV Show they have seen.
_Avoid_: Viewing history, progress, tracking

**Special**:
An Episode outside a TV Show's numbered Seasons; it can be marked as watched but never counts toward Completed.
_Avoid_: Extra, bonus episode

**Not started**:
A TV Show of which a user has watched no Episode.

**In progress**:
A TV Show of which a user has watched some Episodes but is not Completed.

**Completed**:
A TV Show whose every aired Episode in the numbered Seasons a user has watched.
_Avoid_: Finished, watched

**User rating**:
A user's own score for a Movie or a TV Show, a whole number from 1 to 10, given at any time and independently of the Watchlist and Watched. Only that user sees it; it is not an External rating.
_Avoid_: Local rating, my score, review, community rating

**Movie picker**:
Chooses one Movie at random from a user's Watchlist, optionally narrowed by genre.
_Avoid_: Randomizer, roulette, shuffle
