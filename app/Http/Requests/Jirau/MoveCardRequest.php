<?php

namespace App\Http\Requests\Jirau;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCardRequest extends FormRequest
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
            'column_id' => ['required', 'integer', Rule::exists('jirau_columns', 'id')->where('jirau_id', $jirau?->id)],
            'ordered_ids' => ['nullable', 'array'],
            'ordered_ids.*' => ['integer'],
        ];
    }
}
