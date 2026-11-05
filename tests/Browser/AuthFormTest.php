<?php

it('allows email autocomplete and paste when requesting a password reset', function () {
    $page = visitWithCsp(route('password.request', absolute: false));

    $page->assertSee('Forgot password')
        ->assertAttribute('#email', 'autocomplete', 'email');

    pasteInto($page, '#email', 'alex@example.com');

    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues(level: 1);
});
