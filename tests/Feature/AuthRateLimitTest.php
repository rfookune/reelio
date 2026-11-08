<?php

use App\Models\User;

test('registration returns 429 after ten attempts from the same IP in an hour', function () {
    $this->freezeTime();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson(route('register.store'), ['email' => "person{$attempt}@example.com"])->assertUnprocessable();
    }

    $this->postJson(route('register.store'), ['email' => 'another@example.com'])
        ->assertTooManyRequests()->assertHeader('Retry-After', '3600')
        ->assertJsonPath('message', "You're going too fast. Try again in a moment.");

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->postJson(route('register.store'))->assertUnprocessable();

});

test('password recovery returns 429 after five attempts from an IP', function (string $routeName) {
    $this->freezeTime();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route($routeName), ['email' => "invalid-{$attempt}"])->assertUnprocessable();
    }

    $this->postJson(route($routeName))->assertTooManyRequests()->assertHeader('Retry-After', '60');

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->postJson(route($routeName))->assertUnprocessable();
})->with([
    'forgot password' => 'password.email',
    'reset password' => 'password.update',
]);

test('sensitive account requests return 429 per user across IP addresses', function (string $method, string $routeName, int $limit, array $payload) {
    $this->freezeTime();
    $user = User::factory()->create();
    $this->actingAs($user);

    for ($attempt = 0; $attempt < $limit; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
            ->json($method, route($routeName))->assertUnprocessable();
    }

    $this->json($method, route($routeName), $payload)
        ->assertTooManyRequests()->assertHeader('Retry-After', '60')
        ->assertSessionMissing('auth.password_confirmed_at');
    $this->assertModelExists($user);
    expect($user->fresh()->only(['name', 'email']))->toBe($user->only(['name', 'email']));

    $this->actingAs(User::factory()->create())->json($method, route($routeName))->assertUnprocessable();
})->with([
    'confirm password' => ['POST', 'password.confirm.store', 5, ['password' => 'password']],
    'delete account' => ['DELETE', 'profile.destroy', 5, ['password' => 'password']],
    'update profile' => ['PATCH', 'profile.update', 6, ['name' => 'Changed', 'email' => 'changed@example.com']],
]);
