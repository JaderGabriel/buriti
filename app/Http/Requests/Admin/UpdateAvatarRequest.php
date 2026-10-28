<?php

namespace App\Http\Requests\Admin;

use App\Services\AvatarService;
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
            'avatar' => AvatarService::RULES,
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
