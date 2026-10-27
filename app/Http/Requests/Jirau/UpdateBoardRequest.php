<?php

namespace App\Http\Requests\Jirau;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'board_name' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'board_name' => 'nome do quadro',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'board_name.required' => 'Informe o nome do quadro.',
        ];
    }

    public function boardName(): string
    {
        return trim((string) $this->validated('board_name'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('board_name')) {
            $this->merge(['board_name' => trim((string) $this->input('board_name'))]);
        }
    }
}
