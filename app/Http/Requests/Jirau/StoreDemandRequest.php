<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauCardPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:8000'],
            'priority' => ['nullable', Rule::enum(JirauCardPriority::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Descreva a demanda em um título.',
        ];
    }
}
