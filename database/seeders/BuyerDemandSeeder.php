<?php

namespace Database\Seeders;

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\Location;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Database\Seeder;

class BuyerDemandSeeder extends Seeder
{
    public function run(MatchingService $matchingService): void
    {
        $commodity = Commodity::query()->where('code', 'bandeng')->firstOrFail();

        $demands = [
            ['buyer' => 'pembeli@sigap.test', 'marker' => '[DEMO-KEMITRAAN-UTAMA]', 'location' => null, 'size' => 'medium', 'volume' => 1200, 'start' => 5, 'end' => 30, 'status' => 'active', 'notes' => 'Kebutuhan rutin distributor untuk demo rekomendasi dan kemitraan.'],
            ['buyer' => 'pembeli@sigap.test', 'marker' => '[DEMO-KEBUTUHAN-KECIL]', 'location' => 'SIDAYU', 'size' => 'small', 'volume' => 800, 'start' => 3, 'end' => 28, 'status' => 'active', 'notes' => 'Kebutuhan bandeng kecil untuk pelanggan rumah makan.'],
            ['buyer' => 'pembeli@sigap.test', 'marker' => '[DEMO-KEBUTUHAN-SELESAI]', 'location' => 'MANYAR', 'size' => 'large', 'volume' => 500, 'start' => -25, 'end' => -10, 'status' => 'fulfilled', 'notes' => 'Kebutuhan periode sebelumnya yang telah terpenuhi.'],
            ['buyer' => 'koperasi@sigap.test', 'marker' => '[DEMO-KOPERASI-MEDIUM]', 'location' => 'BUNGAH', 'size' => 'medium', 'volume' => 2500, 'start' => 2, 'end' => 25, 'status' => 'active', 'notes' => 'Stok koperasi untuk pasar tradisional Gresik.'],
            ['buyer' => 'koperasi@sigap.test', 'marker' => '[DEMO-KOPERASI-LARGE]', 'location' => null, 'size' => 'large', 'volume' => 1400, 'start' => 4, 'end' => 30, 'status' => 'active', 'notes' => 'Bandeng besar untuk kebutuhan acara dan pengolahan.'],
            ['buyer' => 'rina@sigap.test', 'marker' => '[DEMO-DISTRIBUTOR-MEDIUM]', 'location' => 'MANYAR', 'size' => 'medium', 'volume' => 1800, 'start' => 5, 'end' => 22, 'status' => 'active', 'notes' => 'Pasokan mingguan distributor wilayah perkotaan.'],
        ];

        foreach ($demands as $data) {
            $buyer = User::query()->where('email', $data['buyer'])->firstOrFail();
            $fishSize = FishSize::query()
                ->where('commodity_id', $commodity->id)
                ->where('code', $data['size'])
                ->firstOrFail();
            $locationId = $data['location']
                ? Location::query()->where('code', $data['location'])->value('id')
                : null;
            $notes = "{$data['marker']} {$data['notes']}";

            $demand = BuyerDemand::query()->withTrashed()->updateOrCreate(
                ['buyer_id' => $buyer->id, 'notes' => $notes],
                [
                    'target_location_id' => $locationId,
                    'commodity_id' => $commodity->id,
                    'fish_size_id' => $fishSize->id,
                    'required_volume_kg' => $data['volume'],
                    'need_start_date' => today()->addDays($data['start']),
                    'need_end_date' => today()->addDays($data['end']),
                    'status' => $data['status'],
                    'deleted_at' => null,
                ],
            );

            if ($demand->status === 'active') {
                $matchingService->generate($buyer, $demand);
            }
        }
    }
}
