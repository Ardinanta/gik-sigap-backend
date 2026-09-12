<?php

namespace App\Http\Requests\Api\V1\BuyerDemand;

use App\Models\Commodity;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuyerDemandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('buyer');
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('target_location_id') === '') {
            $this->merge(['target_location_id' => null]);
        }
    }

    public function rules(): array
    {
        $commodityId = Commodity::query()
            ->where('code', 'bandeng')
            ->where('is_active', true)
            ->value('id');

        return [
            'fish_size_id' => [
                'required',
                'integer',
                Rule::exists('fish_sizes', 'id')->where(fn ($query) => $query
                    ->where('commodity_id', $commodityId)
                    ->where('is_active', true)),
            ],
            'required_volume_kg' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'target_location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query
                    ->where('type', 'district')
                    ->where('is_active', true)),
            ],
            'need_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'need_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:need_start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fish_size_id.exists' => 'Ukuran bandeng yang dipilih tidak tersedia.',
            'required_volume_kg.gt' => 'Jumlah kebutuhan harus lebih dari 0 kg.',
            'required_volume_kg.max' => 'Jumlah kebutuhan terlalu besar.',
            'target_location_id.exists' => 'Kecamatan tujuan yang dipilih tidak tersedia.',
            'need_start_date.after_or_equal' => 'Tanggal mulai kebutuhan tidak boleh sebelum hari ini.',
            'need_end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
            'notes.max' => 'Catatan maksimal 1.000 karakter.',
        ];
    }
}
