<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'farmer', 'name' => 'Petambak'],
            ['code' => 'buyer', 'name' => 'Pembeli'],
            ['code' => 'admin', 'name' => 'Admin'],
        ] as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']],
                [...$role, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
