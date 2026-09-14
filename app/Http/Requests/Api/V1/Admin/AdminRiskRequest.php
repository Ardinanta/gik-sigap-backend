<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminRiskRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['week' => ['sometimes', 'date_format:Y-m-d']]; }
}
