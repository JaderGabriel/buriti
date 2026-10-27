<?php

namespace App\Http\Requests\Jirau;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderColumnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $jirau = $this->route('jirau');

        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', Rule::exists('jirau_columns', 'id')->where('jirau_id', $jirau?->id)],
        ];
    }
}
