<?php

namespace App\Http\Requests\Api\V1\BuyerDemand;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBuyerDemandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('buyer');
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['active', 'fulfilled', 'cancelled', 'expired'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status kebutuhan tidak valid.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
