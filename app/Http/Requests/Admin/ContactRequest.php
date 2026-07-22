<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $contactId = $this->route('contact')?->id;

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => [
                'nullable',
                'email',
                'max:180',
                Rule::unique('contacts', 'email')->ignore($contactId),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:180'],
            'role' => ['nullable', 'string', 'max:120'],
            'preferred_channel' => ['nullable', Rule::in(['email', 'phone', 'whatsapp'])],
            'status' => ['required', Rule::enum(ContactStatus::class)],
            'source' => ['required', Rule::enum(ContactSource::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
