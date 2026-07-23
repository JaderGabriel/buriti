<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Company;
use App\Services\CompanyResolver;
use App\Support\PhoneNumber;
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

        if ($this->input('company_id') === '' || $this->input('company_id') === null) {
            $this->merge(['company_id' => null]);
        }

        $this->merge(PhoneNumber::normalizeInput($this->all()));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $contactId = $this->route('contact')?->id;
        $isos = PhoneNumber::countries()->pluck('iso')->all();

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => [
                'nullable',
                'email',
                'max:180',
                Rule::unique('contacts', 'email')->ignore($contactId),
            ],
            'phone_country' => ['nullable', 'string', Rule::in($isos)],
            'phone_number' => ['nullable', 'string', 'min:8', 'max:20', 'regex:/^[0-9]+$/'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'company' => ['nullable', 'string', 'max:180'],
            'role' => ['nullable', 'string', 'max:120'],
            'preferred_channel' => ['nullable', Rule::in(['email', 'phone', 'whatsapp'])],
            'status' => ['required', Rule::enum(ContactStatus::class)],
            'source' => ['required', Rule::enum(ContactSource::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone_number.min' => 'Informe um telefone válido (DDD + número).',
            'phone_number.regex' => 'Use apenas números no telefone (sem DDI).',
            'phone_country.in' => 'País inválido.',
        ];
    }

    /** @return array<string, mixed> */
    public function contactData(CompanyResolver $resolver): array
    {
        $data = $this->validated();
        unset($data['phone_country'], $data['phone_number']);
        $typedName = trim((string) ($data['company'] ?? ''));

        if ($typedName !== '') {
            $company = $resolver->findOrCreateByName($typedName);
            $data['company_id'] = $company?->id;
            $data['company'] = $company?->name;

            return $data;
        }

        if (! empty($data['company_id'])) {
            $existing = Company::query()->find($data['company_id']);
            $data['company_id'] = $existing?->id;
            $data['company'] = $existing?->name;

            return $data;
        }

        $data['company_id'] = null;
        $data['company'] = null;

        return $data;
    }
}
