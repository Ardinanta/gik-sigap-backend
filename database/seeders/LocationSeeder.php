<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('locations')->updateOrInsert(
            ['code' => 'GRESIK'],
            [
                'parent_id' => null,
                'name' => 'Kabupaten Gresik',
                'type' => 'regency',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $gresikId = DB::table('locations')->where('code', 'GRESIK')->value('id');

        foreach ([
            ['code' => 'MANYAR', 'name' => 'Manyar'],
            ['code' => 'BUNGAH', 'name' => 'Bungah'],
            ['code' => 'SIDAYU', 'name' => 'Sidayu'],
            ['code' => 'UJUNGPANGKAH', 'name' => 'Ujungpangkah'],
        ] as $district) {
            DB::table('locations')->updateOrInsert(
                ['code' => $district['code']],
                [
                    'parent_id' => $gresikId,
                    'name' => $district['name'],
                    'type' => 'district',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
