<?php

use App\Models\Movie;
use App\Models\User;

function moviePageUrl(): string
{
    $movie = Movie::factory()->create();

    return route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);
}

it('picks the country by preference, then the guest choice, then the edge header, then US', function (?string $preferred, ?string $cookie, ?string $header, string $expected) {
    $request = $preferred === null ? $this : $this->actingAs(User::factory()->create(['preferred_country' => $preferred]));

    if ($cookie !== null) {
        $request->withCookie('country', $cookie);
    }

    if ($header !== null) {
        $request->withHeader('CF-IPCountry', $header);
    }

    $request->get(moviePageUrl())->assertInertia(fn ($page) => $page->where('whereToWatch.country', $expected));
})->with([
    'preference over everything' => ['CA', 'GB', 'DE', 'CA'],
    'guest choice over the header' => [null, 'GB', 'DE', 'GB'],
    'header alone' => [null, null, 'DE', 'DE'],
    'nothing' => [null, null, null, 'US'],
]);

it('ignores the guest country cookie once signed in, so clearing the preference means automatic', function () {
    $this->actingAs(User::factory()->create(['preferred_country' => null]))
        ->withCookie('country', 'GB')
        ->withHeader('CF-IPCountry', 'DE')
        ->get(moviePageUrl())
        ->assertInertia(fn ($page) => $page->where('whereToWatch.country', 'DE'));
});

it('ignores a guest country cookie that does not name a supported country', function (string $cookie) {
    $this->withCookie('country', $cookie)
        ->withHeader('CF-IPCountry', 'DE')
        ->get(moviePageUrl())
        ->assertInertia(fn ($page) => $page->where('whereToWatch.country', 'DE'));
})->with(['unsupported' => 'BR', 'lowercase' => 'gb', 'junk' => '<script>']);

it('says the detected country is not supported only when it decided the country', function (?string $cookie, ?string $header, string $expected, bool $notice) {
    $request = $cookie === null ? $this : $this->withCookie('country', $cookie);

    if ($header !== null) {
        $request->withHeader('CF-IPCountry', $header);
    }

    $request->get(moviePageUrl())->assertInertia(fn ($page) => $page
        ->where('whereToWatch.country', $expected)
        ->where('whereToWatch.countryUnsupported', $notice));
})->with([
    'unsupported country detected' => [null, 'BR', 'US', true],
    'supported country detected' => [null, 'DE', 'DE', false],
    'supported country detected in lowercase' => [null, 'de', 'DE', false],
    'no country detected' => [null, null, 'US', false],
    'unknown to the edge' => [null, 'XX', 'US', false],
    'Tor' => [null, 'T1', 'US', false],
    'a guest choice overrides the unsupported country' => ['GB', 'BR', 'GB', false],
]);

it('never stores the detected country', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withHeader('CF-IPCountry', 'DE')->get(moviePageUrl());

    $response->assertInertia(fn ($page) => $page->where('whereToWatch.country', 'DE'))->assertCookieMissing('country');
    expect($user->refresh()->preferred_country)->toBeNull();
});

it('lists only the supported countries to choose from', function () {
    $this->get(moviePageUrl())->assertInertia(fn ($page) => $page->where('whereToWatch.countries', [
        ['code' => 'AU', 'name' => 'Australia'],
        ['code' => 'CA', 'name' => 'Canada'],
        ['code' => 'FR', 'name' => 'France'],
        ['code' => 'DE', 'name' => 'Germany'],
        ['code' => 'IE', 'name' => 'Ireland'],
        ['code' => 'IT', 'name' => 'Italy'],
        ['code' => 'NZ', 'name' => 'New Zealand'],
        ['code' => 'ES', 'name' => 'Spain'],
        ['code' => 'GB', 'name' => 'United Kingdom'],
        ['code' => 'US', 'name' => 'United States'],
    ]));
});

it('remembers a guest\'s choice in the country cookie', function () {
    $url = moviePageUrl();

    $this->from($url)->put(route('country.update'), ['country' => 'GB'])
        ->assertRedirect($url)
        ->assertCookie('country', 'GB');
});

it('saves a signed-in user\'s choice to their account', function () {
    $user = User::factory()->create(['preferred_country' => 'CA']);
    $url = moviePageUrl();

    $this->actingAs($user)->from($url)->put(route('country.update'), ['country' => 'GB'])
        ->assertRedirect($url)
        ->assertCookieMissing('country');
    expect($user->refresh()->preferred_country)->toBe('GB');
});

it('rejects a choice of a country that is not supported', function (array $payload, string $message) {
    $user = User::factory()->create(['preferred_country' => 'CA']);

    $this->actingAs($user)->put(route('country.update'), $payload)
        ->assertSessionHasErrors(['country' => $message])
        ->assertCookieMissing('country');
    expect($user->refresh()->preferred_country)->toBe('CA');
})->with([
    'unsupported' => [['country' => 'BR'], 'The selected country is invalid.'],
    'lowercase' => [['country' => 'gb'], 'The selected country is invalid.'],
    'missing' => [[], 'The country field is required.'],
]);

it('copies a guest\'s choice to the account on sign-in only when the account has none', function (?string $preferred, string $cookie, ?string $expected) {
    $user = User::factory()->create(['preferred_country' => $preferred]);

    $this->withCookie('country', $cookie)
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->preferred_country)->toBe($expected);
})->with([
    'account has none' => [null, 'GB', 'GB'],
    'account already has one' => ['CA', 'GB', 'CA'],
    'the cookie names an unsupported country' => [null, 'BR', null],
]);

it('copies a guest\'s choice to a newly registered account', function () {
    $this->withCookie('country', 'GB')->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('home'));

    expect(User::sole()->preferred_country)->toBe('GB');
});
