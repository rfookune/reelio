<?php

return [

    /*
     * The indexing flag. Off, robots.txt disallows everything and every page is `noindex`; on, only the public
     * routes below are indexable. Switch it on only after TMDB has answered favourably in writing (ticket 16).
     */
    'indexing' => (bool) env('SEO_INDEXING_ENABLED', false),

    /*
     * The routes rendered on the server: Discover, Movie pages and the About, Privacy, Terms and Accessibility
     * pages. Every other route is rendered on the client, and so is any page while the SSR server is down. Add a
     * route here to server-render it; that doesn't make it indexable.
     */
    'ssr_routes' => ['home', 'movies.show', 'about', 'privacy', 'terms', 'accessibility'],

    /*
     * The routes crawlers may index when the flag is on. Every other route, and every response that isn't a 200,
     * is `noindex` whatever the flag says.
     */
    'indexable_routes' => ['home', 'movies.show', 'about', 'privacy', 'terms', 'accessibility'],

];
