<?php

namespace App\Http\Requests\Admin;

use App\Enums\GoogleEventColor;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'want_meet' => $this->has('want_meet') ? $this->boolean('want_meet') : true,
            'meet_url' => $this->filled('meet_url') ? $this->input('meet_url') : null,
            'project_id' => $this->filled('project_id') ? $this->input('project_id') : null,
            'contact_id' => $this->filled('contact_id') ? $this->input('contact_id') : null,
            'google_color_id' => $this->filled('google_color_id') ? (string) $this->input('google_color_id') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'exists:projects,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'meet_url' => ['nullable', 'url', 'max:255'],
            'want_meet' => ['sometimes', 'boolean'],
            'google_color_id' => ['nullable', Rule::enum(GoogleEventColor::class)],
        ];
    }
}
