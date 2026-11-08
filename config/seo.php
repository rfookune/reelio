<?php

return [

    /*
     * The indexing flag. Off, robots.txt disallows everything and every page is `noindex`; on, only the public
     * routes below are indexable. Switch it on only after TMDB has answered favourably in writing (ticket 16).
     */
    'indexing' => (bool) env('SEO_INDEXING_ENABLED', false),

    /*
     * The routes that are rendered on the server and, with the flag on, indexable: Discover, Movie pages and the
     * About, Privacy, Terms and Accessibility pages. Every other route is client-rendered and `noindex`.
     */
    'public_routes' => ['home', 'movies.show', 'about', 'privacy', 'terms', 'accessibility'],

];
