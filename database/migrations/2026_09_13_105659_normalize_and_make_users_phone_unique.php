<?php

use App\Support\IndonesianPhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $normalizedByUserId = [];
        $userIdByPhone = [];

        DB::table('users')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->select(['id', 'phone'])
            ->each(function (object $user) use (&$normalizedByUserId, &$userIdByPhone): void {
                $normalized = IndonesianPhoneNumber::normalize((string) $user->phone);

                if ($normalized === null) {
                    throw new RuntimeException("Nomor WhatsApp pada user ID {$user->id} tidak valid.");
                }

                if (isset($userIdByPhone[$normalized])) {
                    $existingUserId = $userIdByPhone[$normalized];

                    throw new RuntimeException(
                        "Nomor WhatsApp user ID {$existingUserId} dan {$user->id} sama setelah normalisasi.",
                    );
                }

                $normalizedByUserId[$user->id] = $normalized;
                $userIdByPhone[$normalized] = $user->id;
            });

        DB::transaction(function () use ($normalizedByUserId): void {
            foreach ($normalizedByUserId as $userId => $phone) {
                DB::table('users')->where('id', $userId)->update(['phone' => $phone]);
            }

            Schema::table('users', function (Blueprint $table): void {
                $table->unique('phone');
            });
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
        });
    }
};
