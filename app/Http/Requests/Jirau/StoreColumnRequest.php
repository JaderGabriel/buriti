<?php

namespace App\Http\Requests\Jirau;

use App\Models\JirauColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'default_due_hours' => ['nullable', 'integer', Rule::in(array_keys(JirauColumn::defaultDueOptions()))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('default_due_hours') === '') {
            $this->merge(['default_due_hours' => null]);
        }
        if ($this->input('color') === '') {
            $this->merge(['color' => null]);
        }
    }
}
