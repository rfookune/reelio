<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

/** The Inertia page object a full-page response carries. */
function inertiaPage(TestResponse $response): array
{
    return $response->viewData('page');
}

test('signing out clears the browser history on the next page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $page = inertiaPage($this->get(route('home')));
    expect($page['clearHistory'] ?? false)->toBeTrue();

    expect(inertiaPage($this->get(route('home')))['clearHistory'] ?? false)->toBeFalse();
});

test('a page visited while signed in does not clear the history', function () {
    $this->actingAs(User::factory()->create());

    expect(inertiaPage($this->get(route('home')))['clearHistory'] ?? false)->toBeFalse();
});

test('the pages that show a user their own data encrypt the browser history', function (string $route) {
    $this->actingAs(User::factory()->create());

    expect(inertiaPage($this->get(route($route)))['encryptHistory'] ?? false)->toBeTrue();
})->with(['watchlist', 'watched', 'profile.edit', 'appearance.edit']);

test('public pages do not encrypt the browser history', function () {
    expect(inertiaPage($this->get(route('home')))['encryptHistory'] ?? false)->toBeFalse();
});
