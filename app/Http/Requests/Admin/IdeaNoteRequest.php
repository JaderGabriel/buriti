<?php

namespace App\Http\Requests\Admin;

use App\Enums\IdeaNoteColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdeaNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->filled('title') ? trim((string) $this->input('title')) : null,
            'body' => $this->filled('body') ? trim((string) $this->input('body')) : null,
            'color' => $this->input('color') ?: IdeaNoteColor::Amber->value,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', Rule::enum(IdeaNoteColor::class)],
        ];
    }
}
