<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauOverviewLane;
use App\Models\Jirau;
use App\Models\JirauColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Jirau::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'default_due_hours' => ['nullable', 'integer', Rule::in(array_keys(JirauColumn::defaultDueOptions()))],
            'overview_lane' => ['nullable', Rule::enum(JirauOverviewLane::class)],
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
