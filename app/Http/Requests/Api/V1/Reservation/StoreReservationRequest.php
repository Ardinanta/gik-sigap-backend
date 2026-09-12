<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('buyer');
    }

    public function rules(): array
    {
        return [
            'volume_kg' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'volume_kg.required' => 'Jumlah reservasi wajib diisi.',
            'volume_kg.numeric' => 'Jumlah reservasi harus berupa angka.',
            'volume_kg.min' => 'Jumlah reservasi harus lebih dari 0 kg.',
            'notes.max' => 'Catatan maksimal 1.000 karakter.',
        ];
    }
}
