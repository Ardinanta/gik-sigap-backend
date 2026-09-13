<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Budi Petambak',
                'email' => 'petambak@sigap.test',
                'phone' => '6281234567801',
                'location_code' => 'MANYAR',
                'role' => 'farmer',
            ],
            [
                'name' => 'Siti Pembeli',
                'email' => 'pembeli@sigap.test',
                'phone' => '6281234567802',
                'location_code' => 'BUNGAH',
                'role' => 'buyer',
            ],
            [
                'name' => 'Admin SIGAP',
                'email' => 'admin@sigap.test',
                'phone' => '6281234567803',
                'location_code' => 'GRESIKKOTA',
                'role' => 'admin',
            ],
        ];

        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $account) {
                $location = Location::query()
                    ->where('code', $account['location_code'])
                    ->where('is_active', true)
                    ->firstOrFail();
                $role = Role::query()->where('code', $account['role'])->firstOrFail();

                $user = User::query()->updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'name' => $account['name'],
                        'phone' => $account['phone'],
                        'location_id' => $location->id,
                        'status' => 'active',
                        'email_verified_at' => now(),
                        'password' => 'password123',
                    ],
                );

                $user->roles()->sync([
                    $role->id => ['created_at' => now()],
                ]);
            }
        });
    }
}
