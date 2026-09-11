<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommoditySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('commodities')->updateOrInsert(
            ['code' => 'bandeng'],
            [
                'name' => 'Bandeng',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
