<?php

namespace App\Http\Requests\Api\V1\Transaction;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasRole('buyer');
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
