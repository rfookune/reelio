# Reelio

Reelio helps people discover movies, save the ones they want to see, decide what to watch next, and find where to stream it.

## Language

### Movie data

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
A Streaming service offering a Movie in a specific country, as a subscription stream, a rental, or a purchase.
_Avoid_: Watch provider, streaming option
