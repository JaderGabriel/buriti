<?php

namespace App\Http\Requests\Admin;

use App\Enums\CrmActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('opportunity_id')) {
            $this->merge(['opportunity_id' => null]);
        }
        if (! $this->filled('task_id')) {
            $this->merge(['task_id' => null]);
        }
        if (! $this->filled('happened_at')) {
            $this->merge(['happened_at' => now()->toDateTimeString()]);
        }

        $this->merge([
            'complete_task' => $this->boolean('complete_task'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CrmActivityType::class)],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:10000'],
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'complete_task' => ['sometimes', 'boolean'],
            'happened_at' => ['nullable', 'date'],
        ];
    }
}
