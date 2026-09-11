<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BuyerTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'umkm', 'name' => 'UMKM Pengolah'],
            ['code' => 'cooperative', 'name' => 'Koperasi'],
            ['code' => 'trader', 'name' => 'Pedagang'],
            ['code' => 'distributor', 'name' => 'Distributor'],
            ['code' => 'household', 'name' => 'Rumah Tangga'],
            ['code' => 'other', 'name' => 'Lainnya'],
        ] as $buyerType) {
            DB::table('buyer_types')->updateOrInsert(
                ['code' => $buyerType['code']],
                [...$buyerType, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
