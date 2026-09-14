<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\Location;
use App\Models\RiskThreshold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminMasterDataController extends Controller
{
    public function index(): JsonResponse
    {
        $commodity = Commodity::query()->where('code', 'bandeng')->firstOrFail();

        return response()->json(['success' => true, 'message' => 'Master data berhasil dimuat.', 'data' => [
            'locations' => Location::query()->where('type', 'district')->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'fish_sizes' => FishSize::query()->where('commodity_id', $commodity->id)->orderBy('min_weight_gram')->orderBy('id')->get(['id', 'code', 'name', 'min_weight_gram', 'max_weight_gram', 'is_active']),
            'risk_thresholds' => RiskThreshold::query()->with('location:id,code,name')->where('commodity_id', $commodity->id)->latest('effective_from')->latest('id')->get()->map(fn (RiskThreshold $threshold) => [
                'id' => $threshold->id, 'location' => $threshold->location, 'period_type' => $threshold->period_type,
                'threshold_volume_kg' => $threshold->threshold_volume_kg, 'warning_ratio' => $threshold->warning_ratio,
                'effective_from' => $threshold->effective_from?->format('Y-m-d'), 'effective_until' => $threshold->effective_until?->format('Y-m-d'),
                'source_note' => $threshold->source_note, 'is_active' => $threshold->is_active,
            ])->values(),
        ]]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $data = $request->validate($this->locationRules());
        $location = Location::query()->create([...$data, 'type' => 'district', 'is_active' => $data['is_active'] ?? true]);

        return response()->json(['success' => true, 'message' => 'Kecamatan berhasil ditambahkan.', 'data' => $location], 201);
    }

    public function updateLocation(Request $request, Location $location): JsonResponse
    {
        abort_unless($location->type === 'district', 404);
        $data = $request->validate($this->locationRules($location));
        $location->update($data);

        return response()->json(['success' => true, 'message' => 'Kecamatan berhasil diperbarui.', 'data' => $location]);
    }

    public function storeFishSize(Request $request): JsonResponse
    {
        $commodity = Commodity::query()->where('code', 'bandeng')->where('is_active', true)->firstOrFail();
        $data = $request->validate($this->fishSizeRules($commodity));
        $size = FishSize::query()->create([...$data, 'commodity_id' => $commodity->id, 'is_active' => $data['is_active'] ?? true]);

        return response()->json(['success' => true, 'message' => 'Ukuran Bandeng berhasil ditambahkan.', 'data' => $size], 201);
    }

    public function updateFishSize(Request $request, FishSize $fishSize): JsonResponse
    {
        $commodity = Commodity::query()->where('code', 'bandeng')->firstOrFail();
        abort_unless((int) $fishSize->commodity_id === (int) $commodity->id, 404);
        $data = $request->validate($this->fishSizeRules($commodity, $fishSize));
        $fishSize->update($data);

        return response()->json(['success' => true, 'message' => 'Ukuran Bandeng berhasil diperbarui.', 'data' => $fishSize]);
    }

    public function storeThreshold(Request $request): JsonResponse
    {
        $data = $request->validate($this->thresholdRules());
        $commodity = Commodity::query()->where('code', 'bandeng')->where('is_active', true)->firstOrFail();
        $this->ensureThresholdDoesNotOverlap($data, $commodity->id);
        $threshold = RiskThreshold::query()->create([...$data, 'commodity_id' => $commodity->id, 'period_type' => 'weekly', 'is_active' => $data['is_active'] ?? true]);

        return response()->json(['success' => true, 'message' => 'Threshold risiko berhasil ditambahkan.', 'data' => $threshold], 201);
    }

    public function updateThreshold(Request $request, RiskThreshold $riskThreshold): JsonResponse
    {
        $data = $request->validate($this->thresholdRules());
        $this->ensureThresholdDoesNotOverlap($data, $riskThreshold->commodity_id, $riskThreshold);
        $riskThreshold->update($data);

        return response()->json(['success' => true, 'message' => 'Threshold risiko berhasil diperbarui.', 'data' => $riskThreshold]);
    }

    private function locationRules(?Location $location = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($location)],
            'name' => ['required', 'string', 'max:150'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function fishSizeRules(Commodity $commodity, ?FishSize $fishSize = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('fish_sizes', 'code')->where('commodity_id', $commodity->id)->ignore($fishSize)],
            'name' => ['required', 'string', 'max:100'], 'min_weight_gram' => ['nullable', 'numeric', 'min:0'],
            'max_weight_gram' => ['nullable', 'numeric', 'gte:min_weight_gram'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function thresholdRules(): array
    {
        return [
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where(fn ($query) => $query->where('type', 'district'))],
            'threshold_volume_kg' => ['required', 'numeric', 'gt:0'], 'warning_ratio' => ['required', 'numeric', 'gt:0', 'lte:1'],
            'effective_from' => ['required', 'date_format:Y-m-d'], 'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'source_note' => ['nullable', 'string', 'max:1000'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function ensureThresholdDoesNotOverlap(array $data, int $commodityId, ?RiskThreshold $current = null): void
    {
        if (($data['is_active'] ?? true) !== true) {
            return;
        }

        $overlaps = RiskThreshold::query()->where('location_id', $data['location_id'])->where('commodity_id', $commodityId)
            ->where('period_type', 'weekly')->where('is_active', true)->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->whereDate('effective_from', '<=', $data['effective_until'] ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['effective_from' => 'Periode threshold aktif bertumpang tindih dengan konfigurasi yang sudah ada.']);
        }
    }
}
