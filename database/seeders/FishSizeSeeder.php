<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FishSizeSeeder extends Seeder
{
    public function run(): void
    {
        $commodityId = DB::table('commodities')->where('code', 'bandeng')->value('id');

        foreach ([
            ['code' => 'small', 'name' => 'Kecil'],
            ['code' => 'medium', 'name' => 'Sedang'],
            ['code' => 'large', 'name' => 'Besar'],
        ] as $fishSize) {
            DB::table('fish_sizes')->updateOrInsert(
                ['commodity_id' => $commodityId, 'code' => $fishSize['code']],
                [
                    'name' => $fishSize['name'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
