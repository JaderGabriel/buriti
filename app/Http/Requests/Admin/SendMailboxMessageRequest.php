<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendMailboxMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMail') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:500'],
            'cc' => ['nullable', 'string', 'max:500'],
            'bcc' => ['nullable', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'signature' => ['sometimes', 'boolean'],
            'in_reply_to' => ['nullable', 'string', 'max:250'],
            'reply_folder' => ['nullable', 'string', 'max:200'],
            'reply_uid' => ['nullable', 'integer', 'min:1'],
            'contact_message_id' => ['nullable', 'integer', 'min:1'],
            'draft' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.required' => 'Indica o destinatário.',
            'subject.required' => 'Indica o assunto.',
            'body.required' => 'A mensagem está vazia.',
        ];
    }
}
