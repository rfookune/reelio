<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset link requests give the same response for known, unknown and recently emailed accounts', function (bool $json) {
    $this->freezeTime();
    Notification::fake();
    $user = User::factory()->create();
    $message = "If that email has an account, we've sent a link";

    foreach ([$user->email, 'unknown@example.com', $user->email] as $email) {
        $response = $this->from(route('password.request'))
            ->json('POST', route('password.email'), ['email' => $email], $json ? ['Accept' => 'application/json'] : ['Accept' => 'text/html']);

        if ($json) {
            $response->assertOk()->assertExactJson(['message' => $message]);
        } else {
            $response->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors()->assertSessionHas('status', $message);
        }
    }

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
    Notification::assertCount(1);
})->with(['JSON' => true, 'browser form' => false]);

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
