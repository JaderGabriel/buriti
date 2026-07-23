<?php

namespace App\Http\Requests\Admin;

use App\Enums\CompanyStatus;
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
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

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
            'phone' => ['nullable', 'string', 'max:40'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::enum(CompanyStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
