<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->file('avatar') && ! $this->boolean('remove_avatar')) {
                $validator->errors()->add('avatar', 'Escolha uma foto ou marque a remoção da atual.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_avatar' => $this->boolean('remove_avatar'),
        ]);
    }
}
