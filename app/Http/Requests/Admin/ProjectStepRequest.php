<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProjectStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_completed' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function stepData(): array
    {
        $completed = $this->boolean('is_completed');

        return [
            'title' => $this->string('title')->toString(),
            'notes' => $this->input('notes'),
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
        ];
    }
}
