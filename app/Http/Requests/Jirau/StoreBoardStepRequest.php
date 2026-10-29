<?php

namespace App\Http\Requests\Jirau;

use App\Models\Jirau;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoardStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Jirau::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'create_card' => ['sometimes', 'boolean'],
            'card_id' => ['nullable', 'integer'],
        ];
    }
}
