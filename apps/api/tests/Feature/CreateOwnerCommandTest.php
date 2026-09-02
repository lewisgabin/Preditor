<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates an owner from options without disclosing the password', function () {
    $password = 'OwnerPassword123!';

    $this->artisan('app:create-owner', [
        '--name' => 'Propietario',
        '--email' => 'owner@example.test',
        '--password' => $password,
    ])->expectsOutputToContain('Propietario creado')->doesntExpectOutput($password)->assertSuccessful();

    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    expect(Hash::check($password, $owner->password))->toBeTrue();
});

it('prompts for missing values', function () {
    $this->artisan('app:create-owner')
        ->expectsQuestion('Nombre', 'Lewis')
        ->expectsQuestion('Correo electrónico', 'lewis@example.test')
        ->expectsQuestion('Contraseña', 'OwnerPassword123!')
        ->assertSuccessful();

    expect(User::query()->where('email', 'lewis@example.test')->exists())->toBeTrue();
});

it('rejects duplicate owners and invalid input', function () {
    User::factory()->create(['email' => 'owner@example.test']);

    $this->artisan('app:create-owner', [
        '--name' => 'Owner',
        '--email' => 'owner@example.test',
        '--password' => 'short',
    ])->expectsOutputToContain('No se pudo crear')->assertFailed();

    expect(User::query()->where('email', 'owner@example.test')->count())->toBe(1);
});
