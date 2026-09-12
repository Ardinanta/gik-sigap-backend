<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Models\Commodity;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('buyer');
    }

    public function rules(): array
    {
        $bandengId = Commodity::query()
            ->where('code', 'bandeng')
            ->where('is_active', true)
            ->value('id');

        return [
            'location_id' => [
                'sometimes',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query
                    ->where('type', 'district')
                    ->where('is_active', true)),
            ],
            'fish_size_id' => [
                'sometimes',
                'integer',
                Rule::exists('fish_sizes', 'id')->where(fn ($query) => $query
                    ->where('commodity_id', $bandengId ?? 0)
                    ->where('is_active', true)),
            ],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'location_id.exists' => 'Kecamatan yang dipilih tidak tersedia.',
            'fish_size_id.exists' => 'Ukuran Bandeng yang dipilih tidak tersedia.',
            'start_date.date_format' => 'Format tanggal mulai harus YYYY-MM-DD.',
            'end_date.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
