<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendTestMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageMail() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:180'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.required' => 'Informe o destinatário do teste.',
            'to.email' => 'Informe um e-mail válido.',
        ];
    }
}
