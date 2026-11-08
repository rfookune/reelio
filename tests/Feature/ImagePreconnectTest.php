<?php

it('preconnects to the image host from the root layout', function () {
    $this->get('/about')->assertSee('<link rel="preconnect" href="https://image.tmdb.org">', escape: false);
});
