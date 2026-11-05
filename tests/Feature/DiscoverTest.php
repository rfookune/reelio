<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the homepage renders the discover page for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Discover'));
});

test('the homepage renders the discover page for signed-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Discover'));
});

test('the starter kit dashboard no longer exists', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertNotFound();
});
