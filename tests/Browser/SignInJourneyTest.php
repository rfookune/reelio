<?php

it('lets a guest register, log out and log back in', function (int $width, int $height) {
    $page = visitWithCsp(route('register', absolute: false), $width, $height);

    $page->assertSee('Create an account')
        ->assertAttribute('#name', 'autocomplete', 'name')
        ->assertAttribute('#email', 'autocomplete', 'email')
        ->assertAttribute('#password', 'autocomplete', 'new-password')
        ->assertAttribute('#password_confirmation', 'autocomplete', 'new-password');

    pasteInto($page, '#name', 'Alex Moviegoer');
    pasteInto($page, '#email', 'alex@example.com');
    pasteInto($page, '#password', 'Movie-night-2026!');
    pasteInto($page, '#password_confirmation', 'Movie-night-2026!');

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 1)
        ->click('@register-user-button')
        ->assertPathIs('/')
        ->assertSee('Discover')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticated();

    $page->navigate(route('profile.edit', absolute: false))
        ->assertValue('#name', 'Alex Moviegoer')
        ->assertValue('#email', 'alex@example.com')
        ->assertNoJavaScriptErrors()
        ->click('Log out')
        ->assertPathIs('/')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();

    $page->navigate(route('login', absolute: false))
        ->assertSee('Log in to your account')
        ->assertAttribute('#email', 'autocomplete', 'email')
        ->assertAttribute('#password', 'autocomplete', 'current-password');

    pasteInto($page, '#email', 'alex@example.com');
    pasteInto($page, '#password', 'Movie-night-2026!');

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 1)
        ->click('@login-button')
        ->assertPathIs('/')
        ->assertSee('Discover')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 1);

    $this->assertAuthenticated();
})->with([
    'desktop' => [1440, 900],
    'mobile' => [390, 844],
]);
