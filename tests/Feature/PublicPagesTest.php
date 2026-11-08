<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the public pages render their Inertia components', function (string $route, string $component) {
    $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['about', 'About'],
    ['privacy', 'Privacy'],
    ['terms', 'Terms'],
    ['accessibility', 'Accessibility'],
]);

test('the public pages are indexable when the indexing flag is on', function (string $route) {
    config(['seo.indexing' => true]);

    expect($this->get(route($route))->headers->get('X-Robots-Tag'))->toBeNull();
})->with(['about', 'privacy', 'terms', 'accessibility']);

test('the public pages are noindex while the indexing flag is off', function (string $route) {
    config(['seo.indexing' => false]);

    expect($this->get(route($route))->headers->get('X-Robots-Tag'))->toBe('noindex');
})->with(['about', 'privacy', 'terms', 'accessibility']);
