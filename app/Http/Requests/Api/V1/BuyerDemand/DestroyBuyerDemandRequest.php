<?php

namespace App\Http\Requests\Api\V1\BuyerDemand;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBuyerDemandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('buyer');
    }

    public function rules(): array
    {
        return [];
    }
}
