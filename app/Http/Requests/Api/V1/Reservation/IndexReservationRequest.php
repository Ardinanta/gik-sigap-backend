<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasRole('buyer');
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'cancelled', 'expired'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
