<?php

namespace App\Http\Requests\Api\V1\Partnership;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmPartnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasRole('farmer');
    }

    public function rules(): array
    {
        return [];
    }
}
