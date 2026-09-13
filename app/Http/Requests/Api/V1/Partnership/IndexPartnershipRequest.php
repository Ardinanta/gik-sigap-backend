<?php

namespace App\Http\Requests\Api\V1\Partnership;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPartnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasRole('buyer');
    }

    public function rules(): array
    {
        return [
            'view' => ['sometimes', Rule::in(['active', 'history'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
