<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Auth::forgetGuards();
    $this->withHeader('Origin', 'http://localhost:5173');
});

it('rejects unauthenticated profile requests', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('logs in with a stateful session and returns the current user', function () {
    $user = User::factory()->create(['password' => Hash::make('SafePassword123!')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'SafePassword123!',
    ])->assertOk()->assertJsonPath('data.email', $user->email);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('uses a generic message for invalid credentials', function () {
    User::factory()->create(['email' => 'owner@example.test']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'owner@example.test',
        'password' => 'incorrect',
    ])->assertUnprocessable()->assertExactJson([
        'message' => 'Credenciales incorrectas.',
    ]);
});

it('logs out and invalidates the current session', function () {
    $user = User::factory()->create(['password' => Hash::make('SafePassword123!')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'SafePassword123!',
    ])->assertOk();

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    Auth::forgetGuards();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rate limits repeated login attempts', function () {
    $email = 'rate-'.Str::uuid().'@example.test';

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'incorrect',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'incorrect',
    ])->assertTooManyRequests();
});

it('does not expose public registration', function () {
    $this->postJson('/api/v1/auth/register')->assertNotFound();
});

it('allows credentialed requests from both local SPA hosts', function (string $origin) {
    $this->withHeader('Origin', $origin)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
})->with([
    'localhost' => 'http://localhost:5173',
    'loopback IP' => 'http://127.0.0.1:5173',
]);

it('does not allow credentialed CORS requests from an untrusted origin', function () {
    $this->withHeader('Origin', 'https://untrusted.example')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});
