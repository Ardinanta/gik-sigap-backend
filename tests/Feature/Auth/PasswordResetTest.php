<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('sends a frontend password reset link without exposing unknown emails', function () {
    Notification::fake();
    config(['app.frontend_url' => 'http://localhost:5173']);

    $user = User::factory()->create(['email' => 'budi@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'BUDI@EXAMPLE.COM',
    ])->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'http://localhost:5173/reset-password?')
            && str_contains($url, 'email=budi%40example.com')
            && str_contains($url, 'token=');
    });

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'unknown@example.com',
    ])->assertOk()
        ->assertJsonPath('message', 'Jika email terdaftar, tautan reset kata sandi telah dikirim.');
});

it('returns a validation error when a reset link is requested too quickly', function () {
    Notification::fake();

    User::factory()->create(['email' => 'budi@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'budi@example.com',
    ])->assertOk();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'budi@example.com',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonPath('errors.email.0', 'Tautan baru saja dikirim. Tunggu sebentar sebelum mencoba lagi.');
});

it('resets a password once with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'budi@example.com',
        'password' => 'old-password',
        'remember_token' => 'old-token',
    ]);
    $token = Password::broker()->createToken($user);

    $payload = [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ];

    $this->postJson('/api/v1/auth/reset-password', $payload)
        ->assertOk()
        ->assertJsonPath('success', true);

    $user->refresh();

    expect(Hash::check('new-password123', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-token');

    $this->postJson('/api/v1/auth/reset-password', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create(['email' => 'budi@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => 'invalid-token',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('expires password reset tokens after sixty minutes', function () {
    $user = User::factory()->create(['email' => 'budi@example.com']);
    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
