<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMailboxSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMail') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mail_signature' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
