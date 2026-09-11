<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthService
{
    public function register(array $data, Request $request): User
    {
        $user = DB::transaction(function () use ($data): User {
            $role = Role::query()->where('code', $data['role'])->first();

            if (! $role) {
                throw new RuntimeException('Role registrasi belum dikonfigurasi.');
            }

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'location_id' => $data['location_id'],
                'status' => 'active',
                'password' => $data['password'],
            ]);

            $user->roles()->attach($role->id, ['created_at' => now()]);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $user->load(['location', 'roles']);
    }

    public function login(array $credentials, Request $request): User
    {
        $authenticated = Auth::guard('web')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'status' => 'active',
        ], (bool) ($credentials['remember'] ?? false));

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak valid.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            throw new RuntimeException('Pengguna terautentikasi tidak valid.');
        }

        return $user->load(['location', 'roles']);
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function sendPasswordResetLink(string $email): void
    {
        $status = Password::broker()->sendResetLink(['email' => $email]);

        if (in_array($status, [Password::ResetLinkSent, Password::InvalidUser], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [$this->resetLinkFailureMessage($status)],
        ]);
    }

    public function resetPassword(array $data): void
    {
        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [$this->resetFailureMessage($status)],
            ]);
        }
    }

    private function resetLinkFailureMessage(string $status): string
    {
        return match ($status) {
            Password::ResetThrottled => 'Tautan baru saja dikirim. Tunggu sebentar sebelum mencoba lagi.',
            default => 'Tautan reset belum dapat dikirim. Silakan coba lagi.',
        };
    }

    private function resetFailureMessage(string $status): string
    {
        return match ($status) {
            Password::InvalidUser => 'Email tidak terdaftar.',
            Password::InvalidToken => 'Tautan reset kata sandi tidak valid atau sudah kedaluwarsa.',
            default => 'Kata sandi tidak dapat direset. Silakan minta tautan baru.',
        };
    }
}
