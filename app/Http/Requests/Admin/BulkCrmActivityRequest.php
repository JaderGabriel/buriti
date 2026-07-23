<?php

namespace App\Http\Requests\Admin;

use App\Enums\CrmActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('task_id')) {
            $this->merge(['task_id' => null]);
        }
        if (! $this->filled('happened_at')) {
            $this->merge(['happened_at' => now()->toDateTimeString()]);
        }

        $ids = $this->input('contact_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $this->merge([
            'contact_ids' => array_values(array_unique(array_filter(array_map('intval', $ids)))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
            'type' => ['required', Rule::enum(CrmActivityType::class)],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:10000'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'happened_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_ids.required' => 'Selecione pelo menos um contato.',
            'contact_ids.min' => 'Selecione pelo menos um contato.',
        ];
    }
}
