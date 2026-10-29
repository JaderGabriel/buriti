<?php

namespace App\Http\Requests\Jirau;

use App\Models\Jirau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderBoardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Jirau::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', Rule::exists('jiraus', 'id')],
        ];
    }
}
