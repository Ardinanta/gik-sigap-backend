<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            LocationSeeder::class,
            CommoditySeeder::class,
            FishSizeSeeder::class,
            BuyerTypeSeeder::class,
            UserSeeder::class,
            HarvestPlanSeeder::class,
            BuyerDemandSeeder::class,
        ]);
    }
}
