<?php

namespace App\Http\Requests\Admin;

use App\Enums\CompanyStatus;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
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

        if ($this->filled('website_url')) {
            $this->merge(['website_url' => trim((string) $this->input('website_url'))]);
        }

        $this->merge(PhoneNumber::normalizeInput($this->all()));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->route('company')?->id;
        $isos = PhoneNumber::countries()->pluck('iso')->all();

        return [
            'name' => [
                'required',
                'string',
                'max:180',
                Rule::unique('companies', 'name')->ignore($companyId),
            ],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'document' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone_country' => ['nullable', 'string', Rule::in($isos)],
            'phone_number' => ['nullable', 'string', 'min:8', 'max:20', 'regex:/^[0-9]+$/'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::enum(CompanyStatus::class)],
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
    public function companyData(): array
    {
        $data = $this->validated();
        unset($data['phone_country'], $data['phone_number']);

        return $data;
    }
}
