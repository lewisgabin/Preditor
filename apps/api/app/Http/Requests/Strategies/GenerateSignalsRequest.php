<?php

namespace App\Http\Requests\Strategies;

use App\Domain\Strategies\MethodCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSignalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d'], 'method_codes' => ['sometimes', 'array', 'max:20'], 'method_codes.*' => ['required', 'string', 'distinct', Rule::enum(MethodCode::class)], 'operator_definition' => ['prohibited'], 'formula' => ['prohibited']];
    }
}
