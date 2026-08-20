<?php

namespace App\Http\Requests\Admin;

use App\Enums\IdeaNoteColor;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdeaNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $companyId = $this->filled('company_id') ? (int) $this->input('company_id') : null;
        $contactId = $this->filled('contact_id') ? (int) $this->input('contact_id') : null;

        if ($contactId && ! $companyId) {
            $companyId = Contact::query()->whereKey($contactId)->value('company_id');
            $companyId = $companyId ? (int) $companyId : null;
        }

        $this->merge([
            'title' => $this->filled('title') ? trim((string) $this->input('title')) : null,
            'body' => $this->filled('body') ? trim((string) $this->input('body')) : null,
            'color' => $this->input('color') ?: IdeaNoteColor::Amber->value,
            'company_id' => $companyId,
            'contact_id' => $contactId,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', Rule::enum(IdeaNoteColor::class)],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ];
    }
}
