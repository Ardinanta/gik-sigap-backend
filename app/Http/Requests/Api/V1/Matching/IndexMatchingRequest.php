<?php

namespace App\Http\Requests\Api\V1\Matching;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IndexMatchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('buyer');
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
