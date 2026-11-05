# Reelio

Reelio helps people discover movies, save the ones they want to see, decide what to watch next, and find where to stream it.

## Language

### Movie data

**Movie**:
A film Reelio shows, identified by Reelio's own id and linked to its Catalog source entry; a Movie exists in Reelio once someone has opened it.
_Avoid_: Title, film, provider movie

**Catalog source**:
The external service Reelio takes movie metadata, artwork, and Availability from (TMDB today).
_Avoid_: Provider, movie provider, data provider

**Rating source**:
An organization whose score for a Movie Reelio displays, always shown with its name (e.g. TMDB, IMDb).
_Avoid_: Provider, review site

**External rating**:
A score for a Movie published by a Rating source, displayed with that source's attribution.
_Avoid_: Score, critic rating, primary rating

### Streaming

**Streaming service**:
A platform where a Movie can be watched, such as Netflix or Apple TV.
_Avoid_: Provider, streaming provider, platform

**Availability**:
A Streaming service offering a Movie in a specific country, as a subscription stream, a free offer, a rental, or a purchase.
_Avoid_: Watch provider, streaming option

### A user's Movies

**Watchlist**:
The Movies a user wants to watch, or watch again.
_Avoid_: Queue, saved movies, list, library

**Watched**:
A user's record of having seen a Movie; set by marking the Movie as watched, independently of the Watchlist.
_Avoid_: Viewing history, history, seen, viewing record

**Watched date**:
The most recent time a user saw a Movie; one per Movie.
_Avoid_: Viewing date, watched at

**Movie picker**:
Chooses one Movie at random from a user's Watchlist, optionally narrowed by genre.
_Avoid_: Randomizer, roulette, shuffle
