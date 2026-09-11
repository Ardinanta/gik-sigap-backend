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
            ['code' => 'BALONGPANGGANG', 'name' => 'Balongpanggang'],
            ['code' => 'BENJENG', 'name' => 'Benjeng'],
            ['code' => 'BUNGAH', 'name' => 'Bungah'],
            ['code' => 'CERME', 'name' => 'Cerme'],
            ['code' => 'DRIYOREJO', 'name' => 'Driyorejo'],
            ['code' => 'DUDUKSAMPEYAN', 'name' => 'Duduksampeyan'],
            ['code' => 'DUKUN', 'name' => 'Dukun'],
            ['code' => 'GRESIKKOTA', 'name' => 'Gresik'],
            ['code' => 'KEBOMAS', 'name' => 'Kebomas'],
            ['code' => 'KEDAMEAN', 'name' => 'Kedamean'],
            ['code' => 'MANYAR', 'name' => 'Manyar'],
            ['code' => 'MENGANTI', 'name' => 'Menganti'],
            ['code' => 'PANCENG', 'name' => 'Panceng'],
            ['code' => 'SANGKAPURA', 'name' => 'Sangkapura'],
            ['code' => 'SIDAYU', 'name' => 'Sidayu'],
            ['code' => 'TAMBAK', 'name' => 'Tambak'],
            ['code' => 'UJUNGPANGKAH', 'name' => 'Ujungpangkah'],
            ['code' => 'WRINGINANOM', 'name' => 'Wringinanom'],
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
