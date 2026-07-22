<?php

namespace App\Http\Requests\Admin;

use App\Enums\OpportunityStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('value')) {
            $this->merge(['value' => null]);
        }
        if (! $this->filled('project_id')) {
            $this->merge(['project_id' => null]);
        }
        if (! $this->filled('expected_close_at')) {
            $this->merge(['expected_close_at' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'exists:contacts,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'stage' => ['required', Rule::enum(OpportunityStage::class)],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_at' => ['nullable', 'date'],
        ];
    }
}
