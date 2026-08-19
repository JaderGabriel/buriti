<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderHomeProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'featured_ids' => ['present', 'array'],
            'featured_ids.*' => ['integer', 'exists:projects,id'],
            'portfolio_ids' => ['present', 'array'],
            'portfolio_ids.*' => ['integer', 'exists:projects,id'],
            'restricted_ids' => ['present', 'array'],
            'restricted_ids.*' => ['integer', 'exists:projects,id'],
            'hidden_ids' => ['present', 'array'],
            'hidden_ids.*' => ['integer', 'exists:projects,id'],
        ];
    }
}
