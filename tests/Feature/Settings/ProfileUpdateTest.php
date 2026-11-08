<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();
    RecordAnalyticsEvent::dispatchSync(
        uuid: '2ab37180-1884-4328-8b4a-981d723fc247',
        userId: $user->id,
        name: 'account_registered',
        properties: [],
        occurredAt: '2026-11-05 12:00:00',
    );
    $this->assertDatabaseCount('events', 1);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
    $this->assertDatabaseCount('events', 0);
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('profile page offers the supported countries', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->has('countries', 10)->where('countries.0', ['code' => 'AU', 'name' => 'Australia']));
});

test('preferred country can be chosen and cleared', function (?string $country, ?string $expected) {
    $user = User::factory()->create(['preferred_country' => 'CA']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'preferred_country' => $country])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->preferred_country)->toBe($expected);
})->with([
    'a supported country' => ['GB', 'GB'],
    'automatic' => ['', null],
]);

test('preferred country must be a supported country', function () {
    $user = User::factory()->create(['preferred_country' => 'CA']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'preferred_country' => 'BR'])
        ->assertSessionHasErrors(['preferred_country' => 'The selected preferred country is invalid.']);

    expect($user->refresh()->preferred_country)->toBe('CA');
});

test('deleting an account removes its Watchlist and Watched facts and signs it out everywhere', function () {
    $user = User::factory()->create();
    $bystander = User::factory()->create();
    UserMovie::factory()->for($user)->create();
    UserMovie::factory()->for($user)->watched()->create();
    $bystanderFact = UserMovie::factory()->for($bystander)->create();
    foreach ([[$user->id, 'phone'], [$bystander->id, 'laptop']] as [$userId, $id]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => now()->timestamp]);
    }

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    expect(UserMovie::pluck('id')->all())->toBe([$bystanderFact->id])
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'laptop')->exists())->toBeTrue();
});

test('deleting an account logs account_deleted with only the user id', function () {
    Log::spy();
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    Log::shouldHaveReceived('info')->once()->with('account_deleted', ['user_id' => $user->id]);
});

test('a refused account deletion logs nothing', function () {
    Log::spy();
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'wrong-password']);

    Log::shouldNotHaveReceived('info', ['account_deleted', Mockery::any()]);
});

test('deleting an account clears the browser history on the next page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    expect($this->get(route('home'))->viewData('page')['clearHistory'] ?? false)->toBeTrue();
});
