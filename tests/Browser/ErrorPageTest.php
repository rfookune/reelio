<?php

use Illuminate\Support\Facades\RateLimiter;

it('renders an accessible 429 page on a full visit and can return to Discover', function () {
    $this->freezeTime();
    for ($attempt = 0; $attempt < 60; $attempt++) {
        RateLimiter::hit(md5('search'.'ip:127.0.0.1'));
    }

    $page = visitWithCsp('/search');

    $page->assertSee('Too many requests')
        ->assertSee("You're going too fast. Try again in a moment.")
        ->assertNoAccessibilityIssues(level: 1)
        ->assertNoJavaScriptErrors()
        ->click('Back to Discover')->assertPathIs('/')
        ->assertNoJavaScriptErrors();
});
