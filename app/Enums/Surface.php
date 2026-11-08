<?php

namespace App\Enums;

/**
 * A place in the app a visitor acts on or arrives from. As an action's source it names the surface the Watchlist or
 * Watched control was used on, and as a Movie page's referrer the surface the visitor came from; the value is what
 * requests, URLs and analytics carry. Each use accepts only some surfaces, listed by the methods below.
 */
enum Surface: string
{
    case Discover = 'discover';
    case Search = 'search';
    case Movie = 'movie';
    case TvShow = 'tv-show';
    case Watchlist = 'watchlist';
    case Watched = 'watched';
    case Picker = 'picker';
    case Direct = 'direct';

    /** @return list<self> The surfaces that carry Movie Watchlist and Watched controls. */
    public static function movieActionSources(): array
    {
        return [self::Discover, self::Search, self::Movie, self::Watchlist, self::Watched, self::Picker];
    }

    /** @return list<self> The surfaces that carry the TV Show Watchlist control. */
    public static function tvShowActionSources(): array
    {
        return [self::TvShow, self::Search, self::Watchlist, self::Watched];
    }

    /** @return list<self> The surfaces a visitor can arrive at a Movie page from. */
    public static function movieReferrers(): array
    {
        return [self::Discover, self::Search, self::Watchlist, self::Watched, self::Picker, self::Direct];
    }

    public static function tvShowReferrerFrom(mixed $value): ?self
    {
        $surface = self::movieReferrerFrom($value);

        return $surface === self::Picker ? null : $surface;
    }

    /** The Movie referrer a query value names, or null when it is missing, not a string or not a Movie referrer. */
    public static function movieReferrerFrom(mixed $value): ?self
    {
        $surface = is_string($value) ? self::tryFrom($value) : null;

        return $surface !== null && in_array($surface, self::movieReferrers(), true) ? $surface : null;
    }
}
