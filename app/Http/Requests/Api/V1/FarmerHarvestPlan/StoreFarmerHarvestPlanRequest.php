<?php

namespace App\Http\Requests\Api\V1\FarmerHarvestPlan;

use App\Models\Commodity;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmerHarvestPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->status === 'active' && $user->hasRole('farmer');
    }

    public function rules(): array
    {
        $commodityId = Commodity::query()->where('code', 'bandeng')->where('is_active', true)->value('id');

        return [
            'pond_name' => ['required', 'string', 'max:150'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where(fn ($query) => $query->where('type', 'district')->where('is_active', true))],
            'fish_size_id' => ['required', 'integer', Rule::exists('fish_sizes', 'id')->where(fn ($query) => $query->where('commodity_id', $commodityId)->where('is_active', true))],
            'estimated_volume_kg' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'harvest_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'asking_price_per_kg' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'pond_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'location_id.exists' => 'Kecamatan tambak yang dipilih tidak tersedia.',
            'fish_size_id.exists' => 'Ukuran Bandeng yang dipilih tidak tersedia.',
            'estimated_volume_kg.gt' => 'Perkiraan volume harus lebih dari 0 kg.',
            'harvest_date.after_or_equal' => 'Tanggal panen tidak boleh sebelum hari ini.',
            'asking_price_per_kg.gt' => 'Harga harapan harus lebih dari Rp0.',
            'photo.image' => 'Foto kondisi tambak harus berupa gambar.',
            'photo.mimes' => 'Foto kondisi tambak harus berformat JPG atau PNG.',
            'photo.max' => 'Ukuran foto kondisi tambak maksimal 5 MB.',
        ];
    }
}
