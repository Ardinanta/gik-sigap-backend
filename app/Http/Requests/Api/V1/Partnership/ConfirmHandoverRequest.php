<?php

namespace App\Http\Requests\Api\V1\Partnership;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ConfirmHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('handover', $this->route('partnership'));

        return true;
    }

    public function rules(): array
    {
        return [
            'volume_kg' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'volume_kg.required' => 'Masukkan berat hasil timbang.',
            'volume_kg.numeric' => 'Berat harus berupa angka.',
            'volume_kg.decimal' => 'Berat maksimal memiliki dua angka desimal.',
            'volume_kg.min' => 'Berat harus lebih dari nol.',
            'volume_kg.max' => 'Berat melebihi batas yang dapat dicatat.',
            'version.*' => 'Muat ulang data penyerahan sebelum mengonfirmasi.',
        ];
    }
}
