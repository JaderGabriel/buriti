<?php

namespace App\Http\Requests\Admin;

use App\Enums\MailMarker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnotateMailboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMail') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pasta' => ['required', 'string', 'max:200'],
            'uid' => ['required', 'integer', 'min:1'],
            'message_id' => ['nullable', 'string', 'max:250'],
            'subject' => ['nullable', 'string', 'max:180'],
            'from' => ['nullable', 'string', 'max:180'],
            'marker' => ['nullable', Rule::in(array_merge([''], MailMarker::values()))],
            'label_id' => ['nullable', 'integer', 'min:1', 'exists:mail_labels,id'],
            'detach_label' => ['sometimes', 'boolean'],
            'kind' => ['nullable', Rule::in(['project', 'jirau', 'idea'])],
            'linkable_id' => ['nullable', 'integer', 'min:1'],
            'link_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
