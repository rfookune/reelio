<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration succeeds when analytics cannot be enqueued', function () {
    Exceptions::fake();
    config(['queue.default' => 'unavailable']);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertSessionHasNoErrors()->assertRedirect(route('home', absolute: false));
    Exceptions::assertReported(InvalidArgumentException::class);
});

test('registration succeeds when analytics cannot be stored', function () {
    Exceptions::fake();
    Schema::drop('events');

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertSessionHasNoErrors()->assertRedirect(route('home', absolute: false));
    Exceptions::assertReported(QueryException::class);
});

test('rejected registrations do not record an account registration', function () {
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->post(route('register.store'), [])->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
    Queue::assertNotPushed(RecordAnalyticsEvent::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Queue::fake([RecordAnalyticsEvent::class]);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    Queue::assertPushedOn('default', RecordAnalyticsEvent::class, function (RecordAnalyticsEvent $job) {
        expect($job->userId)->toBe(User::sole()->id);
        expect($job->name)->toBe('account_registered');
        expect($job->properties)->toBe([]);

        return true;
    });
});
