<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactStatus;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        $this->merge(PhoneNumber::normalizeInput($this->all()));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isos = PhoneNumber::countries()->pluck('iso')->all();

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180', 'unique:contacts,email'],
            'phone_country' => ['nullable', 'string', Rule::in($isos)],
            'phone_number' => ['nullable', 'string', 'min:8', 'max:20', 'regex:/^[0-9]+$/'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::enum(ContactStatus::class)],
        ];
    }

    /** @return array<string, mixed> */
    public function contactPayload(): array
    {
        $data = $this->validated();
        unset($data['phone_country'], $data['phone_number']);

        return $data;
    }
}
