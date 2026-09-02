<?php

namespace App\Http\Requests\Draws;

use App\Domain\Draws\Enums\DrawStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DrawIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|object>> */
    public function rules(): array
    {
        return [
            'lottery_id' => ['nullable', 'integer', 'min:1', Rule::exists('lotteries', 'id')],
            'external_id' => ['nullable', 'integer', 'min:1', Rule::exists('lotteries', 'external_id')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(DrawStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');
            if (is_string($from) && is_string($to) && $to < $from) {
                $validator->errors()->add('to', 'La fecha final debe ser igual o posterior a la fecha inicial.');
            }
        });
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }

    /** @return array{lottery_id?: int, external_id?: int, from?: string, to?: string, status?: DrawStatus} */
    public function filters(): array
    {
        $validated = $this->validated();
        if (isset($validated['status'])) {
            $validated['status'] = DrawStatus::from($validated['status']);
        }
        unset($validated['page'], $validated['per_page']);

        return $validated;
    }
}
