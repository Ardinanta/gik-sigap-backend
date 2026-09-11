<?php

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::query()->firstOrCreate(['code' => 'farmer'], ['name' => 'Petambak']);
    Role::query()->firstOrCreate(['code' => 'buyer'], ['name' => 'Pembeli']);
    Role::query()->firstOrCreate(['code' => 'admin'], ['name' => 'Admin']);

    $regency = Location::query()->firstOrCreate(['code' => 'GRESIK'], [
        'code' => 'GRESIK',
        'name' => 'Kabupaten Gresik',
        'type' => 'regency',
        'is_active' => true,
    ]);

    test()->district = Location::query()->firstOrCreate(['code' => 'MANYAR'], [
        'parent_id' => $regency->id,
        'name' => 'Manyar',
        'type' => 'district',
        'is_active' => true,
    ]);
});

function statefulPost(string $uri, array $data = [])
{
    return test()
        ->withHeader('Origin', 'http://localhost:5173')
        ->postJson($uri, $data);
}

it('registers a farmer and starts an authenticated session', function () {
    $response = statefulPost('/api/v1/auth/register', [
        'name' => 'Budi Santoso',
        'email' => 'BUDI@EXAMPLE.COM',
        'phone' => '081234567890',
        'location_id' => test()->district->id,
        'role' => 'farmer',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'budi@example.com')
        ->assertJsonPath('data.location.name', 'Manyar')
        ->assertJsonPath('data.roles.0', 'farmer')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');

    $user = User::query()->where('email', 'budi@example.com')->firstOrFail();

    expect(Hash::check('password123', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('user_roles', [
        'user_id' => $user->id,
        'role_id' => Role::query()->where('code', 'farmer')->value('id'),
    ]);
});

it('rejects admin public registration and an unavailable location', function () {
    $inactiveDistrict = Location::query()->create([
        'code' => 'BUNGAH',
        'name' => 'Bungah',
        'type' => 'district',
        'is_active' => false,
    ]);

    statefulPost('/api/v1/auth/register', [
        'name' => 'Admin Baru',
        'email' => 'admin@example.com',
        'phone' => '081234567891',
        'location_id' => $inactiveDistrict->id,
        'role' => 'admin',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['location_id', 'role']);

    $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
});

it('rejects duplicate email and invalid password confirmation', function () {
    User::factory()->create(['email' => 'budi@example.com']);

    statefulPost('/api/v1/auth/register', [
        'name' => 'Budi',
        'email' => 'BUDI@example.com',
        'phone' => '081234567892',
        'location_id' => test()->district->id,
        'role' => 'buyer',
        'password' => 'password123',
        'password_confirmation' => 'berbeda123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('logs in an active user and returns the current user', function () {
    $user = User::factory()->create([
        'email' => 'buyer@example.com',
        'password' => 'password123',
        'location_id' => test()->district->id,
    ]);
    $user->roles()->attach(Role::query()->where('code', 'buyer')->value('id'), ['created_at' => now()]);

    statefulPost('/api/v1/auth/login', [
        'email' => 'BUYER@EXAMPLE.COM',
        'password' => 'password123',
        'remember' => true,
    ])->assertOk()
        ->assertJsonPath('data.roles.0', 'buyer');

    $this->assertAuthenticatedAs($user);

    $this->withHeader('Origin', 'http://localhost:5173')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('rejects invalid credentials and inactive or deleted users', function () {
    User::factory()->create([
        'email' => 'active@example.com',
        'password' => 'password123',
    ]);
    User::factory()->inactive()->create([
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ]);
    $deleted = User::factory()->create([
        'email' => 'deleted@example.com',
        'password' => 'password123',
    ]);
    $deleted->delete();

    foreach (['active@example.com' => 'wrong-password', 'inactive@example.com' => 'password123', 'deleted@example.com' => 'password123'] as $email => $password) {
        statefulPost('/api/v1/auth/login', compact('email', 'password'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    $this->assertGuest();
});

it('requires authentication and logout ends the session', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();

    $user = User::factory()->create();

    statefulPost('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    statefulPost('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
