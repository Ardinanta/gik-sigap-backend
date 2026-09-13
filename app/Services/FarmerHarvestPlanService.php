<?php

namespace App\Services;

use App\Models\Commodity;
use App\Models\HarvestPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FarmerHarvestPlanService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $farmer, array $data, ?UploadedFile $photo): HarvestPlan
    {
        $commodityId = $this->bandengCommodityId();
        $photoData = $this->storePhoto($photo);

        try {
            $plan = DB::transaction(fn (): HarvestPlan => $farmer->harvestPlans()->create([
                ...$data,
                ...$photoData,
                'commodity_id' => $commodityId,
                'status' => 'planned',
            ]));
        } catch (Throwable $exception) {
            $this->deletePhoto($photoData['photo_path'] ?? null);
            throw $exception;
        }

        return $plan->load(['location', 'fishSize']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $farmer, HarvestPlan $harvestPlan, array $data, ?UploadedFile $photo): HarvestPlan
    {
        $this->ensureOwner($farmer, $harvestPlan);
        $photoData = $this->storePhoto($photo);
        $oldPhotoPath = null;

        try {
            $plan = DB::transaction(function () use ($farmer, $harvestPlan, $data, $photoData, $photo, &$oldPhotoPath): HarvestPlan {
                $locked = $farmer->harvestPlans()->whereKey($harvestPlan->id)->lockForUpdate()->firstOrFail();
                $this->ensureEditable($locked);
                $oldPhotoPath = $locked->photo_path;

                $locked->update([
                    ...$data,
                    ...($photo ? $photoData : []),
                    'commodity_id' => $this->bandengCommodityId(),
                    'status' => 'planned',
                ]);

                return $locked;
            });
        } catch (Throwable $exception) {
            $this->deletePhoto($photoData['photo_path'] ?? null);
            throw $exception;
        }

        if ($photo && $oldPhotoPath !== $plan->photo_path) {
            $this->deletePhoto($oldPhotoPath);
        }

        return $plan->load(['location', 'fishSize']);
    }

    public function ensureOwner(User $farmer, HarvestPlan $harvestPlan): void
    {
        abort_unless((int) $harvestPlan->farmer_id === (int) $farmer->id, 404);
    }

    private function ensureEditable(HarvestPlan $harvestPlan): void
    {
        $hasBinding = $harvestPlan->reservations()
            ->where(function ($query): void {
                $query->where('status', 'confirmed')
                    ->orWhere(fn ($pending) => $pending->where('status', 'pending')->where('expires_at', '>', now()));
            })
            ->exists() || $harvestPlan->partnerships()
            ->whereIn('partnerships.status', ['interested', 'discussing', 'matched', 'completed'])
            ->exists();

        if ($harvestPlan->status !== 'planned' || $hasBinding) {
            throw ValidationException::withMessages([
                'harvest_plan' => 'Rencana tidak dapat diubah karena sudah memiliki reservasi atau kemitraan yang mengikat.',
            ]);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function storePhoto(?UploadedFile $photo): array
    {
        if (! $photo) {
            return [];
        }

        $path = $photo->store('harvest-plans', 'public');

        if (! $path) {
            throw new RuntimeException('Foto kondisi tambak tidak dapat disimpan.');
        }

        return [
            'photo_path' => $path,
            'photo_original_name' => $photo->getClientOriginalName(),
            'photo_mime_type' => $photo->getMimeType(),
        ];
    }

    private function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function bandengCommodityId(): int
    {
        $commodityId = Commodity::query()->where('code', 'bandeng')->where('is_active', true)->value('id');

        if (! $commodityId) {
            throw new RuntimeException('Komoditas Bandeng belum dikonfigurasi.');
        }

        return (int) $commodityId;
    }
}
