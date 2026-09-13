<?php

namespace App\Http\Requests\Api\V1\Risk;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IndexFarmerRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('farmer');
    }

    public function rules(): array
    {
        return [
            'week' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'week.date_format' => 'Periode minggu tidak valid.',
        ];
    }
}
