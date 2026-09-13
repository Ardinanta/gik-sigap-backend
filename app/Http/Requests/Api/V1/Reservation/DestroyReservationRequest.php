<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DestroyReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasRole('buyer');
    }

    public function rules(): array
    {
        return [];
    }
}
