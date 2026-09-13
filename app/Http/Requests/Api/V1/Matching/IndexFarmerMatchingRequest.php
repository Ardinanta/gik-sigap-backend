<?php

namespace App\Http\Requests\Api\V1\Matching;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IndexFarmerMatchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('farmer');
    }

    public function rules(): array
    {
        return [
            'harvest_plan_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
