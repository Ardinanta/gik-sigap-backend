<?php

namespace App\Http\Requests\Api\V1\Matching;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GenerateMatchingRequest extends FormRequest
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
