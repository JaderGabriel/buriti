<?php

namespace App\Http\Requests\Admin;

use App\Enums\JirauTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJirauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'board_name' => ['nullable', 'string', 'max:120'],
            'template' => ['required', Rule::enum(JirauTemplate::class)],
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
}
