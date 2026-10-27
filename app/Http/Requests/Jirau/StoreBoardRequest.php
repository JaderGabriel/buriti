<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'name' => ['nullable', 'string', 'max:120'],
            'board_name' => ['nullable', 'string', 'max:120'],
            'template' => ['required', Rule::enum(JirauTemplate::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'project_id' => 'projeto',
            'board_name' => 'nome do quadro',
            'template' => 'modelo',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Escolha o projeto deste quadro.',
            'project_id.exists' => 'Este projeto não existe mais.',
            'template.required' => 'Escolha o modelo do quadro.',
        ];
    }

    public function template(): JirauTemplate
    {
        return JirauTemplate::from((string) $this->validated('template'));
    }

    public function boardName(): ?string
    {
        $name = trim((string) ($this->validated('board_name') ?? $this->validated('name') ?? ''));

        return $name !== '' ? $name : null;
    }

    public function projectId(): int
    {
        return (int) $this->validated('project_id');
    }
}
