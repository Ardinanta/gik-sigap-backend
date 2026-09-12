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
            ['code' => 'small', 'name' => 'Kecil', 'min_weight_gram' => 125, 'max_weight_gram' => 166.67],
            ['code' => 'medium', 'name' => 'Sedang', 'min_weight_gram' => 250, 'max_weight_gram' => 333.33],
            ['code' => 'large', 'name' => 'Besar', 'min_weight_gram' => 500, 'max_weight_gram' => 1000],
        ] as $fishSize) {
            DB::table('fish_sizes')->updateOrInsert(
                ['commodity_id' => $commodityId, 'code' => $fishSize['code']],
                [
                    'name' => $fishSize['name'],
                    'min_weight_gram' => $fishSize['min_weight_gram'],
                    'max_weight_gram' => $fishSize['max_weight_gram'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
