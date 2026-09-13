<?php

namespace App\Http\Requests\Api\V1\Partnership;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnershipRequest extends FormRequest
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
