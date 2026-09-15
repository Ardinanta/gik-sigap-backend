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
            ['name' => 'Budi Petambak', 'email' => 'petambak@sigap.test', 'phone' => '6281234567801', 'location_code' => 'MANYAR', 'role' => 'farmer'],
            ['name' => 'Agus Mina Jaya', 'email' => 'agus@sigap.test', 'phone' => '6281234567804', 'location_code' => 'BUNGAH', 'role' => 'farmer'],
            ['name' => 'Nur Aini Tambak', 'email' => 'aini@sigap.test', 'phone' => '6281234567805', 'location_code' => 'SIDAYU', 'role' => 'farmer'],
            ['name' => 'Hasan Pesisir', 'email' => 'hasan@sigap.test', 'phone' => '6281234567806', 'location_code' => 'UJUNGPANGKAH', 'role' => 'farmer'],
            ['name' => 'Siti Pembeli', 'email' => 'pembeli@sigap.test', 'phone' => '6281234567802', 'location_code' => 'BUNGAH', 'role' => 'buyer'],
            ['name' => 'Koperasi Mina Gresik', 'email' => 'koperasi@sigap.test', 'phone' => '6281234567807', 'location_code' => 'GRESIKKOTA', 'role' => 'buyer'],
            ['name' => 'Rina Distributor', 'email' => 'rina@sigap.test', 'phone' => '6281234567808', 'location_code' => 'KEBOMAS', 'role' => 'buyer'],
            ['name' => 'Admin SIGAP', 'email' => 'admin@sigap.test', 'phone' => '6281234567803', 'location_code' => 'GRESIKKOTA', 'role' => 'admin'],
        ];

        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $account) {
                $location = Location::query()
                    ->where('code', $account['location_code'])
                    ->where('is_active', true)
                    ->firstOrFail();
                $role = Role::query()->where('code', $account['role'])->firstOrFail();

                $user = User::query()->withTrashed()->updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'name' => $account['name'],
                        'phone' => $account['phone'],
                        'location_id' => $location->id,
                        'status' => 'active',
                        'email_verified_at' => now(),
                        'password' => 'password123',
                        'deleted_at' => null,
                    ],
                );

                $user->forceFill([
                    'email_verified_at' => now(),
                    'deleted_at' => null,
                ])->save();

                $user->roles()->sync([
                    $role->id => ['created_at' => now()],
                ]);
            }
        });
    }
}
