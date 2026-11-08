<?php

return [
    'tv_shows' => ['enabled' => (bool) env('CATALOG_TV_SHOWS', false)],

    'language' => env('CATALOG_LANGUAGE', 'en-US'),
    'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/'),

    /*
     * The countries Reelio stores and shows Availability for. Adding one is a config change plus a backfill.
     */
    'supported_countries' => ['US', 'CA', 'GB', 'AU', 'NZ', 'IE', 'DE', 'FR', 'ES', 'IT'],

    /*
     * Shown when the visitor hasn't chosen a country and the edge detected none, or one that isn't supported.
     */
    'default_country' => 'US',

    /*
     * The request header the edge puts the visitor's detected country in. Detection is never stored.
     */
    'country_header' => env('CATALOG_COUNTRY_HEADER', 'CF-IPCountry'),
    'tmdb' => [
        'base_url' => 'https://api.themoviedb.org/3/',
        'token' => env('TMDB_READ_TOKEN', ''),
    ],

    'details' => [
        'hard_lifetime_days' => 30,
    ],

    /*
     * The daily cleanup deletes a Movie nobody holds once it has gone unviewed for more than `after_days`, with its
     * genre links, Availability, detail cache and TMDB rating. Undo by catalog id re-creates the row.
     */
    'purge_unviewed' => [
        'enabled' => (bool) env('CATALOG_PURGE_UNVIEWED', true),
        'after_days' => 30,
    ],

    /*
     * TMDB's terms allow keeping its data for six months. The daily cleanup clears everything taken from TMDB off a
     * Movie whose last successful sync is more than `after_days` old, keeping users' facts, and deletes TMDB ratings
     * older than that. This runs whether or not the unviewed purge is switched on.
     */
    'cap' => [
        'after_days' => 170,
    ],

    /*
     * Each cleanup run handles at most `batch_size` Movies, ratings or Streaming services per step, then hands the
     * rest to its next run, so each stays short. A Streaming service no Availability references is deleted only once
     * it was last fetched more than `unreferenced_service_grace_seconds` ago, so a refresh that has just stored it
     * keeps it.
     */
    'cleanup' => [
        'batch_size' => 500,
        'unreferenced_service_grace_seconds' => 3600,
    ],

    /*
     * A search result carries no IMDb id, so the Search page looks it up, one small source call per Movie, to find
     * the Movie's IMDb rating. An id is remembered for `lifetime_days`, and the answer that the source has none for
     * a Movie for `missing_lifetime_seconds`, since a new release gains one. A request looks up at most
     * `max_per_request` Movies, within `budget_seconds`, and lookups share `requests_per_second` across all
     * visitors, below the live ceiling so they never starve the pages visitors are waiting on. No visitor waits on a
     * lookup, so one over the ceiling waits for the next second, within the budget. A lookup still without a turn
     * then, or over `max_per_request`, is skipped (it tries for a turn every `wait_milliseconds`), and its Movie shows no IMDb rating until a later search finds room.
     */
    'imdb_lookup' => [
        'lifetime_days' => 30,
        'missing_lifetime_seconds' => 86400,
        'max_per_request' => 20,
        'requests_per_second' => 8,
        'budget_seconds' => 6,
        'wait_milliseconds' => 100,
    ],

    'movie_page' => [
        /*
         * How many top-billed cast members the Movie page shows; the rest are on its full cast page.
         */
        'cast_limit' => 12,
    ],

    'refresh' => [
        /*
         * A Movie page view refreshes the Movie in the request once its last sync is older than this.
         */
        'on_view_after_seconds' => 86400,

        /*
         * The daily planner refreshes a Movie on any Watchlist once its last sync is more than `watchlist_after_days`
         * old, and one that is only Watched once it is more than `watched_after_days` old. A Movie nobody holds is
         * never refreshed by schedule. Each planner run dispatches at most `planner_page_size` refreshes, then hands
         * the rest to its next run, so each stays short.
         */
        'watchlist_after_days' => 3,
        'watched_after_days' => 30,
        'planner_page_size' => 200,

        /*
         * The owner is alerted when a Movie someone holds is more than this many days past its scheduled refresh.
         */
        'overdue_alert_after_days' => 14,
    ],

    'search' => [
        /*
         * A search page is read from the cache for `fresh_seconds`, then read again. While the source fails, the
         * cached page is served as stale until `stale_seconds` after it was read, then the search is unavailable.
         */
        'fresh_seconds' => 3600,
        'stale_seconds' => 86400,

        /*
         * Shorter queries show Trending instead of searching; longer ones are cut.
         */
        'minimum_query_length' => 2,
        'maximum_query_length' => 100,
    ],

    'discover' => [
        'movies_per_section' => 10,

        /*
         * How many pages a section reads to fill up after the poster filter and the dedupe, at most. TMDB's own
         * cap of 500 pages is applied whatever this says.
         */
        'max_pages_per_section' => 3,

        /*
         * Coming soon lists Movies whose US date is from tomorrow to this many days out. Each candidate's US date
         * needs a detail call, so a build looks up at most `max_lookups` of them, leaving time for the sections
         * after it.
         */
        'coming_soon' => [
            'days_ahead' => 60,
            'max_lookups' => 30,
        ],

        /*
         * Each day's genre collections: `per_day` consecutive genres from this list, by TMDB genre id, starting
         * further along it each day, so consecutive days differ. Each lists the genre's popular Movies with at
         * least `minimum_votes` votes.
         */
        'genre_collections' => [
            'per_day' => 3,
            'minimum_votes' => 300,
            'genres' => [
                28 => 'Action',
                35 => 'Comedy',
                18 => 'Drama',
                878 => 'Science Fiction',
                27 => 'Horror',
                16 => 'Animation',
                53 => 'Thriller',
                10749 => 'Romance',
            ],
        ],

        /*
         * A section, or the hero, whose rebuild fails keeps its previous version this long, then is hidden.
         */
        'stale_after_days' => 7,

        /*
         * How long a build may keep asking the source, by profile. The calls are made one after another, so the
         * background budget leaves room for one more slow call (about 40 s with its retries and throttle waits) inside
         * the job's 85 s timeout; the interactive one keeps the first visitor's wait short.
         */
        'build_budget_seconds' => [
            'background' => 35,
            'interactive' => 6,
        ],

        /*
         * How long a first visitor waits for another visitor's build before the page renders without it.
         */
        'build_wait_seconds' => 10,
    ],

    'creation_limit' => [
        'attempts' => 20,
        'decay_seconds' => 60,
    ],

    'guard' => [
        'failure_threshold' => 5,
        'breaker_open_seconds' => 30,
        'failure_memory_seconds' => 86400,
        'live_requests_per_second' => 15,

        /*
         * Every background request shares this rate, retries included. Over it, a request waits its turn for up to
         * `background_wait_seconds`, then fails as unavailable. With three attempts of 10 s, a background call can
         * take about 40 s at worst.
         */
        'background_requests_per_second' => 20,
        'background_wait_seconds' => 3,
    ],
];
