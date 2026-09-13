<?php

namespace App\Http\Requests\Api\V1\FarmerHarvestPlan;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFarmerHarvestPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->status === 'active' && $user->hasRole('farmer');
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['planned', 'completed', 'cancelled'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
