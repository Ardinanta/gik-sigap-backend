<?php

namespace Database\Seeders;

use App\Models\User;
use App\Notifications\PartnershipActivityNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $notifications = [
            ['id' => '01994c80-0000-7000-8000-000000000001', 'email' => 'petambak@sigap.test', 'category' => 'reservation_requested', 'title' => 'Pengajuan pasokan baru', 'message' => 'Siti Pembeli mengajukan 450 kg bandeng dari Tambak Ujung Jaya.', 'route' => '/app/farmer/kemitraan', 'resource_id' => null, 'read' => false, 'days' => 0],
            ['id' => '01994c80-0000-7000-8000-000000000002', 'email' => 'petambak@sigap.test', 'category' => 'partnership_requested', 'title' => 'Pengajuan kemitraan baru', 'message' => 'Ada rekomendasi yang ditindaklanjuti menjadi pengajuan kemitraan.', 'route' => '/app/farmer/kemitraan', 'resource_id' => null, 'read' => true, 'days' => 2],
            ['id' => '01994c80-0000-7000-8000-000000000003', 'email' => 'pembeli@sigap.test', 'category' => 'partnership_confirmed', 'title' => 'Pengajuan kemitraan diterima', 'message' => 'Petambak telah menyepakati volume dan harga. Silakan pantau persiapan serah terima.', 'route' => '/app/buyer/kemitraan', 'resource_id' => null, 'read' => false, 'days' => 0],
            ['id' => '01994c80-0000-7000-8000-000000000004', 'email' => 'pembeli@sigap.test', 'category' => 'handover_confirmed', 'title' => 'Transaksi berhasil diselesaikan', 'message' => 'Serah terima bandeng telah dikonfirmasi oleh kedua pihak.', 'route' => '/app/buyer/kemitraan?tab=history', 'resource_id' => null, 'read' => true, 'days' => 3],
            ['id' => '01994c80-0000-7000-8000-000000000005', 'email' => 'admin@sigap.test', 'category' => 'risk_warning', 'title' => 'Prioritas risiko mingguan', 'message' => 'Terdapat wilayah dengan konsentrasi rencana panen yang perlu dikoordinasikan.', 'route' => '/app/admin/risiko', 'resource_id' => null, 'read' => false, 'days' => 0],
        ];

        foreach ($notifications as $item) {
            $user = User::query()->where('email', $item['email'])->firstOrFail();
            $createdAt = now()->subDays($item['days']);

            DB::table('notifications')->updateOrInsert(
                ['id' => $item['id']],
                [
                    'type' => PartnershipActivityNotification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'category' => $item['category'],
                        'title' => $item['title'],
                        'message' => $item['message'],
                        'route' => $item['route'],
                        'resource_id' => $item['resource_id'],
                    ], JSON_THROW_ON_ERROR),
                    'read_at' => $item['read'] ? $createdAt->copy()->addHour() : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );
        }
    }
}
